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

        $name = text_limit(posted_value('name') ?: '익명', 30);
        $subject = text_limit(posted_value('subject'), 80);
        $comment = text_limit(posted_value('comment'), 5000);

        $errors = [];
        if (!$isReply && $subject === '' && $comment === '' && empty($_FILES['image']['name'])) {
            $errors[] = '새 스레드는 제목, 내용, 이미지 중 하나는 있어야 합니다.';
        }

        if ($isReply && $comment === '' && empty($_FILES['image']['name'])) {
            $errors[] = '답글은 내용이나 이미지가 필요합니다.';
        }

        $upload = $this->handleUpload($_FILES['image'] ?? null, $errors);

        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            redirect($isReply ? '/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId) : '/board.php?board=' . rawurlencode($boardKey));
        }

        $payload = [
            'name' => $name,
            'subject' => $subject,
            'comment' => $comment,
            'image' => $upload['stored_name'],
            'image_original_name' => $upload['original_name'],
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $repo = repository();
        if ($isReply) {
            $ok = $repo->createReply($boardKey, $threadId, $payload);
            if (!$ok) {
                flash_set('error', '대상 스레드를 찾지 못했습니다.');
                redirect('/board.php?board=' . rawurlencode($boardKey));
            }
            flash_set('success', '답글이 등록되었습니다.');
            redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId));
        }

        $newThreadId = $repo->createThread($boardKey, $payload);
        flash_set('success', '새 스레드가 생성되었습니다.');
        redirect('/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($newThreadId));
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
