<?php
declare(strict_types=1);

namespace App\Repository;

final class JsonPostRepository implements PostRepositoryInterface
{
    public function __construct(private readonly array $config)
    {
    }

    public function getThreads(string $boardKey): array
    {
        $threads = $this->readBoard($boardKey);
        usort($threads, static fn (array $a, array $b): int => strcmp($b['bumped_at'], $a['bumped_at']));
        return $threads;
    }

    public function findThread(string $boardKey, string $threadId): ?array
    {
        foreach ($this->readBoard($boardKey) as $thread) {
            if (($thread['id'] ?? '') === $threadId) {
                return $thread;
            }
        }

        return null;
    }

    public function createThread(string $boardKey, array $payload): string
    {
        $threads = $this->readBoard($boardKey);
        $threadId = $this->nextId();

        $threads[] = [
            'id' => $threadId,
            'board' => $boardKey,
            'subject' => $payload['subject'],
            'name' => $payload['name'],
            'comment' => $payload['comment'],
            'image' => $payload['image'],
            'image_original_name' => $payload['image_original_name'],
            'created_at' => $payload['created_at'],
            'bumped_at' => $payload['created_at'],
            'reply_count' => 0,
            'replies' => [],
        ];

        $this->writeBoard($boardKey, $threads);
        return $threadId;
    }

    public function createReply(string $boardKey, string $threadId, array $payload): bool
    {
        $threads = $this->readBoard($boardKey);

        foreach ($threads as &$thread) {
            if (($thread['id'] ?? '') !== $threadId) {
                continue;
            }

            $thread['replies'][] = [
                'id' => $this->nextId(),
                'name' => $payload['name'],
                'comment' => $payload['comment'],
                'image' => $payload['image'],
                'image_original_name' => $payload['image_original_name'],
                'created_at' => $payload['created_at'],
            ];
            $thread['reply_count'] = count($thread['replies']);
            $thread['bumped_at'] = $payload['created_at'];
            $this->writeBoard($boardKey, $threads);
            return true;
        }

        return false;
    }

    private function readBoard(string $boardKey): array
    {
        $path = $this->boardPath($boardKey);
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeBoard(string $boardKey, array $threads): void
    {
        $path = $this->boardPath($boardKey);
        file_put_contents($path, json_encode($threads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function boardPath(string $boardKey): string
    {
        return $this->config['storage_path'] . '/data/boards/' . $boardKey . '.json';
    }

    private function nextId(): string
    {
        return date('YmdHis') . bin2hex(random_bytes(3));
    }
}
