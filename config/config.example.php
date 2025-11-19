<?php

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'microtasks',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'token_length' => 40,
    ],
    'uploads' => [
        'projects' => __DIR__ . '/../uploads/projects',
        'tasks' => __DIR__ . '/../uploads/tasks',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_file_size' => 5 * 1024 * 1024, // 5 MB
    ],
];

