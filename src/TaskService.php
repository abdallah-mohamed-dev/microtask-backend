<?php

namespace App;

use DateTimeImmutable;
use PDO;

class TaskService
{
    private const VALID_STATUSES = ['pending', 'in_progress', 'completed', 'blocked'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function get(int $taskId, ?int $userId = null): array
    {
        $stmt = $this->db->prepare('
            SELECT t.*
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            WHERE t.id = :id ' . ($userId ? 'AND p.user_id = :user_id' : '') . '
            LIMIT 1
        ');
        $params = [':id' => $taskId];
        if ($userId) {
            $params[':user_id'] = $userId;
        }
        $stmt->execute($params);
        $task = $stmt->fetch();

        if (!$task) {
            Http::json(['error' => 'Task not found'], 404);
        }

        return $this->formatTask($task);
    }

    public function create(int $userId, array $data): array
    {
        Http::requireFields($data, ['project_id', 'title']);
        $this->assertProjectOwnership($userId, (int)$data['project_id']);

        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            Http::json(['error' => 'Invalid status'], 422);
        }

        $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        $links = isset($data['links']) && is_array($data['links']) ? $data['links'] : [];

        $stmt = $this->db->prepare('
            INSERT INTO tasks (project_id, title, description, status, deadline, created_at)
            VALUES (:project_id, :title, :description, :status, :deadline, :created_at)
        ');

        $stmt->execute([
            ':project_id' => (int)$data['project_id'],
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':deadline' => $data['deadline'] ?? null,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $taskId = (int)$this->db->lastInsertId();

        if ($tags) {
            foreach ($tags as $tag) {
                $this->addTag($userId, $taskId, ['tag' => $tag], false);
            }
        }

        if ($links) {
            foreach ($links as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $this->addLink($userId, $taskId, $link, false);
            }
        }

        return $this->get($taskId, $userId);
    }

    public function update(int $userId, int $taskId, array $data): array
    {
        $task = $this->get($taskId, $userId);

        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            Http::json(['error' => 'Invalid status'], 422);
        }

        $stmt = $this->db->prepare('
            UPDATE tasks
            SET title = :title,
                description = :description,
                status = :status,
                deadline = :deadline
            WHERE id = :id
        ');

        $stmt->execute([
            ':title' => $data['title'] ?? $task['title'],
            ':description' => array_key_exists('description', $data) ? $data['description'] : $task['description'],
            ':status' => $data['status'] ?? $task['status'],
            ':deadline' => array_key_exists('deadline', $data) ? $data['deadline'] : $task['deadline'],
            ':id' => $taskId,
        ]);

        return $this->get($taskId, $userId);
    }

    public function delete(int $userId, int $taskId): void
    {
        $this->get($taskId, $userId);
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $taskId]);
    }

    public function addTag(int $userId, int $taskId, array $data, bool $returnTask = true): ?array
    {
        Http::requireFields($data, ['tag']);
        $this->get($taskId, $userId);

        $stmt = $this->db->prepare('INSERT INTO task_tags (task_id, tag) VALUES (:task_id, :tag)');
        $stmt->execute([
            ':task_id' => $taskId,
            ':tag' => $data['tag'],
        ]);

        return $returnTask ? $this->get($taskId, $userId) : null;
    }

    public function deleteTag(int $userId, int $taskId, int $tagId): array
    {
        $this->get($taskId, $userId);
        $stmt = $this->db->prepare('DELETE FROM task_tags WHERE id = :id AND task_id = :task_id');
        $stmt->execute([':id' => $tagId, ':task_id' => $taskId]);
        return $this->get($taskId, $userId);
    }

    public function addLink(int $userId, int $taskId, array $data, bool $returnTask = true): ?array
    {
        Http::requireFields($data, ['url']);
        $this->get($taskId, $userId);

        $stmt = $this->db->prepare('INSERT INTO task_links (task_id, title, url) VALUES (:task_id, :title, :url)');
        $stmt->execute([
            ':task_id' => $taskId,
            ':title' => $data['title'] ?? null,
            ':url' => $data['url'],
        ]);

        return $returnTask ? $this->get($taskId, $userId) : null;
    }

    public function deleteLink(int $userId, int $taskId, int $linkId): array
    {
        $this->get($taskId, $userId);
        $stmt = $this->db->prepare('DELETE FROM task_links WHERE id = :id AND task_id = :task_id');
        $stmt->execute([':id' => $linkId, ':task_id' => $taskId]);
        return $this->get($taskId, $userId);
    }

    public function addImage(int $userId, int $taskId, string $path): array
    {
        $this->get($taskId, $userId);
        $stmt = $this->db->prepare('INSERT INTO task_images (task_id, file_path, created_at) VALUES (:task_id, :file_path, :created_at)');
        $stmt->execute([
            ':task_id' => $taskId,
            ':file_path' => $path,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return $this->get($taskId, $userId);
    }

    public function deleteImage(int $userId, int $taskId, int $imageId): array
    {
        $this->get($taskId, $userId);
        $stmt = $this->db->prepare('DELETE FROM task_images WHERE id = :id AND task_id = :task_id');
        $stmt->execute([':id' => $imageId, ':task_id' => $taskId]);
        return $this->get($taskId, $userId);
    }

    private function assertProjectOwnership(int $userId, int $projectId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM projects WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $projectId, ':user_id' => $userId]);
        if (!$stmt->fetch()) {
            Http::json(['error' => 'Project not found'], 404);
        }
    }

    private function formatTask(array $task): array
    {
        $tags = $this->fetchTags((int)$task['id']);
        $links = $this->fetchLinks((int)$task['id']);
        $images = $this->fetchImages((int)$task['id']);

        return [
            'id' => (int)$task['id'],
            'project_id' => (int)$task['project_id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'status' => $task['status'],
            'deadline' => $task['deadline'],
            'created_at' => $task['created_at'],
            'tags' => array_column($tags, 'tag'),
            'tag_meta' => $tags,
            'links' => $links,
            'images' => array_column($images, 'file_path'),
            'image_meta' => $images,
        ];
    }

    private function fetchTags(int $taskId): array
    {
        $stmt = $this->db->prepare('SELECT id, tag FROM task_tags WHERE task_id = :task_id');
        $stmt->execute([':task_id' => $taskId]);
        return array_map(fn($row) => ['id' => (int)$row['id'], 'tag' => $row['tag']], $stmt->fetchAll());
    }

    private function fetchLinks(int $taskId): array
    {
        $stmt = $this->db->prepare('SELECT id, title, url FROM task_links WHERE task_id = :task_id');
        $stmt->execute([':task_id' => $taskId]);
        return array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'url' => $row['url'],
            ];
        }, $stmt->fetchAll());
    }

    private function fetchImages(int $taskId): array
    {
        $stmt = $this->db->prepare('SELECT id, file_path FROM task_images WHERE task_id = :task_id');
        $stmt->execute([':task_id' => $taskId]);
        return array_map(fn($row) => ['id' => (int)$row['id'], 'file_path' => $row['file_path']], $stmt->fetchAll());
    }
}

