<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/scheduler.php';

$processed = run_due_schedules($pdo, $config);
fwrite(STDOUT, 'Processed ' . $processed . " scheduled update(s).\n");
