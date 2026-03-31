<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$boardKey = query_value('board') ?: 'b';
$board = board_or_404($boardKey);
$threadId = query_value('id');
$thread = repository()->findThread($boardKey, $threadId);

if ($thread === null) {
    http_response_code(404);
    include __DIR__ . '/../src/View/404.php';
    exit;
}

$flash = flash_get();
$auth = auth_user();
$replyTree = build_reply_tree($thread['replies'] ?? []);
$replyCount = count($thread['replies'] ?? []);

$renderReplyNode = static function (array $reply, int $depth = 0) use (&$renderReplyNode, $boardKey, $threadId): void {
    $depthClass = 'reply-depth-' . min($depth, 4);
    ?>
    <article class="reply-card glass-card <?= e($depthClass) ?>" id="post-<?= e($reply['id']) ?>">
        <div class="thread-meta">
            <p class="thread-subject-line">
                <strong><?= e($reply['name']) ?></strong><?= member_badge_html($reply) ?>
            </p>
            <p class="thread-meta-line">
                <span class="meta-left">
                    <span>No.<?= e($reply['id']) ?></span>
                    <span><?= e(render_time($reply['created_at'])) ?></span>
                </span>
            </p>
        </div>
        <?php if (!empty($reply['parent_reply_id'])): ?>
            <p class="reply-parent-link">↳ 댓글 No.<?= e((string) $reply['parent_reply_id']) ?>에 대한 답글</p>
        <?php endif; ?>
        <?php if (!empty($reply['image'])): ?>
            <a class="detail-image-link" href="<?= e(public_upload_url($reply['image'])) ?>" target="_blank" rel="noreferrer">
                <img class="thread-image reply-image" src="<?= e(public_upload_url($reply['image'])) ?>" alt="reply image">
            </a>
        <?php endif; ?>
        <?php if (!empty($reply['comment'])): ?>
            <div class="thread-body"><?= nl2br(e($reply['comment'])) ?></div>
        <?php endif; ?>

        <?php render_post_actions($boardKey, $threadId, $reply, true, 'thread'); ?>

        <?php if (!empty($reply['children'])): ?>
            <div class="nested-reply-list">
                <?php foreach ($reply['children'] as $child): ?>
                    <?php $renderReplyNode($child, $depth + 1); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
};
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($thread['subject'] ?: '무제') ?> - /<?= e($boardKey) ?>/</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="theme-thread accent-<?= e($board['accent']) ?> sidebar-layout">
<?php render_site_menu('/thread.php'); ?>
<div class="page-shell page-shell-with-sidebar">
    <header class="topbar glass-card">
        <a class="home-link" href="/board.php?board=<?= e($boardKey) ?>">← /<?= e($boardKey) ?>/ 돌아가기</a>
        <div>
            <p class="eyebrow">Thread</p>
            <h1><?= e($thread['subject'] ?: '무제') ?></h1>
            <p><?= e($board['subtitle']) ?></p>
        </div>
        <div class="topbar-actions">
            <a class="header-chip-link" href="/search.php?board=<?= e($boardKey) ?>&post_id=<?= e($thread['id']) ?>">번호로 찾기</a>
            <button type="button" class="button-secondary mobile-toggle" data-toggle-form>답글 작성</button>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <main class="thread-page-layout">
        <section class="thread-card glass-card thread-detail" id="post-<?= e($thread['id']) ?>">
            <div class="thread-meta">
                <p class="thread-subject"><?= e($thread['subject'] ?: '무제') ?></p>
                <p class="thread-meta-line">
                    <span class="meta-left">
                        <strong><?= e($thread['name']) ?></strong><?= member_badge_html($thread) ?>
                        <span>No.<?= e($thread['id']) ?></span>
                        <span><?= e(render_time($thread['created_at'])) ?></span>
                    </span>
                    <span class="meta-right"><span class="count-chip">댓글 <?= e((string) $replyCount) ?></span></span>
                </p>
            </div>
            <?php if (!empty($thread['image'])): ?>
                <a class="detail-image-link" href="<?= e(public_upload_url($thread['image'])) ?>" target="_blank" rel="noreferrer">
                    <img class="thread-image detail-image" src="<?= e(public_upload_url($thread['image'])) ?>" alt="thread image">
                </a>
            <?php endif; ?>
            <?php if (!empty($thread['comment'])): ?>
                <div class="thread-body"><?= nl2br(e($thread['comment'])) ?></div>
            <?php endif; ?>

            <?php render_post_actions($boardKey, (string) $thread['id'], $thread, false, 'thread'); ?>
        </section>

        <aside class="panel glass-card compose-panel sticky-panel" data-form-panel>
            <div class="panel-header">
                <h2>답글 작성</h2>
                <p>내용이나 이미지를 넣고, 수정/삭제용 비밀번호도 입력해주세요.</p>
            </div>
            <div class="reply-context is-collapsed" data-reply-context>
                <div>
                    <strong data-reply-context-label>댓글에 답글 작성 중</strong>
                    <p>선택한 댓글 아래에 답글로 연결됩니다.</p>
                </div>
                <button type="button" class="reply-context-clear" data-reply-context-clear>해제</button>
            </div>
            <form action="/post.php?board=<?= e($boardKey) ?>&thread=<?= e($thread['id']) ?>" method="post" enctype="multipart/form-data" class="stack-form">
                <input type="hidden" name="parent_reply_id" id="reply-parent-id" value="">
                <label>
                    <span>이름</span>
                    <input type="text" name="name" maxlength="30" placeholder="익명" value="<?= e($auth['username'] ?? '') ?>">
                </label>
                <label>
                    <span>내용</span>
                    <textarea id="reply-comment-box" name="comment" rows="6" maxlength="5000" placeholder="댓글 내용을 입력하세요"></textarea>
                </label>
                <label>
                    <span>이미지</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                </label>
                <label>
                    <span>게시물 비밀번호</span>
                    <input type="password" name="post_password" minlength="4" maxlength="100" placeholder="수정/삭제할 때 사용" required oninvalid="this.setCustomValidity('비밀번호를 입력해주세요.')" oninput="this.setCustomValidity('')">
                </label>
                <button class="button-primary" type="submit">답글 등록</button>
            </form>
        </aside>
    </main>

    <section class="reply-list">
        <?php foreach ($replyTree as $reply): ?>
            <?php $renderReplyNode($reply, 0); ?>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
