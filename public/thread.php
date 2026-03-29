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
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($thread['subject'] ?: '무제') ?> - /<?= e($boardKey) ?>/</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="theme-thread accent-<?= e($board['accent']) ?>">
<div class="page-shell">
    <header class="topbar glass-card">
        <a class="home-link" href="/board.php?board=<?= e($boardKey) ?>">← /<?= e($boardKey) ?>/ 돌아가기</a>
        <div>
            <p class="eyebrow">Thread</p>
            <h1><?= e($thread['subject'] ?: '무제') ?></h1>
            <p><?= e($board['subtitle']) ?></p>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <main class="thread-page-layout">
        <section class="thread-card glass-card thread-detail">
            <div class="thread-meta">
                <p class="thread-subject"><?= e($thread['subject'] ?: '무제') ?></p>
                <p>
                    <strong><?= e($thread['name']) ?></strong>
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
        </section>

        <aside class="panel glass-card compose-panel sticky-panel">
            <div class="panel-header">
                <h2>답글 작성</h2>
                <p>내용이나 이미지를 넣어주세요.</p>
            </div>
            <form action="/post.php?board=<?= e($boardKey) ?>&thread=<?= e($thread['id']) ?>" method="post" enctype="multipart/form-data" class="stack-form">
                <label>
                    <span>이름</span>
                    <input type="text" name="name" maxlength="30" placeholder="익명">
                </label>
                <label>
                    <span>내용</span>
                    <textarea name="comment" rows="6" maxlength="5000" placeholder=">><?= e($thread['id']) ?> 에 답글 달기"></textarea>
                </label>
                <label>
                    <span>이미지</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                </label>
                <button class="button-primary" type="submit">답글 등록</button>
            </form>
        </aside>
    </main>

    <section class="reply-list">
        <?php foreach ($thread['replies'] as $reply): ?>
            <article class="reply-card glass-card">
                <div class="thread-meta">
                    <p>
                        <strong><?= e($reply['name']) ?></strong>
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
            </article>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
