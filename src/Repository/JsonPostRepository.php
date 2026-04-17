<?php
declare(strict_types=1);

namespace App\Repository;

final class JsonPostRepository implements PostRepositoryInterface
{
    public function __construct(private array $config)
    {
    }

    public function getThreads(string $boardKey): array
    {
        $threads = $this->readBoard($boardKey);
        usort($threads, static function (array $a, array $b): int {
            $stickyCompare = (int) !empty($b['sticky']) <=> (int) !empty($a['sticky']);
            if ($stickyCompare !== 0) {
                return $stickyCompare;
            }
            return strcmp((string) ($b['bumped_at'] ?? ''), (string) ($a['bumped_at'] ?? ''));
        });
        return array_map(fn (array $thread): array => $this->sanitizeThread($thread), $threads);
    }

    public function findThread(string $boardKey, string $threadId): ?array
    {
        foreach ($this->readBoard($boardKey) as $thread) {
            if (($thread['id'] ?? '') === $threadId) {
                return $this->sanitizeThread($thread);
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
            'password_hash' => $payload['password_hash'],
            'user_id' => $payload['user_id'] ?? null,
            'username' => $payload['username'] ?? null,
            'created_at' => $payload['created_at'],
            'bumped_at' => $payload['created_at'],
            'reply_count' => 0,
            'sticky' => false,
            'locked' => false,
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

            if (!empty($thread['locked'])) {
                return false;
            }

            $parentReplyId = (string) ($payload['parent_reply_id'] ?? '');
            if ($parentReplyId !== '' && !$this->replyExists($thread, $parentReplyId)) {
                $parentReplyId = '';
            }
            if ($parentReplyId !== '') {
                $settings = \site_settings();
                $limit = (int) (($settings['site']['reply_depth_limit'] ?? 100));
                if ($limit > 0 && $this->replyDepth($thread, $parentReplyId) >= $limit) {
                    $parentReplyId = $this->replyParentId($thread, $parentReplyId) ?: $parentReplyId;
                }
            }

            $thread['replies'][] = [
                'id' => $this->nextId(),
                'thread_id' => $threadId,
                'parent_reply_id' => $parentReplyId !== '' ? $parentReplyId : null,
                'name' => $payload['name'],
                'comment' => $payload['comment'],
                'image' => $payload['image'],
                'image_original_name' => $payload['image_original_name'],
                'password_hash' => $payload['password_hash'],
                'user_id' => $payload['user_id'] ?? null,
                'username' => $payload['username'] ?? null,
                'created_at' => $payload['created_at'],
            ];
            $thread['reply_count'] = count($thread['replies']);
            $thread['bumped_at'] = $payload['created_at'];
            $this->writeBoard($boardKey, $threads);
            return true;
        }

        return false;
    }

    public function updateThread(string $boardKey, string $threadId, array $payload): bool
    {
        $threads = $this->readBoard($boardKey);
        foreach ($threads as &$thread) {
            if (($thread['id'] ?? '') !== $threadId) {
                continue;
            }
            $thread['name'] = $payload['name'];
            $thread['subject'] = $payload['subject'];
            $thread['comment'] = $payload['comment'];
            if (array_key_exists('image', $payload)) {
                $thread['image'] = $payload['image'];
                $thread['image_original_name'] = $payload['image_original_name'];
            }
            $thread['updated_at'] = $payload['updated_at'];
            if (!empty($payload['password_hash'])) {
                $thread['password_hash'] = $payload['password_hash'];
            }
            $this->writeBoard($boardKey, $threads);
            return true;
        }
        return false;
    }

    public function deleteThread(string $boardKey, string $threadId): bool
    {
        $threads = $this->readBoard($boardKey);
        $before = count($threads);
        $threads = array_values(array_filter($threads, static fn (array $thread): bool => ($thread['id'] ?? '') !== $threadId));
        if ($before === count($threads)) {
            return false;
        }
        $this->writeBoard($boardKey, $threads);
        return true;
    }

    public function updateReply(string $boardKey, string $threadId, string $replyId, array $payload): bool
    {
        $threads = $this->readBoard($boardKey);
        foreach ($threads as &$thread) {
            if (($thread['id'] ?? '') !== $threadId) {
                continue;
            }
            foreach ($thread['replies'] as &$reply) {
                if (($reply['id'] ?? '') !== $replyId) {
                    continue;
                }
                $reply['name'] = $payload['name'];
                $reply['comment'] = $payload['comment'];
                if (array_key_exists('image', $payload)) {
                    $reply['image'] = $payload['image'];
                    $reply['image_original_name'] = $payload['image_original_name'];
                }
                $reply['updated_at'] = $payload['updated_at'];
                if (!empty($payload['password_hash'])) {
                    $reply['password_hash'] = $payload['password_hash'];
                }
                $thread['bumped_at'] = $payload['updated_at'];
                $this->writeBoard($boardKey, $threads);
                return true;
            }
        }
        return false;
    }

    public function deleteReply(string $boardKey, string $threadId, string $replyId): bool
    {
        $threads = $this->readBoard($boardKey);
        foreach ($threads as &$thread) {
            if (($thread['id'] ?? '') !== $threadId) {
                continue;
            }
            $before = count($thread['replies']);
            $thread['replies'] = array_values(array_filter($thread['replies'], static fn (array $reply): bool => ($reply['id'] ?? '') !== $replyId && ($reply['parent_reply_id'] ?? '') !== $replyId));
            if ($before === count($thread['replies'])) {
                return false;
            }
            $thread['reply_count'] = count($thread['replies']);
            $this->writeBoard($boardKey, $threads);
            return true;
        }
        return false;
    }

    public function search(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $boardFilter = trim((string) ($filters['board'] ?? ''));
        $field = (string) ($filters['field'] ?? 'all');
        $username = trim((string) ($filters['username'] ?? ''));
        $postId = trim((string) ($filters['post_id'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'latest');
        $boards = $boardFilter !== '' && $boardFilter !== 'all' ? [$boardFilter] : array_keys($this->config['boards']);
        $results = [];

        foreach ($boards as $boardKey) {
            foreach ($this->readBoard($boardKey) as $thread) {
                $threadMatch = $this->matchesPost($boardKey, $thread, $thread, $q, $field, $username, $postId, true);
                if ($threadMatch['matched']) {
                    $results[] = $this->buildSearchResult($boardKey, $thread, null, $threadMatch['score']);
                }
                foreach (($thread['replies'] ?? []) as $reply) {
                    $replyMatch = $this->matchesPost($boardKey, $thread, $reply, $q, $field, $username, $postId, false);
                    if ($replyMatch['matched']) {
                        $results[] = $this->buildSearchResult($boardKey, $thread, $reply, $replyMatch['score']);
                    }
                }
            }
        }

        usort($results, static function (array $a, array $b) use ($sort): int {
            $scoreCompare = $b['score'] <=> $a['score'];
            if ($sort !== 'latest' && $scoreCompare !== 0) {
                return $scoreCompare;
            }
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $results;
    }

    private function matchesPost(string $boardKey, array $thread, array $post, string $q, string $field, string $username, string $postId, bool $isThread): array
    {
        $score = 0;
        if ($username !== '') {
            $postUser = (string) (($post['username'] ?? '') !== '' ? $post['username'] : ($post['name'] ?? ''));
            if ($this->lower($postUser) !== $this->lower($username)) {
                return ['matched' => false, 'score' => 0];
            }
            $score += 4;
        }

        if ($postId !== '') {
            $displayNo = (string) \post_display_number($thread, (string) ($post['id'] ?? ''));
            $internalId = (string) ($post['id'] ?? '');
            if (strpos($internalId, $postId) === false && $displayNo !== $postId) {
                return ['matched' => false, 'score' => 0];
            }
            $score += 5;
        }

        if ($q !== '') {
            $tokens = preg_split('/\s+/u', $this->lower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $subject = $this->lower((string) ($post['subject'] ?? ''));
            $comment = $this->lower((string) ($post['comment'] ?? ''));
            $fileName = $this->lower((string) ($post['image_original_name'] ?? ''));

            foreach ($tokens as $token) {
                $matchedToken = false;
                if (($field === 'all' || $field === 'title') && $isThread && $subject !== '' && $this->contains($subject, $token)) {
                    $score += 6;
                    $matchedToken = true;
                }
                if (($field === 'all' || $field === 'comment') && $comment !== '' && $this->contains($comment, $token)) {
                    $score += 3;
                    $matchedToken = true;
                }
                if ($field === 'all' && $fileName !== '' && $this->contains($fileName, $token)) {
                    $score += 2;
                    $matchedToken = true;
                }

                if (!$matchedToken) {
                    return ['matched' => false, 'score' => 0];
                }
            }
        }

        if ($q === '' && $username === '' && $postId === '') {
            return ['matched' => false, 'score' => 0];
        }

        return ['matched' => true, 'score' => $score];
    }

    private function buildSearchResult(string $boardKey, array $thread, ?array $reply, int $score): array
    {
        $post = $reply ?? $thread;
        return [
            'board' => $boardKey,
            'thread_id' => $thread['id'],
            'post_id' => $post['id'],
            'is_reply' => $reply !== null,
            'subject' => (string) ($thread['subject'] ?? ''),
            'name' => (string) ($post['name'] ?? '익명'),
            'comment' => (string) ($post['comment'] ?? ''),
            'image' => $post['image'] ?? null,
            'created_at' => (string) ($post['created_at'] ?? ''),
            'score' => $score,
        ];
    }


    private function replyParentId(array $thread, string $replyId): ?string
    {
        foreach (($thread['replies'] ?? []) as $reply) {
            if (($reply['id'] ?? '') === $replyId) {
                $parent = (string) ($reply['parent_reply_id'] ?? '');
                return $parent !== '' ? $parent : null;
            }
        }
        return null;
    }

    private function replyDepth(array $thread, string $replyId): int
    {
        $depth = 0;
        $current = $replyId;
        while ($current !== '') {
            $parent = $this->replyParentId($thread, $current);
            if ($parent === null) {
                break;
            }
            $depth++;
            $current = $parent;
            if ($depth > 1000) {
                break;
            }
        }
        return $depth + 1;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function contains(string $haystack, string $needle): bool
    {
        if (function_exists('mb_strpos')) {
            return mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
        }
        return strpos($haystack, $needle) !== false;
    }

    private function sanitizeThread(array $thread): array
    {
        $thread['sticky'] = !empty($thread['sticky']);
        $thread['locked'] = !empty($thread['locked']);
        unset($thread['password_hash']);
        foreach (($thread['replies'] ?? []) as $index => $reply) {
            unset($reply['password_hash']);
            $thread['replies'][$index] = $reply;
        }
        return $thread;
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

    private function replyExists(array $thread, string $replyId): bool
    {
        foreach (($thread['replies'] ?? []) as $reply) {
            if (($reply['id'] ?? '') === $replyId) {
                return true;
            }
        }
        return false;
    }
}
