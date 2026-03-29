<?php
declare(strict_types=1);

namespace App\Repository;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?array;

    public function createUser(string $username, string $passwordHash): bool;
}
