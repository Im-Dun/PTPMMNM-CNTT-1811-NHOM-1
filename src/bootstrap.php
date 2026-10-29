<?php
declare(strict_types=1);
session_start();
$requiredExtensions = ['pdo_mysql', 'dom', 'mbstring', 'curl'];
$missingExtensions = array_values(array_filter(
    $requiredExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));
if ($missingExtensions !== []) {
    http_response_code(500);
    exit('Thiếu PHP extension bắt buộc: ' . implode(', ', $missingExtensions) . '.');
}
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) { http_response_code(500); exit('Thiếu config.php. Hãy sao chép config.example.php.'); }
$config = require $configFile;
$pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
function ensure_page_title_column(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM saved_pages LIKE 'title'")->fetch();
    if ($column !== false) {
        return;
    }

    $pdo->exec("ALTER TABLE saved_pages ADD title VARCHAR(500) NOT NULL DEFAULT '' AFTER source_url");
    $pdo->exec(
        "UPDATE saved_pages p
         SET p.title = COALESCE((
             SELECT s.title
             FROM snapshots s
             WHERE s.saved_page_id = p.id
             ORDER BY s.version_no DESC
             LIMIT 1
         ), '')
         WHERE p.title = ''"
    );
}
ensure_page_title_column($pdo);
require __DIR__ . '/functions.php';
require __DIR__ . '/Fetcher.php';
