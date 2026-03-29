<?php
declare(strict_types=1);

namespace App\Http;

final class ManagePostAction
{
    public function handle(): never
    {
        $boardKey = query_value('board');
        board_or_404($boardKey);
        $threadId = query_value('thread_id');
        $replyId = query_value('reply_id');
        $action = posted_value('manage_action');

        $thread = repository()->findThread($boardKey, $threadId);
        if ($thread === null) {
            flash_set('error', '대상 스레드를 찾지 못했습니다.');
            redirect('/board.php?board=' . rawurlencode($boardKey));
        }

        $target = $replyId === '' ? $this->findThreadSecret($boardKey, $threadId) : $this->findReplySecret($boardKey, $threadId, $replyId);
        if ($target === null) {
            flash_set('error', '대상 게시물을 찾지 못했습니다.');
            redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
        }

        $password = posted_value('post_password');
        if ($password === '' || !password_verify($password, (string) ($target['password_hash'] ?? ''))) {
            flash_set('error', '게시물 비밀번호가 올바르지 않습니다.');
            redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
        }

        if ($action === 'delete') {
            $this->deletePost($boardKey, $threadId, $replyId, $target);
        }

        if ($action === 'edit') {
            $this->editPost($boardKey, $threadId, $replyId, $target);
        }

        flash_set('error', '잘못된 요청입니다.');
        redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
    }

    private function editPost(string $boardKey, string $threadId, string $replyId, array $target): never
    {
        $name = text_limit(posted_value('name') ?: '익명', 30);
        $subject = text_limit(posted_value('subject'), 80);
        $comment = text_limit(posted_value('comment'), 5000);
        $newPassword = posted_value('new_post_password');
        $errors = [];

        if ($replyId === '' && $subject === '' && $comment === '' && empty($target['image'])) {
            $errors[] = '스레드는 제목, 내용, 이미지 중 하나는 유지되어야 합니다.';
        }
        if ($replyId !== '' && $comment === '' && empty($target['image'])) {
            $errors[] = '답글은 내용이나 이미지가 있어야 합니다.';
        }

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
        }

        $payload = [
            'name' => $name,
            'subject' => $replyId === '' ? $subject : '',
            'comment' => $comment,
            'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        if ($newPassword !== '') {
            if (strlen($newPassword) < 4) {
                flash_set('error', '새 게시물 비밀번호는 4자 이상이어야 합니다.');
                redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
            }
            $payload['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $ok = $replyId === ''
            ? repository()->updateThread($boardKey, $threadId, $payload)
            : repository()->updateReply($boardKey, $threadId, $replyId, $payload);

        flash_set($ok ? 'success' : 'error', $ok ? '게시물이 수정되었습니다.' : '게시물 수정에 실패했습니다.');
        redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
    }

    private function deletePost(string $boardKey, string $threadId, string $replyId, array $target): never
    {
        if (!empty($target['image'])) {
            delete_upload_file((string) $target['image']);
        }

        $ok = $replyId === ''
            ? repository()->deleteThread($boardKey, $threadId)
            : repository()->deleteReply($boardKey, $threadId, $replyId);

        flash_set($ok ? 'success' : 'error', $ok ? '게시물이 삭제되었습니다.' : '게시물 삭제에 실패했습니다.');
        if ($replyId === '') {
            redirect('/board.php?board=' . rawurlencode($boardKey));
        }
        redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
    }

    private function findThreadSecret(string $boardKey, string $threadId): ?array
    {
        $raw = raw_repository_find_thread($boardKey, $threadId);
        return $raw;
    }

    private function findReplySecret(string $boardKey, string $threadId, string $replyId): ?array
    {
        $rawThread = raw_repository_find_thread($boardKey, $threadId);
        if ($rawThread === null) {
            return null;
        }
        foreach (($rawThread['replies'] ?? []) as $reply) {
            if (($reply['id'] ?? '') === $replyId) {
                return $reply;
            }
        }
        return null;
    }
}
