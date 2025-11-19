<?php

namespace App;

use DateTimeImmutable;
use PDO;

class ProjectService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function list(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute([':user_id' => $userId]);
        $projects = $stmt->fetchAll();

        return array_map(fn($project) => $this->formatProject($project, true), $projects);
    }

    public function get(int $userId, int $projectId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $projectId, ':user_id' => $userId]);
        $project = $stmt->fetch();
        if (!$project) {
            Http::json(['error' => 'Project not found'], 404);
        }

        return $this->formatProject($project, true);
    }

    public function create(int $userId, array $data): array
    {
        Http::requireFields($data, ['title']);

        $stmt = $this->db->prepare('
            INSERT INTO projects (user_id, title, description, image, link, created_at)
            VALUES (:user_id, :title, :description, :image, :link, :created_at)
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':image' => $data['image'] ?? null,
            ':link' => $data['link'] ?? null,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $this->get($userId, (int)$this->db->lastInsertId());
    }

    public function update(int $userId, int $projectId, array $data): array
    {
        $project = $this->get($userId, $projectId);

        $stmt = $this->db->prepare('
            UPDATE projects
            SET title = :title,
                description = :description,
                image = :image,
                link = :link
            WHERE id = :id AND user_id = :user_id
        ');

        $stmt->execute([
            ':title' => $data['title'] ?? $project['title'],
            ':description' => array_key_exists('description', $data) ? $data['description'] : $project['description'],
            ':image' => array_key_exists('image', $data) ? $data['image'] : $project['image'],
            ':link' => array_key_exists('link', $data) ? $data['link'] : $project['link'],
            ':id' => $projectId,
            ':user_id' => $userId,
        ]);

        return $this->get($userId, $projectId);
    }

    public function delete(int $userId, int $projectId): void
    {
        $this->get($userId, $projectId);
        $stmt = $this->db->prepare('DELETE FROM projects WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $projectId, ':user_id' => $userId]);
    }

    public function attachImage(int $userId, int $projectId, string $path): array
    {
        $this->get($userId, $projectId);
        $stmt = $this->db->prepare('UPDATE projects SET image = :image WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':image' => $path, ':id' => $projectId, ':user_id' => $userId]);
        return $this->get($userId, $projectId);
    }

    private function formatProject(array $project, bool $withTasks): array
    {
        $result = [
            'id' => (int)$project['id'],
            'title' => $project['title'],
            'description' => $project['description'],
            'image' => $project['image'],
            'link' => $project['link'],
            'created_at' => $project['created_at'],
        ];

        if ($withTasks) {
            $stmt = $this->db->prepare('SELECT id, title, status, deadline FROM tasks WHERE project_id = :project_id ORDER BY created_at DESC');
            $stmt->execute([':project_id' => $project['id']]);
            $tasks = $stmt->fetchAll();
            $result['tasks'] = array_map(function ($task) {
                return [
                    'id' => (int)$task['id'],
                    'title' => $task['title'],
                    'status' => $task['status'],
                    'deadline' => $task['deadline'],
                ];
            }, $tasks);
        }

        return $result;
    }
}

