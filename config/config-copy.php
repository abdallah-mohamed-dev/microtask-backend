<?php

return [
    'db' => [
        'host' => ' srv1814.hstgr.io',
        'port' => 3306,
        'database' => 'u780865834_microtasks',
        'username' => 'u780865834_abdallah',
        'password' => 'zmrzc4@NosNSbD@k4',
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

