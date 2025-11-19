<?php

namespace App;

use DateTimeImmutable;
use PDO;

class AuthService
{
    public function __construct(
        private readonly PDO $db,
        private readonly array $config
    ) {
    }

    public function register(array $data): array
    {
        Http::requireFields($data, ['name', 'email', 'password']);

        $exists = $this->findByEmail($data['email']);
        if ($exists) {
            Http::json(['error' => 'Email already registered'], 409);
        }

        $password = password_hash($data['password'], PASSWORD_BCRYPT);
        $token = $this->generateToken();

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, token, created_at) VALUES (:name, :email, :password, :token, :created_at)'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':password' => $password,
            ':token' => $token,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $this->formatUser($this->getById((int)$this->db->lastInsertId()));
    }

    public function login(array $data): array
    {
        Http::requireFields($data, ['email', 'password']);

        $user = $this->findByEmail($data['email']);
        if (!$user || !password_verify($data['password'], $user['password'])) {
            Http::json(['error' => 'Invalid credentials'], 401);
        }

        $token = $this->generateToken();
        $stmt = $this->db->prepare('UPDATE users SET token = :token WHERE id = :id');
        $stmt->execute([':token' => $token, ':id' => $user['id']]);

        $user['token'] = $token;
        return $this->formatUser($user);
    }

    public function authenticate(?string $header): array
    {
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            Http::json(['error' => 'Unauthorized'], 401);
        }

        $token = substr($header, 7);
        $stmt = $this->db->prepare('SELECT * FROM users WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            Http::json(['error' => 'Unauthorized'], 401);
        }

        return $user;
    }

    public function me(array $user): array
    {
        return $this->formatUser($user);
    }

    private function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function getById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            Http::json(['error' => 'User not found'], 404);
        }
        return $user;
    }

    private function generateToken(): string
    {
        $length = $this->config['security']['token_length'] ?? 40;
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    private function formatUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'token' => $user['token'],
            'created_at' => $user['created_at'],
        ];
    }
}

