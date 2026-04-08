<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$boardKey = query_value('board') ?: detect_board_key_from_request() ?: 'b';
$board = board_or_404($boardKey);
$threadId = query_value('id');
$thread = repository()->findThread($boardKey, $threadId);

if ($thread === null) {
    http_response_code(404);
    include __DIR__ . '/../src/View/404.php';
    exit;
}

$flash = flash_get();
$replyTree = build_reply_tree($thread['replies'] ?? []);
$replyCount = count($thread['replies'] ?? []);
$displayNumbers = thread_display_number_map($thread);
$initialReplyCount = thread_preview_initial_count();
$replyBatchCount = thread_preview_batch_count();
$replyIndex = 0;

$renderFlatBundleReply = static function (array $reply) use ($boardKey, $threadId, $displayNumbers): void {
    ?>
    <article class="reply-card glass-card reply-bundle-item reply-depth-flat" id="post-<?= e((string) $reply['id']) ?>">
        <div class="thread-meta">
            <p class="thread-subject-line"><strong><?= e($reply['name']) ?></strong><?= member_badge_html($reply) ?></p>
            <p class="thread-meta-line">
                <span class="meta-left">
                    <span>No.<?= e((string) ($displayNumbers[$reply['id']] ?? $reply['id'])) ?></span>
                    <span><?= e(render_time($reply['created_at'])) ?></span>
                </span>
            </p>
        </div>
        <?php if (!empty($reply['parent_reply_id'])): ?>
            <p class="reply-parent-link">↳ 댓글 No.<?= e((string) ($displayNumbers[$reply['parent_reply_id']] ?? $reply['parent_reply_id'])) ?>에 연결된 답글</p>
        <?php endif; ?>
        <?php if (!empty($reply['image'])): ?>
            <a class="detail-image-link" href="<?= e(public_upload_url($reply['image'])) ?>" target="_blank" rel="noreferrer">
                <img class="thread-image reply-image" src="<?= e(public_upload_url($reply['image'])) ?>" alt="reply image">
            </a>
        <?php endif; ?>
        <?php if (!empty($reply['comment'])): ?>
            <div class="thread-body reply-body"><?= nl2br(e((string) $reply['comment'])) ?></div>
        <?php endif; ?>
        <?php render_post_actions($boardKey, $threadId, $reply, true, 'thread'); ?>
    </article>
    <?php
};

