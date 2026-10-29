<?php

declare(strict_types=1);

function html_to_markdown(string $html): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $root = $dom->getElementsByTagName('body')->item(0);
    if (!$root instanceof DOMNode) {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }
    remove_export_navigation($root);

    $convert = static function (DOMNode $node) use (&$convert): string {
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/\s+/', ' ', $node->nodeValue ?? '') ?? '';
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $convert($child);
        }
        $inner = trim($inner);

        return match ($tag) {
            'h1' => '# ' . $inner . "\n\n",
            'h2' => '## ' . $inner . "\n\n",
            'h3' => '### ' . $inner . "\n\n",
            'h4' => '#### ' . $inner . "\n\n",
            'p', 'div', 'section', 'article' => $inner . "\n\n",
            'br' => "\n",
            'strong', 'b' => '**' . $inner . '**',
            'em', 'i' => '*' . $inner . '*',
            'a' => $node instanceof DOMElement && $node->hasAttribute('href')
                ? (str_starts_with($node->getAttribute('href'), '#')
                    ? $inner
                    : '[' . $inner . '](' . $node->getAttribute('href') . ')')
                : $inner,
            'li' => '- ' . $inner . "\n",
            'ul', 'ol' => $inner . "\n",
            'blockquote' => '> ' . str_replace("\n", "\n> ", $inner) . "\n\n",
            'code' => '`' . $inner . '`',
            'pre' => "```\n" . $inner . "\n```\n\n",
            'img' => $node instanceof DOMElement
                ? '![' . $node->getAttribute('alt') . '](' . $node->getAttribute('src') . ")\n\n"
                : '',
            default => $inner,
        };
    };

    $markdown = $convert($root);
    $markdown = preg_replace("/[ \t]+\n/", "\n", $markdown) ?? $markdown;
    $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;
    $lines = preg_split("/\r\n|\r|\n/", $markdown) ?: [];
    $lines = array_values(array_filter($lines, static function (string $line): bool {
        $text = trim($line);
        return $text !== ''
            && !preg_match('/^(?:\[)?Coordinates\b/iu', $text)
            && !preg_match('/^From Wikipedia, the free encyclopedia$/iu', $text)
            && !preg_match('/^Capital of .{1,120}$/iu', $text)
            && !preg_match('/^Capital city in England$/iu', $text)
            && !preg_match('/^This article is about\b/iu', $text);
    }));
    $markdown = implode("\n\n", $lines);
    return trim($markdown);
}

function clean_export_html(string $html): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('body')->item(0);
    if (!$root instanceof DOMNode) {
        return trim($html);
    }
    remove_export_navigation($root);
    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }
    return trim($result);
}

function embed_export_assets(PDO $pdo, string $html, int $snapshotId): array
{
    $query = $pdo->prepare('SELECT id,source_url,local_name,mime FROM assets WHERE snapshot_id=?');
    $query->execute([$snapshotId]);
    $assets = [];
    foreach ($query as $asset) {
        $path = dirname(__DIR__) . '/public/uploads/' . basename((string) $asset['local_name']);
        if (!is_file($path)) {
            continue;
        }
        $dataUri = 'data:' . $asset['mime'] . ';base64,' . base64_encode((string) file_get_contents($path));
        $html = str_replace('?r=asset&id=' . (int) $asset['id'], $dataUri, $html);
        $html = str_replace('?r=asset&amp;id=' . (int) $asset['id'], $dataUri, $html);
        $assets[] = [
            'source_url' => $asset['source_url'],
            'mime' => $asset['mime'],
            'embedded' => true,
            'data_base64' => base64_encode((string) file_get_contents($path)),
        ];
    }
    return [$html, $assets];
}

function link_export_assets(PDO $pdo, string $html, int $snapshotId): string
{
    $query = $pdo->prepare('SELECT id,source_url FROM assets WHERE snapshot_id=?');
    $query->execute([$snapshotId]);
    foreach ($query as $asset) {
        $sourceUrl = (string) $asset['source_url'];
        $html = str_replace('?r=asset&id=' . (int) $asset['id'], $sourceUrl, $html);
        $html = str_replace('?r=asset&amp;id=' . (int) $asset['id'], htmlspecialchars($sourceUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
    }
    return $html;
}

function text_only_export_html(string $html): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('body')->item(0);
    if (!$root instanceof DOMNode) {
        return trim($html);
    }

    remove_export_navigation($root);
    $xpath = new DOMXPath($dom);
    foreach (iterator_to_array($xpath->query(
        './/img|.//picture|.//svg|.//video|.//audio|.//canvas|.//table|.//figure|.//sup'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " infobox ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " sidebar ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " vertical-nav ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " gallery ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " thumb ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " reflist ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " references ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " metadata ")]',
        $root
    )) as $element) {
        $element->parentNode?->removeChild($element);
    }
    foreach (iterator_to_array($xpath->query('.//a[not(normalize-space())]', $root)) as $emptyLink) {
        $emptyLink->parentNode?->removeChild($emptyLink);
    }
    foreach (iterator_to_array($xpath->query('.//a[starts-with(@href, "#")]', $root)) as $anchor) {
        $anchor->parentNode?->replaceChild($dom->createTextNode($anchor->textContent), $anchor);
    }

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }
    return trim($result);
}

