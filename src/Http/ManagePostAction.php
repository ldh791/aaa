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
        $returnTo = posted_value('return_to') ?: '/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId);

        $thread = repository()->findThread($boardKey, $threadId);
        if ($thread === null) {
            flash_set('error', '대상 스레드를 찾지 못했습니다.');
            redirect(board_url($boardKey));
        }

        $target = $replyId === '' ? $this->findThreadSecret($boardKey, $threadId) : $this->findReplySecret($boardKey, $threadId, $replyId);
        if ($target === null) {
            flash_set('error', '대상 게시물을 찾지 못했습니다.');
            redirect($returnTo);
        }

        if ($action === 'unlock_edit') {
            $this->unlockEdit($boardKey, $threadId, $replyId, $target, $returnTo);
        }

        if ($action === 'delete') {
            $password = posted_value('post_password');
            if ($password === '' || !password_verify($password, (string) ($target['password_hash'] ?? ''))) {
                flash_set('error', '게시물 비밀번호가 올바르지 않습니다.');
                redirect($returnTo);
            }
            $this->deletePost($boardKey, $threadId, $replyId, $target, $returnTo);
        }

        if ($action === 'edit') {
            $this->editPost($boardKey, $threadId, $replyId, $target, $returnTo);
        }

        flash_set('error', '잘못된 요청입니다.');
        redirect($returnTo);
    }

    private function unlockEdit(string $boardKey, string $threadId, string $replyId, array $target, string $returnTo): never
    {
        $password = posted_value('post_password');
        if ($password === '' || !password_verify($password, (string) ($target['password_hash'] ?? ''))) {
            flash_set('error', '게시물 비밀번호가 올바르지 않습니다.');
            redirect($returnTo);
        }

        unlock_post_edit($boardKey, $threadId, $replyId !== '' ? $replyId : $threadId);
        flash_set('success', '비밀번호가 확인되었습니다. 수정할 내용을 입력해주세요.');
        redirect($returnTo);
    }

    private function editPost(string $boardKey, string $threadId, string $replyId, array $target, string $returnTo): never
    {
        $postId = $replyId !== '' ? $replyId : $threadId;
        if (!is_post_edit_unlocked($boardKey, $threadId, $postId)) {
            flash_set('error', '먼저 게시물 비밀번호를 확인해주세요.');
            redirect($returnTo);
        }

        $name = text_limit(posted_value('name') ?: '익명', 30);
        $subject = text_limit(posted_value('subject'), 80);
        $comment = text_limit(posted_value('comment'), 5000);
        $errors = [];

        if ($replyId === '' && $subject === '' && $comment === '' && empty($target['image'])) {
            $errors[] = '스레드는 제목, 내용, 이미지 중 하나는 유지되어야 합니다.';
        }
        if ($replyId !== '' && $comment === '' && empty($target['image'])) {
            $errors[] = '답글은 내용이나 이미지가 있어야 합니다.';
        }

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            redirect($returnTo);
        }

        $payload = [
            'name' => $name,
            'subject' => $replyId === '' ? $subject : '',
            'comment' => $comment,
            'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $ok = $replyId === ''
            ? repository()->updateThread($boardKey, $threadId, $payload)
            : repository()->updateReply($boardKey, $threadId, $replyId, $payload);

        if ($ok) {
            clear_post_edit_unlock($boardKey, $threadId, $postId);
        }
        flash_set($ok ? 'success' : 'error', $ok ? '게시물이 수정되었습니다.' : '게시물 수정에 실패했습니다.');
        redirect($returnTo);
    }

    private function deletePost(string $boardKey, string $threadId, string $replyId, array $target, string $returnTo): never
    {
        if (!empty($target['image'])) {
            delete_upload_file((string) $target['image']);
        }

        $ok = $replyId === ''
            ? repository()->deleteThread($boardKey, $threadId)
            : repository()->deleteReply($boardKey, $threadId, $replyId);

        flash_set($ok ? 'success' : 'error', $ok ? '게시물이 삭제되었습니다.' : '게시물 삭제에 실패했습니다.');
        if ($replyId === '') {
            redirect(board_url($boardKey));
        }
        redirect($returnTo);
    }

    private function findThreadSecret(string $boardKey, string $threadId): ?array
    {
        return raw_repository_find_thread($boardKey, $threadId);
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
