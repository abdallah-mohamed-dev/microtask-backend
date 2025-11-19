<?php

namespace App;

class Http
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function requireFields(array $body, array $required): void
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing) {
            self::json(['error' => 'Missing required fields', 'fields' => $missing], 422);
        }
    }
}

