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
date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Ho_Chi_Minh'));
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

function ensure_schedule_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS update_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            saved_page_id INT NOT NULL,
            created_by INT NOT NULL,
            frequency ENUM('hourly','daily','weekly','monthly') NOT NULL,
            run_time TIME NOT NULL DEFAULT '08:00:00',
            day_of_week TINYINT UNSIGNED NULL,
            day_of_month TINYINT UNSIGNED NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            notify_success TINYINT(1) NOT NULL DEFAULT 1,
            notify_failure TINYINT(1) NOT NULL DEFAULT 1,
            next_run_at DATETIME NOT NULL,
            last_run_at DATETIME NULL,
            last_status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
            last_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY one_schedule_per_page (saved_page_id),
            KEY due_schedules (enabled, next_run_at),
            FOREIGN KEY (saved_page_id) REFERENCES saved_pages(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            schedule_id INT NULL,
            type ENUM('success','failure') NOT NULL,
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY user_unread (user_id, is_read, created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (schedule_id) REFERENCES update_schedules(id) ON DELETE SET NULL
        )"
    );
}
ensure_schedule_tables($pdo);
require __DIR__ . '/functions.php';
require __DIR__ . '/Fetcher.php';
require __DIR__ . '/scheduler.php';
