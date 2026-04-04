<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$config = app_config();
$boards = $config['boards'];
$auth = auth_user();
$flash = flash_get();
$filters = [
    'q' => query_value('q'),
    'board' => query_value('board') ?: 'all',
    'field' => query_value('field') ?: 'all',
    'username' => query_value('username'),
    'post_id' => query_value('post_id'),
    'sort' => query_value('sort') ?: 'latest',
];
$hasSearch = $filters['q'] !== '' || $filters['username'] !== '' || $filters['post_id'] !== '';
$results = $hasSearch ? repository()->search($filters) : [];
$selectedBoardKey = ($filters['board'] !== 'all' && isset($boards[$filters['board']])) ? $filters['board'] : '';
$menuCurrentPath = $selectedBoardKey !== '' ? board_url($selectedBoardKey) : '/search.php';
$pageHeading = $selectedBoardKey !== '' ? '/' . $selectedBoardKey . '/ 보드 검색' : '게시판 검색';
$pageDescription = $selectedBoardKey !== ''
    ? '/' . $selectedBoardKey . '/ 게시판 안에서 제목, 본문, 번호, 유저네임으로 검색할 수 있습니다.'
    : '전체 게시판 또는 특정 게시판에서 제목, 본문, 번호, 유저네임으로 검색할 수 있습니다.';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>검색 - <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="theme-home sidebar-layout">
<div class="page-shell page-shell-with-sidebar">
    <?php render_site_menu($menuCurrentPath); ?>

    <header class="topbar glass-card">
        <a class="home-link" href="/">← 홈</a>
        <div>
            <p class="eyebrow">Search</p>
            <h1><?= e($pageHeading) ?></h1>
            <p><?= e($pageDescription) ?></p>
        </div>
        <div class="auth-links">
            <?php if ($auth): ?>
                <span class="eyebrow">@<?= e($auth['username']) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="panel glass-card search-panel-wide">
        <form method="get" action="/search.php" class="search-form-grid">
            <label>
                <span>단어 검색</span>
                <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="제목 또는 본문 단어">
            </label>
            <label>
                <span>게시판</span>
                <select name="board">
                    <option value="all">전체 게시판</option>
                    <?php foreach ($boards as $boardKey => $board): ?>
                        <option value="<?= e($boardKey) ?>" <?= $filters['board'] === $boardKey ? 'selected' : '' ?>>/<?= e($boardKey) ?>/ <?= e($board['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>검색 대상</span>
                <select name="field">
                    <option value="all" <?= $filters['field'] === 'all' ? 'selected' : '' ?>>제목 + 본문</option>
                    <option value="title" <?= $filters['field'] === 'title' ? 'selected' : '' ?>>제목만</option>
                    <option value="comment" <?= $filters['field'] === 'comment' ? 'selected' : '' ?>>본문만</option>
                </select>
            </label>
            <label>
                <span>유저네임</span>
                <input type="text" name="username" value="<?= e($filters['username']) ?>" placeholder="작성자 이름">
            </label>
            <label>
                <span>스레드/댓글 번호</span>
                <input type="text" name="post_id" value="<?= e($filters['post_id']) ?>" placeholder="No. 번호 검색">
            </label>
            <label>
                <span>정렬</span>
                <select name="sort">
                    <option value="latest" <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>>최신순</option>
                    <option value="relevance" <?= $filters['sort'] === 'relevance' ? 'selected' : '' ?>>관련도순</option>
                </select>
            </label>
            <div class="search-form-actions">
                <button class="button-primary" type="submit">검색</button>
            </div>
        </form>
    </section>

    <section class="thread-list">
        <?php if ($hasSearch && $results === []): ?>
            <article class="panel glass-card empty-state">
                <h2>검색 결과가 없습니다.</h2>
                <p>단어, 유저네임, 번호를 바꿔서 다시 검색해보세요.</p>
            </article>
        <?php endif; ?>

        <?php foreach ($results as $result): ?>
            <article class="thread-card glass-card">
                <div class="thread-meta">
                    <p class="thread-subject"><?= e($result['subject'] ?: '무제') ?></p>
                    <p>
                        <strong><?= e($result['name']) ?></strong>
                        <span>/<?= e($result['board']) ?>/</span>
                        <span>No.<?= e($result['post_id']) ?></span>
                        <span><?= $result['is_reply'] ? '댓글' : '스레드' ?></span>
                        <span><?= e(render_time($result['created_at'])) ?></span>
                    </p>
                </div>
                <a class="thread-link" href="/thread.php?board=<?= e($result['board']) ?>&id=<?= e($result['thread_id']) ?>#post-<?= e($result['post_id']) ?>">
                    <div class="thread-preview">
                        <?php if (!empty($result['image'])): ?>
                            <img class="thread-image" src="<?= e(public_upload_url($result['image'])) ?>" alt="search image">
                        <?php endif; ?>
                        <div class="thread-text">
                            <p><?= nl2br(e(text_preview($result['comment'], 300))) ?></p>
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
