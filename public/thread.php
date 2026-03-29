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
$config = app_config();
$auth = auth_user();
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
<body class="theme-thread accent-<?= e($board['accent']) ?>">
<div class="page-shell">
    <?php render_site_menu('/thread.php'); ?>

    <header class="topbar glass-card">
        <a class="home-link" href="/board.php?board=<?= e($boardKey) ?>">← /<?= e($boardKey) ?>/ 돌아가기</a>
        <div>
            <p class="eyebrow">Thread</p>
            <h1><?= e($thread['subject'] ?: '무제') ?></h1>
            <p><?= e($board['subtitle']) ?></p>
        </div>
        <div class="topbar-actions">
            <a class="button-secondary" href="/search.php?board=<?= e($boardKey) ?>&post_id=<?= e($thread['id']) ?>">번호로 찾기</a>
            <button type="button" class="button-secondary mobile-toggle" data-toggle-form>답글 달기</button>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <main class="thread-page-layout">
        <section class="thread-card glass-card thread-detail" id="post-<?= e($thread['id']) ?>">
            <div class="thread-meta">
                <p class="thread-subject"><?= e($thread['subject'] ?: '무제') ?></p>
                <p>
                    <?= render_author_html($thread) ?>
                    <span>No.<?= e($thread['id']) ?></span>
                    <span><?= e(render_time($thread['created_at'])) ?></span>
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

            <div class="post-actions-bar">
                <button type="button" class="button-secondary post-action-button" data-toggle-target="thread-edit-<?= e($thread['id']) ?>">수정</button>
                <button type="button" class="button-secondary post-action-button danger-lite" data-toggle-target="thread-delete-<?= e($thread['id']) ?>">삭제</button>
            </div>

            <div class="manage-stack">
                <section id="thread-edit-<?= e($thread['id']) ?>" class="mini-manage-form is-collapsed" data-toggle-panel>
                    <h3>스레드 수정</h3>
                    <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($thread['id']) ?>" method="post" class="stack-form compact-form">
                        <input type="hidden" name="manage_action" value="edit">
                        <label><span>이름</span><input type="text" name="name" maxlength="30" value="<?= e($thread['name']) ?>"></label>
                        <label><span>제목</span><input type="text" name="subject" maxlength="80" value="<?= e($thread['subject']) ?>"></label>
                        <label><span>내용</span><textarea name="comment" rows="5" maxlength="5000"><?= e($thread['comment']) ?></textarea></label>
                        <label><span>현재 비밀번호</span><input type="password" name="post_password" required></label>
                        <label><span>새 비밀번호(선택)</span><input type="password" name="new_post_password"></label>
                        <button class="button-secondary" type="submit">스레드 수정</button>
                    </form>
                </section>

                <section id="thread-delete-<?= e($thread['id']) ?>" class="mini-manage-form danger-form is-collapsed" data-toggle-panel>
                    <h3>스레드 삭제</h3>
                    <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($thread['id']) ?>" method="post" class="stack-form compact-form" onsubmit="return confirm('스레드를 삭제할까요?');">
                        <input type="hidden" name="manage_action" value="delete">
                        <label><span>현재 비밀번호</span><input type="password" name="post_password" required></label>
                        <button class="button-danger" type="submit">스레드 삭제</button>
                    </form>
                </section>
            </div>
        </section>

        <aside class="panel glass-card compose-panel sticky-panel" data-form-panel>
            <div class="panel-header">
                <h2>답글 작성</h2>
                <p>내용이나 이미지를 넣고, 수정/삭제용 비밀번호도 입력해주세요.</p>
            </div>
            <form action="/post.php?board=<?= e($boardKey) ?>&thread=<?= e($thread['id']) ?>" method="post" enctype="multipart/form-data" class="stack-form">
                <label>
                    <span>이름</span>
                    <input type="text" name="name" maxlength="30" placeholder="익명" value="<?= e($auth['username'] ?? '') ?>">
                </label>
                <label>
                    <span>내용</span>
                    <textarea name="comment" rows="6" maxlength="5000" placeholder=">><?= e($thread['id']) ?> 에 답글 달기"></textarea>
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
        <?php foreach ($thread['replies'] as $reply): ?>
            <article class="reply-card glass-card" id="post-<?= e($reply['id']) ?>">
                <div class="thread-meta">
                    <p>
                        <?= render_author_html($reply) ?>
                        <span>No.<?= e($reply['id']) ?></span>
                        <span><?= e(render_time($reply['created_at'])) ?></span>
                    </p>
                </div>
                <?php if (!empty($reply['image'])): ?>
                    <a class="detail-image-link" href="<?= e(public_upload_url($reply['image'])) ?>" target="_blank" rel="noreferrer">
                        <img class="thread-image reply-image" src="<?= e(public_upload_url($reply['image'])) ?>" alt="reply image">
                    </a>
                <?php endif; ?>
                <?php if (!empty($reply['comment'])): ?>
                    <div class="thread-body"><?= nl2br(e($reply['comment'])) ?></div>
                <?php endif; ?>

                <div class="post-actions-bar">
                    <button type="button" class="button-secondary post-action-button" data-toggle-target="reply-edit-<?= e($reply['id']) ?>">수정</button>
                    <button type="button" class="button-secondary post-action-button danger-lite" data-toggle-target="reply-delete-<?= e($reply['id']) ?>">삭제</button>
                </div>

                <div class="manage-stack">
                    <section id="reply-edit-<?= e($reply['id']) ?>" class="mini-manage-form is-collapsed" data-toggle-panel>
                        <h3>댓글 수정</h3>
                        <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($thread['id']) ?>&reply_id=<?= e($reply['id']) ?>" method="post" class="stack-form compact-form">
                            <input type="hidden" name="manage_action" value="edit">
                            <label><span>이름</span><input type="text" name="name" maxlength="30" value="<?= e($reply['name']) ?>"></label>
                            <label><span>내용</span><textarea name="comment" rows="4" maxlength="5000"><?= e($reply['comment']) ?></textarea></label>
                            <label><span>현재 비밀번호</span><input type="password" name="post_password" required></label>
                            <label><span>새 비밀번호(선택)</span><input type="password" name="new_post_password"></label>
                            <button class="button-secondary" type="submit">댓글 수정</button>
                        </form>
                    </section>
                    <section id="reply-delete-<?= e($reply['id']) ?>" class="mini-manage-form danger-form is-collapsed" data-toggle-panel>
                        <h3>댓글 삭제</h3>
                        <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($thread['id']) ?>&reply_id=<?= e($reply['id']) ?>" method="post" class="stack-form compact-form" onsubmit="return confirm('댓글을 삭제할까요?');">
                            <input type="hidden" name="manage_action" value="delete">
                            <label><span>현재 비밀번호</span><input type="password" name="post_password" required></label>
                            <button class="button-danger" type="submit">댓글 삭제</button>
                        </form>
                    </section>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
