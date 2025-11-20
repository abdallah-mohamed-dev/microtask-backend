<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use App\Http;
use App\ProjectService;
use App\TaskService;
use App\UploadService;

// Serve static files directly (uploads, images, etc.)
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestPath = ltrim($requestPath, '/');
$filePath = __DIR__ . '/' . $requestPath;

// If it's a file request and file exists, serve it directly
if ($requestPath && !str_starts_with($requestPath, 'api/') && file_exists($filePath) && is_file($filePath)) {
    $mimeType = mime_content_type($filePath);
    if ($mimeType) {
        header('Content-Type: ' . $mimeType);
    }
    header('Access-Control-Allow-Origin: *');
    readfile($filePath);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    if (str_starts_with($class, $prefix)) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

$configPath = __DIR__ . '/config/config.php';
if (!file_exists($configPath)) {
    Http::json(['error' => 'Missing config.php. Copy config/config.example.php to config/config.php and update credentials.'], 500);
}

$config = require $configPath;
$db = (new Database($config['db']))->getConnection();
$authService = new AuthService($db, $config);
$projectService = new ProjectService($db);
$taskService = new TaskService($db);
$uploadService = new UploadService($config);

$method = $_SERVER['REQUEST_METHOD'];
$path = strtok(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '?');
$body = getJsonBody();
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

$publicRoutes = [
    'POST /api/auth/register' => fn() => Http::json($authService->register($body), 201),
    'POST /api/auth/login' => fn() => Http::json($authService->login($body)),
];

$routeKey = $method . ' ' . $path;
if (isset($publicRoutes[$routeKey])) {
    $publicRoutes[$routeKey]();
}

$user = $authService->authenticate($authHeader);

if ($routeKey === 'GET /api/auth/me') {
    Http::json($authService->me($user));
}

// Project routes
if ($routeKey === 'GET /api/projects') {
    Http::json($projectService->list((int)$user['id']));
}

if ($routeKey === 'POST /api/projects') {
    Http::json($projectService->create((int)$user['id'], $body), 201);
}

if (preg_match('#^/api/projects/(\d+)$#', $path, $matches)) {
    $projectId = (int)$matches[1];
    if ($method === 'GET') {
        Http::json($projectService->get((int)$user['id'], $projectId));
    } elseif ($method === 'PUT') {
        Http::json($projectService->update((int)$user['id'], $projectId, $body));
    } elseif ($method === 'DELETE') {
        $projectService->delete((int)$user['id'], $projectId);
        Http::json(['success' => true]);
    }
}

if (preg_match('#^/api/projects/(\d+)/upload-image$#', $path, $matches) && $method === 'POST') {
    $projectId = (int)$matches[1];
    if (empty($_FILES['image'])) {
        Http::json(['error' => 'Image file is required'], 422);
    }
    $path = $uploadService->save('projects', $_FILES['image']);
    Http::json($projectService->attachImage((int)$user['id'], $projectId, $path));
}

// Task routes
if ($routeKey === 'POST /api/tasks') {
    Http::json($taskService->create((int)$user['id'], $body), 201);
}

if (preg_match('#^/api/tasks/(\d+)$#', $path, $matches)) {
    $taskId = (int)$matches[1];
    if ($method === 'GET') {
        Http::json($taskService->get($taskId, (int)$user['id']));
    } elseif ($method === 'PUT') {
        Http::json($taskService->update((int)$user['id'], $taskId, $body));
    } elseif ($method === 'DELETE') {
        $taskService->delete((int)$user['id'], $taskId);
        Http::json(['success' => true]);
    }
}

if (preg_match('#^/api/tasks/(\d+)/add-tag$#', $path, $matches) && $method === 'POST') {
    $taskId = (int)$matches[1];
    Http::json($taskService->addTag((int)$user['id'], $taskId, $body));
}

if (preg_match('#^/api/tasks/(\d+)/tag/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $taskId = (int)$matches[1];
    $tagId = (int)$matches[2];
    Http::json($taskService->deleteTag((int)$user['id'], $taskId, $tagId));
}

if (preg_match('#^/api/tasks/(\d+)/add-link$#', $path, $matches) && $method === 'POST') {
    $taskId = (int)$matches[1];
    Http::json($taskService->addLink((int)$user['id'], $taskId, $body));
}

if (preg_match('#^/api/tasks/(\d+)/link/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $taskId = (int)$matches[1];
    $linkId = (int)$matches[2];
    Http::json($taskService->deleteLink((int)$user['id'], $taskId, $linkId));
}

if (preg_match('#^/api/tasks/(\d+)/upload-image$#', $path, $matches) && $method === 'POST') {
    $taskId = (int)$matches[1];
    if (empty($_FILES['image'])) {
        Http::json(['error' => 'Image file is required'], 422);
    }
    $path = $uploadService->save('tasks', $_FILES['image']);
    Http::json($taskService->addImage((int)$user['id'], $taskId, $path));
}

if (preg_match('#^/api/tasks/(\d+)/image/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $taskId = (int)$matches[1];
    $imageId = (int)$matches[2];
    Http::json($taskService->deleteImage((int)$user['id'], $taskId, $imageId));
}

Http::json(['error' => 'Not found'], 404);

function getJsonBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if ($contentType && !str_contains($contentType, 'application/json')) {
        return [];
    }

    $input = file_get_contents('php://input');
    if (!$input) {
        return [];
    }

    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}


