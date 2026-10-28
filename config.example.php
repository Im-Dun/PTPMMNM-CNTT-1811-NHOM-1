<?php
return [
    'db' => ['dsn' => 'mysql:host=127.0.0.1;dbname=webshelf;charset=utf8mb4', 'user' => 'root', 'pass' => ''],
    // Only hosts explicitly approved for the assignment. Redirects are re-checked.
    'allowed_hosts' => ['localhost', '127.0.0.1'],
    'allowed_ports' => [80, 443, 8081],
    // Keep true only for the local fixture server; use false for public deployments.
    'allow_private_hosts' => true,
    'fetch_timeout' => 60,
    'max_redirects' => 5,
    'max_response_bytes' => 5 * 1024 * 1024,
];
