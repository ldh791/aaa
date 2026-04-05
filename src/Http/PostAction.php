<?php
declare(strict_types=1);

namespace App\Http;

final class PostAction
{
    public function handle(): never
    {
        $boardKey = query_value('board');
        board_or_404($boardKey);

        $isReply = query_value('thread') !== '';
        $threadId = query_value('thread');

        $name = text_limit(posted_value('name') ?: (auth_user()['username'] ?? '익명'), 30);
        $subject = text_limit(posted_value('subject'), 80);
        $comment = text_limit(posted_value('comment'), 5000);
        $postPassword = posted_value('post_password');
        $parentReplyId = posted_value('parent_reply_id');

        $errors = [];
        if (!$isReply && $subject === '' && $comment === '' && empty($_FILES['image']['name'])) {
            $errors[] = '새 스레드는 제목, 내용, 이미지 중 하나는 있어야 합니다.';
        }
        if ($isReply && $comment === '' && empty($_FILES['image']['name'])) {
            $errors[] = '답글은 내용이나 이미지가 필요합니다.';
        }
        if ($postPassword === '' || strlen($postPassword) < 4) {
            $errors[] = '게시물 비밀번호는 4자 이상 입력해주세요.';
        }

        $upload = $this->handleUpload($_FILES['image'] ?? null, $errors);

        $returnTo = $this->resolveReturnTo($boardKey, $threadId, $isReply);

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            redirect($returnTo);
        }

        $auth = auth_user();
        $payload = [
            'name' => $name,
            'subject' => $subject,
            'comment' => $comment,
            'image' => $upload['stored_name'],
            'image_original_name' => $upload['original_name'],
            'password_hash' => password_hash($postPassword, PASSWORD_DEFAULT),
            'user_id' => $auth['id'] ?? null,
            'username' => $auth['username'] ?? null,
            'parent_reply_id' => $isReply ? $parentReplyId : null,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $repo = repository();
        if ($isReply) {
            $ok = $repo->createReply($boardKey, $threadId, $payload);
            if (!$ok) {
                $thread = $repo->findThread($boardKey, $threadId);
                $message = $thread === null ? '대상 스레드를 찾지 못했습니다.' : (!empty($thread['locked']) ? '잠긴 스레드에는 새 댓글을 달 수 없습니다.' : '답글 등록에 실패했습니다.');
                flash_set('error', $message);
                redirect($thread === null ? board_url($boardKey) : $returnTo);
            }
            flash_set('success', '답글이 등록되었습니다.');
            redirect($returnTo);
        }

        $newThreadId = $repo->createThread($boardKey, $payload);
        flash_set('success', '새 스레드가 생성되었습니다.');
        if (strpos($returnTo, board_url($boardKey)) === 0) {
            redirect(board_url($boardKey) . '#post-' . rawurlencode($newThreadId));
        }
        redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($newThreadId));
    }


    private function resolveReturnTo(string $boardKey, string $threadId, bool $isReply): string
    {
        $fallback = $isReply ? thread_url($boardKey, $threadId) : board_url($boardKey);
        $returnTo = posted_value('return_to');
        if ($returnTo === '' && isset($_SERVER['HTTP_REFERER'])) {
            $returnTo = (string) $_SERVER['HTTP_REFERER'];
        }
        if ($returnTo === '') {
            return $fallback;
        }

        $parts = parse_url($returnTo);
        if ($parts === false) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        if ($path === '') {
            return $fallback;
        }

        if (str_starts_with($path, '/')) {
            return $path . $query . $fragment;
        }

        return $fallback;
    }

    private function handleUpload(?array $file, array &$errors): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['stored_name' => null, 'original_name' => null];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = '이미지 업로드 중 오류가 발생했습니다.';
            return ['stored_name' => null, 'original_name' => null];
        }

        if (($file['size'] ?? 0) > max_upload_bytes()) {
            $errors[] = '이미지 크기는 최대 ' . format_bytes_label(max_upload_bytes()) . '까지 가능합니다.';
            return ['stored_name' => null, 'original_name' => null];
        }

        $tmp = $file['tmp_name'] ?? '';
        $mime = mime_content_type($tmp) ?: '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            $errors[] = 'JPG, PNG, GIF, WEBP만 업로드할 수 있습니다.';
            return ['stored_name' => null, 'original_name' => null];
        }

        $stored = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $destination = app_config()['upload_path'] . '/' . $stored;

        if (!move_uploaded_file($tmp, $destination)) {
            $errors[] = '업로드 파일 저장에 실패했습니다.';
            return ['stored_name' => null, 'original_name' => null];
        }

        return [
            'stored_name' => $stored,
            'original_name' => basename((string) ($file['name'] ?? $stored)),
        ];
    }
}
