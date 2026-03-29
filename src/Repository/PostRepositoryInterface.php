<?php
declare(strict_types=1);

namespace App\Repository;

interface PostRepositoryInterface
{
    public function getThreads(string $boardKey): array;

    public function findThread(string $boardKey, string $threadId): ?array;

    public function createThread(string $boardKey, array $payload): string;

    public function createReply(string $boardKey, string $threadId, array $payload): bool;
}
