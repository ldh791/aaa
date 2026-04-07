<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$boardKey = query_value('board');
board_or_404($boardKey);
$threadId = query_value('thread_id');
$replyId = query_value('reply_id');
$returnTo = posted_value('return_to') ?: ($replyId !== '' ? thread_url($boardKey, $threadId) . '#post-' . $replyId : thread_url($boardKey, $threadId));

$reason = posted_value('reason');
$detail = text_limit(posted_value('detail'), 1000);
$errors = [];
if ($reason === '' || !in_array($reason, report_reasons(), true)) {
    $errors[] = '신고 사유를 선택해주세요.';
}
if ($threadId === '') {
    $errors[] = '신고 대상을 찾지 못했습니다.';
}
if ($errors !== []) {
    flash_set('error', implode(' ', $errors));
    redirect($returnTo);
}

create_report($boardKey, $threadId, $replyId, $reason, $detail);
flash_set('success', '신고가 접수되었습니다.');
redirect($returnTo);
