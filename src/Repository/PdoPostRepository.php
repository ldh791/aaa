<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;
use RuntimeException;

final class PdoPostRepository implements PostRepositoryInterface
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s',
            $config['db']['driver'],
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['database']
        );

        $this->pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function getThreads(string $boardKey): array
    {
        throw new RuntimeException('PDO repository is prepared for later migration, but not enabled in this test build.');
    }

    public function findThread(string $boardKey, string $threadId): ?array
    {
        throw new RuntimeException('PDO repository is prepared for later migration, but not enabled in this test build.');
    }

    public function createThread(string $boardKey, array $payload): string
    {
        throw new RuntimeException('PDO repository is prepared for later migration, but not enabled in this test build.');
    }

    public function createReply(string $boardKey, string $threadId, array $payload): bool
    {
        throw new RuntimeException('PDO repository is prepared for later migration, but not enabled in this test build.');
    }
}