function export_asset_data(PDO $pdo, int $snapshotId): array
{
    $query = $pdo->prepare('SELECT source_url,local_name,mime FROM assets WHERE snapshot_id=?');
    $query->execute([$snapshotId]);
    $assets = [];
    foreach ($query as $asset) {
        $path = dirname(__DIR__) . '/public/uploads/' . basename((string) $asset['local_name']);
        if (!is_file($path)) {
            continue;
        }
        $assets[] = [
            'source_url' => $asset['source_url'],
            'mime' => $asset['mime'],
            'data_base64' => base64_encode((string) file_get_contents($path)),
        ];
    }
    return $assets;
}

function remove_export_navigation(DOMNode $root): void
{
    $xpath = new DOMXPath($root->ownerDocument);
    foreach (iterator_to_array($xpath->query(
        './/nav|.//header|.//footer|.//aside|.//*[@role="navigation"]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " mw-jump ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " mw-editsection ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " mw-portlet ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " vector-toc ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " interlanguage-link ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " catlinks ")]'
        . '|.//*[contains(concat(" ", normalize-space(@class), " "), " metadata ")]',
        $root
    )) as $navigation) {
        $navigation->parentNode?->removeChild($navigation);
    }
}

if ($r === 'export-md') {
    $currentUser = login_required();
    $page = page_library($pdo, (int) ($_GET['id'] ?? 0));
    allow($pdo, (int) $page['library_id'], ['owner', 'editor', 'viewer']);
    $version = (int) ($_GET['version'] ?? 0);
    $query = $pdo->prepare(
        'SELECT * FROM snapshots WHERE saved_page_id=? AND (?=0 OR version_no=?) ORDER BY version_no'
    );
    $query->execute([$page['id'], $version, $version]);
    $snapshots = $query->fetchAll();
    if ($snapshots === []) {
        http_response_code(404);
        exit('Không tìm thấy phiên bản.');
    }

    $content = '# ' . $page['title'] . "\n\n"
        . '- Nguồn: ' . $page['source_url'] . "\n"
        . '- Thư viện: ' . $page['library_name'] . "\n\n";
    foreach ($snapshots as $snapshot) {
        $cleanHtml = text_only_export_html(clean_export_html((string) $snapshot['clean_html']));
        $content .= '## Phiên bản ' . $snapshot['version_no'] . ' - ' . $snapshot['fetched_at'] . "\n\n"
            . html_to_markdown($cleanHtml) . "\n\n";
    }

    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="webshelf-page-' . $page['id'] . '.md"');
    echo $content;
    exit;
}

if ($r === 'compare') {
    $currentUser = login_required();
    $page = page_library($pdo, (int) ($_GET['page'] ?? 0));
    allow($pdo, (int) $page['library_id'], ['owner', 'editor', 'viewer']);
    $first = (int) ($_GET['from'] ?? 0);
    $second = (int) ($_GET['to'] ?? 0);
    $query = $pdo->prepare(
        'SELECT * FROM snapshots WHERE saved_page_id=? AND id IN (?,?) ORDER BY version_no'
    );
    $query->execute([$page['id'], $first, $second]);
    $snapshots = $query->fetchAll();
    if (count($snapshots) !== 2 || $first === $second) {
        http_response_code(400);
        exit('Cần chọn hai phiên bản khác nhau của cùng bài viết.');
    }
    $body = '<h1>Đối chiếu phiên bản</h1><div class="comparison-grid">';
    foreach ($snapshots as $snapshot) {
        $body .= '<article class="card"><h2>v' . (int) $snapshot['version_no'] . '</h2>'
            . '<p class="muted">' . h($snapshot['fetched_at']) . '</p>'
            . '<div class="article">' . $snapshot['clean_html'] . '</div></article>';
    }
    $body .= '</div>';
    layout('Đối chiếu phiên bản', $body);
}

if ($r === 'note') {
    $currentUser = login_required();
    verify_csrf();
    $snapshotId = (int) ($_POST['snapshot_id'] ?? 0);
    $snapshotData = snapshot($pdo, $snapshotId);
    allow($pdo, (int) $snapshotData['library_id'], ['owner', 'editor', 'viewer']);
    $noteId = (int) ($_POST['note_id'] ?? 0);
    $action = $_POST['action'] ?? 'create';
    if ($action === 'delete') {
        $delete = $pdo->prepare('DELETE FROM notes WHERE id=? AND snapshot_id=? AND user_id=?');
        $delete->execute([$noteId, $snapshotId, $currentUser['id']]);
    } elseif ($action === 'update') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            http_response_code(422);
            exit('Ghi chú không được rỗng.');
        }
        $update = $pdo->prepare('UPDATE notes SET body=? WHERE id=? AND snapshot_id=? AND user_id=?');
        $update->execute([$body, $noteId, $snapshotId, $currentUser['id']]);
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body !== '') {
            $insert = $pdo->prepare('INSERT INTO notes(snapshot_id,user_id,body) VALUES(?,?,?)');
            $insert->execute([$snapshotId, $currentUser['id'], $body]);
        }
    }
    header('Location: ?r=read&id=' . $snapshotId);
    exit;
}
