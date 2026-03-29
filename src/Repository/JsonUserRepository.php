<?php
declare(strict_types=1);

namespace App\Repository;

final class JsonUserRepository implements UserRepositoryInterface
{
    public function __construct(private array $config)
    {
    }

    public function findByUsername(string $username): ?array
    {
        foreach ($this->readUsers() as $user) {
            if (($user['username'] ?? '') === $username) {
                return $user;
            }
        }

        return null;
    }

    public function createUser(string $username, string $passwordHash): bool
    {
        $users = $this->readUsers();
        foreach ($users as $user) {
            if (($user['username'] ?? '') === $username) {
                return false;
            }
        }

        $users[] = [
            'id' => date('YmdHis') . bin2hex(random_bytes(3)),
            'username' => $username,
            'password_hash' => $passwordHash,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        file_put_contents($this->usersPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return true;
    }

    private function readUsers(): array
    {
        $path = $this->usersPath();
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function usersPath(): string
    {
        return $this->config['storage_path'] . '/data/users.json';
    }
}
