<?php

namespace App;

class UploadService
{
    public function __construct(private readonly array $config)
    {
    }

    public function save(string $type, array $file): string
    {
        $basePath = $this->config['uploads'][$type] ?? null;
        if (!$basePath) {
            Http::json(['error' => 'Upload path misconfigured'], 500);
        }

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            Http::json(['error' => 'File upload failed'], 400);
        }

        $allowed = $this->config['uploads']['allowed_extensions'] ?? [];
        $maxSize = $this->config['uploads']['max_file_size'] ?? (5 * 1024 * 1024);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($allowed && !in_array($ext, $allowed, true)) {
            Http::json(['error' => 'Unsupported file type'], 422);
        }

        if ($file['size'] > $maxSize) {
            Http::json(['error' => 'File exceeds maximum size'], 422);
        }

        if (!is_dir($basePath) && !mkdir($basePath, 0775, true) && !is_dir($basePath)) {
            Http::json(['error' => 'Unable to create upload directory'], 500);
        }

        $filename = uniqid($type . '_', true) . '.' . $ext;
        $destination = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Http::json(['error' => 'Failed to store uploaded file'], 500);
        }

        $publicPath = sprintf(
            '/uploads/%s/%s',
            $type,
            $filename
        );

        return $publicPath;
    }
}

