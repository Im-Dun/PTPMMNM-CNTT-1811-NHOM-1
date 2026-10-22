<?php

declare(strict_types=1);

final class Fetcher
{
    private const SUPPORTED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly array $config)
    {
    }

    private function parsedUrl(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('URL không hợp lệ.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $allowedHosts = array_map('strtolower', $this->config['allowed_hosts'] ?? []);
        $allowedPorts = array_map('intval', $this->config['allowed_ports'] ?? [80, 443]);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Chỉ hỗ trợ URL HTTP hoặc HTTPS.');
        }
        if (!in_array($host, $allowedHosts, true)) {
            throw new RuntimeException('URL không thuộc host được cho phép.');
        }
        if (!in_array((int) $port, $allowedPorts, true)) {
            throw new RuntimeException('Cổng của URL không được cho phép.');
        }

        $parts['scheme'] = $scheme;
        $parts['host'] = $host;
        $parts['port'] = (int) $port;
        return $parts;
    }

    private function validatedIp(string $host): string
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('Không phân giải được địa chỉ đích.');
        }

        $allowPrivateHosts = (bool) ($this->config['allow_private_hosts'] ?? false);
        foreach ($addresses as $address) {
            $isPublic = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
            if ($isPublic || $allowPrivateHosts) {
                return $address;
            }
        }

        throw new RuntimeException('Địa chỉ đích thuộc mạng nội bộ hoặc bị cấm.');
    }

    private function request(string $url): array
    {
        $parts = $this->parsedUrl($url);
        $ip = $this->validatedIp($parts['host']);
        $maxBytes = (int) ($this->config['max_response_bytes'] ?? 5 * 1024 * 1024);
        $buffer = '';
        $tooLarge = false;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Không khởi tạo được HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => (int) ($this->config['fetch_timeout'] ?? 8),
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'WebShelf educational archiver',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$parts['host'] . ':' . $parts['port'] . ':' . $ip],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$buffer, &$tooLarge, $maxBytes): int {
                $buffer .= $chunk;
                if (strlen($buffer) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($tooLarge) {
            throw new RuntimeException('Phản hồi vượt quá giới hạn kích thước.');
        }
        if ($ok === false) {
            throw new RuntimeException('Không lấy được nội dung: ' . ($error ?: 'lỗi kết nối không xác định.'));
        }

        return [
            'headers' => substr($buffer, 0, $headerSize),
            'body' => substr($buffer, $headerSize),
            'status' => $status,
            'content_type' => $contentType,
        ];
    }

    private function absolute(string $base, string $url): string
    {
        if (parse_url($url, PHP_URL_SCHEME)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return (string) parse_url($base, PHP_URL_SCHEME) . ':' . $url;
        }

        $baseParts = $this->parsedUrl($base);
        $path = (string) ($baseParts['path'] ?? '/');
        if (str_starts_with($url, '/')) {
            $path = $url;
        } else {
            $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');
            $path = ($directory === '' ? '' : $directory) . '/' . $url;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $normalizedPath = '/' . implode('/', $segments);
        return $baseParts['scheme'] . '://' . $baseParts['host']
            . (($baseParts['port'] ?? 0) !== ($baseParts['scheme'] === 'https' ? 443 : 80)
                ? ':' . $baseParts['port']
                : '')
            . $normalizedPath;
    }

    private function get(string $url): array
    {
        $current = $url;
        $maxRedirects = (int) ($this->config['max_redirects'] ?? 5);
        for ($attempt = 0; $attempt <= $maxRedirects; $attempt++) {
            $this->parsedUrl($current);
            $response = $this->request($current);
            $status = $response['status'];
            if ($status >= 300 && $status < 400) {
                if (!preg_match('/^Location:\s*(.+)$/mi', $response['headers'], $match)) {
                    throw new RuntimeException('Chuyển hướng không hợp lệ.');
                }
                $current = $this->absolute($current, trim($match[1]));
                continue;
            }

            return [
                $response['body'],
                $status,
                $response['content_type'],
                $current,
            ];
        }

        throw new RuntimeException('Quá nhiều chuyển hướng.');
    }

    public function archive(string $url): array
    {
        [$html, $status, $contentType, $finalUrl] = $this->get($url);
        if ($status !== 200) {
            throw new RuntimeException("Máy chủ trả HTTP $status.");
        }
        if (!str_contains(strtolower($contentType), 'text/html')) {
            throw new RuntimeException('Chỉ hỗ trợ HTML.');
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('HTML không đọc được.');
        }

        $xpath = new DOMXPath($dom);
        $node = $xpath->query(
            '//article|//main|//*[contains(concat(" ",normalize-space(@class)," ")," article-content ")]'
        )->item(0);
        if (!$node instanceof DOMElement) {
            throw new RuntimeException('Không tìm thấy phần bài viết hỗ trợ (article/main/.article-content).');
        }

        $title = trim($xpath->evaluate('string(//title)'))
            ?: trim($xpath->evaluate('string(.//h1)', $node));
        foreach (iterator_to_array($xpath->query(
            './/script|.//style|.//iframe|.//object|.//embed|.//form|.//link',
            $node
        )) as $bad) {
            $bad->parentNode?->removeChild($bad);
        }
        foreach (iterator_to_array($xpath->query('.//*', $node)) as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (str_starts_with(strtolower($attribute->name), 'on')
                    || in_array(strtolower($attribute->name), ['style', 'srcset'], true)) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
        if (mb_strlen($text) < 40 || preg_match('/\b(not found|404|error page)\b/i', $text)) {
            throw new RuntimeException('Trang không có nội dung bài viết hợp lệ.');
        }

        return [
            'title' => mb_substr($title, 0, 500),
            'html' => $dom->saveHTML($node),
            'text' => $text,
            'status' => $status,
            'final_url' => $finalUrl,
            'dom' => $dom,
            'node' => $node,
        ];
    }

    public function saveImages(DOMXPath $xpath, DOMNode $node, string $base, int $snapshotId, PDO $pdo): string
    {
        $directory = dirname(__DIR__) . '/public/uploads';
        foreach (iterator_to_array($xpath->query('.//img[@src]', $node)) as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }
            $source = $this->absolute($base, $image->getAttribute('src'));
            $asset = null;
            try {
                [$data, $status, $type] = $this->get($source);
                $mime = strtolower(explode(';', $type)[0]);
                if ($status !== 200 || !isset(self::SUPPORTED_IMAGE_TYPES[$mime])) {
                    throw new RuntimeException('Ảnh không thuộc loại được hỗ trợ.');
                }
                $name = bin2hex(random_bytes(16)) . '.' . self::SUPPORTED_IMAGE_TYPES[$mime];
                if (file_put_contents($directory . '/' . $name, $data, LOCK_EX) === false) {
                    throw new RuntimeException('Không ghi được ảnh cục bộ.');
                }
                $insert = $pdo->prepare(
                    'INSERT INTO assets(snapshot_id,source_url,local_name,mime) VALUES(?,?,?,?)'
                );
                $insert->execute([$snapshotId, $source, $name, $mime]);
                $asset = (string) $pdo->lastInsertId();
                $image->setAttribute('src', '?r=asset&id=' . $asset);
                foreach (['srcset', 'onerror'] as $attribute) {
                    $image->removeAttribute($attribute);
                }
            } catch (RuntimeException) {
                $image->parentNode?->removeChild($image);
            }
        }

        return $node->ownerDocument?->saveHTML($node) ?? '';
    }
}
