<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private PDO $connection;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed', 'details' => $exception->getMessage()]);
            exit;
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}

