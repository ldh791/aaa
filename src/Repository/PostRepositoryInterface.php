<?php
declare(strict_types=1);

namespace App\Repository;

interface PostRepositoryInterface
{
    public function getThreads(string $boardKey): array;

    public function findThread(string $boardKey, string $threadId): ?array;

    public function createThread(string $boardKey, array $payload): string;

    public function createReply(string $boardKey, string $threadId, array $payload): bool;

    public function updateThread(string $boardKey, string $threadId, array $payload): bool;

    public function deleteThread(string $boardKey, string $threadId): bool;

    public function updateReply(string $boardKey, string $threadId, string $replyId, array $payload): bool;

    public function deleteReply(string $boardKey, string $threadId, string $replyId): bool;

    public function search(array $filters): array;
}
