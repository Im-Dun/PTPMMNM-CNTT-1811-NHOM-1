<?php
return [
    'db' => ['dsn' => 'mysql:host=127.0.0.1;dbname=webshelf;charset=utf8mb4', 'user' => 'root', 'pass' => ''],
    // Only hosts explicitly approved for the assignment. Redirects are re-checked.
    'allowed_hosts' => ['localhost', '127.0.0.1', 'en.wikipedia.org', 'www.wikipedia.org', 'upload.wikimedia.org', 'commons.wikimedia.org', 'meta.wikimedia.org', 'thumb.wikimedia.org', 'www.w3.org', 'www.w3schools.com', 'www.example.com', 'www.iana.org', 'www.ietf.org', 'www.rfc-editor.org','www.pexels.com' ],
    'allowed_ports' => [80, 443, 8081],
    // Keep true only for the local fixture server; use false for public deployments.
    'allow_private_hosts' => true,
    'fetch_timeout' => 60,
    'max_redirects' => 5,
    'max_response_bytes' => 5 * 1024 * 1024,
];
