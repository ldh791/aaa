<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$boardKey = query_value('board') ?: detect_board_key_from_request() ?: 'b';
$board = board_or_404($boardKey);
$threads = repository()->getThreads($boardKey);
$flash = flash_get();
$auth = auth_user();
$initialPreviewCount = board_preview_initial_count();
$previewBatchCount = board_preview_batch_count();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>/<?= e($boardKey) ?>/ - <?= e(app_config()['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="theme-board accent-<?= e($board['accent']) ?> sidebar-layout">
<?php render_site_menu(board_url($boardKey)); ?>
<div class="page-shell page-shell-with-sidebar">
    <header class="topbar glass-card board-topbar">
        <a class="home-link" href="/">← 홈</a>
        <div class="board-topbar-copy">
            <p class="eyebrow">Board</p>
            <h1>/<?= e($boardKey) ?>/ <?= e($board['title']) ?></h1>
            <p><?= e($board['subtitle']) ?></p>
            <div class="board-hero-actions">
                <button type="button" class="header-chip-link board-compose-toggle" data-toggle-group="board-compose" data-toggle-target="board-compose-panel">새 스레드 작성</button>
                <a class="header-chip-link" href="/search.php?board=<?= e($boardKey) ?>">보드 검색</a>
            </div>
        </div>
        <div class="topbar-side-spacer" aria-hidden="true"></div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="layout-two-column layout-board-single">
        <aside id="board-compose-panel" class="panel glass-card compose-panel is-collapsed compose-panel-inline" data-toggle-panel>
            <div class="panel-header">
                <h2>새 스레드 만들기</h2>
                <p>제목, 내용, 이미지 중 하나는 필요하고, 수정/삭제용 비밀번호도 필요합니다.</p>
            </div>
            <form action="/post.php?board=<?= e($boardKey) ?>" method="post" enctype="multipart/form-data" class="stack-form">
                <input type="hidden" name="return_to" value="<?= e(board_url($boardKey)) ?>">
                <label>
                    <span>이름</span>
                    <input type="text" name="name" maxlength="30" placeholder="익명" value="<?= e($auth['username'] ?? '') ?>">
                </label>
                <label>
                    <span>제목</span>
                    <input type="text" name="subject" maxlength="80" placeholder="스레드 제목">
                </label>
                <label>
                    <span>내용</span>
                    <textarea name="comment" rows="6" maxlength="5000" placeholder="내용을 입력하세요"></textarea>
                </label>
                <label>
                    <span>이미지</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                </label>
                <label>
                    <span>게시물 비밀번호</span>
                    <input type="password" name="post_password" minlength="4" maxlength="100" placeholder="수정/삭제할 때 사용" required oninvalid="this.setCustomValidity('비밀번호를 입력해주세요.')" oninput="this.setCustomValidity('')">
                </label>
                <button class="button-primary" type="submit">스레드 등록</button>
            </form>
        </aside>

        <main class="thread-list">
            <?php if ($threads === []): ?>
                <section class="panel glass-card empty-state">
                    <h2>아직 스레드가 없습니다.</h2>
                    <p>첫 번째 스레드를 올려서 보드를 시작해보세요.</p>
                </section>
            <?php endif; ?>

            <?php foreach ($threads as $thread): ?>
                <?php
                $displayNumbers = thread_display_number_map($thread);
                $replyTree = build_reply_tree($thread['replies'] ?? []);
                $previewIndex = 0;
                $replyRender = static function (array $reply, int $depth = 0) use (&$replyRender, &$previewIndex, $boardKey, $thread, $displayNumbers, $initialPreviewCount): void {
                    $currentIndex = $previewIndex++;
                    $isCollapsed = $currentIndex >= $initialPreviewCount;
                    $depthClass = 'reply-depth-' . min($depth, 2);
                    ?>
                    <article class="reply-card glass-card board-reply-card <?= e($depthClass) ?><?= $isCollapsed ? ' is-collapsed' : '' ?>" id="post-<?= e((string) $reply['id']) ?>" data-preview-item>
                        <div class="thread-meta">
                            <p class="thread-meta-line">
                                <span class="meta-left">
                                    <strong><?= e($reply['name']) ?></strong><?= member_badge_html($reply) ?>
                                    <span>No.<?= e((string) ($displayNumbers[$reply['id']] ?? $reply['id'])) ?></span>
                                    <span><?= e(render_time($reply['created_at'])) ?></span>
                                </span>
                            </p>
                        </div>
                        <?php if (!empty($reply['parent_reply_id'])): ?>
                            <p class="reply-parent-link">↳ 댓글 No.<?= e((string) ($displayNumbers[$reply['parent_reply_id']] ?? $reply['parent_reply_id'])) ?>에 연결된 답글</p>
                        <?php endif; ?>
                        <?php if (!empty($reply['comment'])): ?>
                            <div class="thread-body reply-body"><?= nl2br(e(text_preview((string) $reply['comment'], 220))) ?></div>
                        <?php endif; ?>
                        <?php render_post_actions($boardKey, (string) $thread['id'], $reply, true, 'board'); ?>
                        <?php if (!empty($reply['children'])): ?>
                            <div class="nested-reply-list">
                                <?php foreach ($reply['children'] as $child): ?>
                                    <?php $replyRender($child, $depth + 1); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                    <?php
                };
                ?>
                <article class="thread-card glass-card" id="post-<?= e((string) $thread['id']) ?>">
                    <div class="thread-meta">
                        <p class="thread-subject"><?= e($thread['subject'] ?: '무제') ?></p>
                        <p class="thread-meta-line">
                            <span class="meta-left">
                                <strong><?= e($thread['name']) ?></strong><?= member_badge_html($thread) ?>
                                <span>No.<?= e((string) ($displayNumbers[$thread['id']] ?? '1')) ?></span>
                                <span><?= e(render_time($thread['created_at'])) ?></span>
                            </span>
                            <span class="meta-right"><span class="count-chip">댓글 <?= e((string) $thread['reply_count']) ?></span></span>
                        </p>
                    </div>

                    <a class="thread-link" href="<?= e(thread_url($boardKey, (string) $thread['id'])) ?>">
                        <div class="thread-preview">
                            <?php if (!empty($thread['image'])): ?>
                                <img class="thread-image" src="<?= e(public_upload_url($thread['image'])) ?>" alt="thread image">
                            <?php endif; ?>
                            <div class="thread-text">
                                <?php if (!empty($thread['comment'])): ?>
                                    <p><?= nl2br(e(text_preview((string) $thread['comment'], 500))) ?></p>
                                <?php else: ?>
                                    <p class="muted">이미지 중심 스레드</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>

                    <?php render_post_actions($boardKey, (string) $thread['id'], $thread, false, 'board'); ?>

                    <div class="reply-meta">
                        <span class="thread-open-link-wrap"><a class="thread-open-link" href="<?= e(thread_url($boardKey, (string) $thread['id'])) ?>">스레드 보기</a></span>
                    </div>

                    <?php if ($replyTree !== []): ?>
                        <div class="reply-preview-list board-reply-preview-list" data-preview-list>
                            <?php foreach ($replyTree as $reply): ?>
                                <?php $replyRender($reply, 0); ?>
                            <?php endforeach; ?>
                            <?php if ($thread['reply_count'] > $initialPreviewCount): ?>
                                <button type="button" class="load-more-chip" data-preview-more data-preview-batch="<?= e((string) $previewBatchCount) ?>">댓글 더보기</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </main>
    </div>
<?php render_mobile_reply_dock($boardKey); ?>
</div>
</body>
</html>