$renderReplyNode = static function (array $reply, int $depth = 0) use (&$renderReplyNode, $renderFlatBundleReply, &$replyIndex, $boardKey, $threadId, $displayNumbers, $initialReplyCount): void {
    $currentIndex = $replyIndex++;
    $depthClass = 'reply-depth-' . min($depth, 1);
    $collapsedClass = $currentIndex >= $initialReplyCount ? ' is-collapsed' : '';
    ?>
    <article class="reply-card glass-card <?= e($depthClass) ?><?= e($collapsedClass) ?>" id="post-<?= e((string) $reply['id']) ?>" data-preview-item>
        <div class="thread-meta">
            <div class="thread-meta-topline">
                <p class="thread-subject-line">
                    <strong><?= e($reply['name']) ?></strong><?= member_badge_html($reply) ?>
                </p>
            </div>
            <p class="thread-meta-line">
                <span class="meta-left">
                    <span>No.<?= e((string) ($displayNumbers[$reply['id']] ?? $reply['id'])) ?></span>
                    <span><?= e(render_time($reply['created_at'])) ?></span>
                </span>
            </p>
        </div>
        <?php if (!empty($reply['parent_reply_id'])): ?>
            <p class="reply-parent-link">↳ 댓글 No.<?= e((string) ($displayNumbers[$reply['parent_reply_id']] ?? $reply['parent_reply_id'])) ?>에 연결된 답글</p>
        <?php endif; ?>
        <?php if (!empty($reply['image'])): ?>
            <a class="detail-image-link" href="<?= e(public_upload_url($reply['image'])) ?>" target="_blank" rel="noreferrer">
                <img class="thread-image reply-image" src="<?= e(public_upload_url($reply['image'])) ?>" alt="reply image">
            </a>
        <?php endif; ?>
        <?php if (!empty($reply['comment'])): ?>
            <div class="thread-body reply-body"><?= nl2br(e((string) $reply['comment'])) ?></div>
        <?php endif; ?>

        <?php render_post_actions($boardKey, $threadId, $reply, true, 'thread'); ?>

        <?php if (!empty($reply['children'])): ?>
            <?php if ($depth >= 1): ?>
                <?php $bundleReplies = flatten_descendant_replies($reply['children']); $bundleId = 'reply-bundle-' . (string) $reply['id']; $bundleGroups = group_replies_by_parent($bundleReplies); ?>
                <div class="reply-bundle">
                    <div class="reply-bundle-head">
                        <button type="button" class="reply-bundle-toggle" data-toggle-target="<?= e($bundleId) ?>"><span class="reply-bundle-toggle-label">더보기 답글</span><strong><?= e((string) count($bundleReplies)) ?></strong></button>
                    </div>
                    <div id="<?= e($bundleId) ?>" class="reply-bundle-list is-collapsed" data-toggle-panel>
                        <?php foreach ($bundleGroups as $parentReplyId => $groupReplies): ?>
                            <section class="reply-bundle-group">
                                <?php if ($parentReplyId !== '' && isset($replyLookup[$parentReplyId])): ?>
                                    <?php render_bundle_group_quote($replyLookup[$parentReplyId], $displayNumbers, count($groupReplies)); ?>
                                <?php endif; ?>
                                <div class="reply-bundle-group-list">
                                    <?php foreach ($groupReplies as $bundleReply): ?>
                                        <?php $renderFlatBundleReply($bundleReply); ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="nested-reply-list">
                    <?php foreach ($reply['children'] as $child): ?>
                        <?php $renderReplyNode($child, $depth + 1); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
<?php render_site_menu(board_url($boardKey)); ?>
<div class="page-shell page-shell-with-sidebar thread-shell">
    <header class="topbar glass-card topbar-thread">
        <a class="home-link" href="<?= e(board_url($boardKey)) ?>">← /<?= e($boardKey) ?>/ 돌아가기</a>
        <div class="board-topbar-copy">
            <p class="eyebrow">Thread</p>
            <h1><?= e($thread['subject'] ?: '무제') ?></h1>
            <p><?= e($board['subtitle']) ?></p>
        </div>
        <div class="topbar-side-spacer" aria-hidden="true"></div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <main class="thread-page-layout thread-stage thread-single-column">
        <section class="thread-main-column">
            <section class="thread-card glass-card thread-detail thread-detail-hero" id="post-<?= e((string) $thread['id']) ?>">
                <div class="thread-detail-header">
                    <div class="thread-detail-copy">
                        <p class="thread-kicker">원본 스레드</p>
                        <h2 class="thread-display-title"><?= e($thread['subject'] ?: '무제') ?></h2>
                        <p class="thread-meta-line thread-detail-meta">
                            <span class="meta-left">
                                <strong><?= e($thread['name']) ?></strong><?= member_badge_html($thread) ?>
                                <span>No.<?= e((string) ($displayNumbers[$thread['id']] ?? '1')) ?></span>
                                <span><?= e(render_time($thread['created_at'])) ?></span>
                            </span>
                        </p>
                    </div>
                </div>
                <?php if (!empty($thread['image'])): ?>
                    <a class="detail-image-link" href="<?= e(public_upload_url($thread['image'])) ?>" target="_blank" rel="noreferrer">
                        <img class="thread-image detail-image" src="<?= e(public_upload_url($thread['image'])) ?>" alt="thread image">
                    </a>
                <?php endif; ?>
                <?php if (!empty($thread['comment'])): ?>
                    <div class="thread-body thread-body-featured thread-body-plain"><?= nl2br(e((string) $thread['comment'])) ?></div>
                <?php endif; ?>
                <?php render_post_actions($boardKey, (string) $thread['id'], $thread, false, 'thread'); ?>
            </section>

            <section class="reply-section reply-section-inline-list">
                <div class="reply-section-head reply-section-head-inline">
                    <span class="count-chip">댓글 <?= e((string) $replyCount) ?></span>
                </div>
                <div class="reply-list reply-list-embedded" data-preview-list>
                    <?php if (empty($replyTree)): ?>
                        <div class="empty-state reply-empty">
                            <p>아직 댓글이 없습니다. 첫 댓글을 남겨보세요.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($replyTree as $reply): ?>
                            <?php $renderReplyNode($reply, 0); ?>
                        <?php endforeach; ?>
                        <?php if ($replyCount > $initialReplyCount): ?>
                            <button type="button" class="load-more-chip" data-preview-more data-preview-batch="<?= e((string) $replyBatchCount) ?>">댓글 더보기</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        </section>
    </main>
<?php render_mobile_reply_dock($boardKey); ?>
</div>
</body>
</html>
