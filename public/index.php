<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$config = app_config();
$flash = flash_get();
$boards = $config['boards'];
$auth = auth_user();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="theme-home sidebar-layout">
<?php render_site_menu('/'); ?>
<div class="page-shell page-shell-with-sidebar">
    <header class="hero glass-card">
        <div>
            <p class="eyebrow">Cute imageboard for Render test</p>
            <h1><?= e($config['app_name']) ?></h1>
            <p class="hero-copy">회원가입, 로그인, 게시물 비밀번호 수정·삭제, 게시판/전체 검색까지 포함한 테스트용 빌드입니다.</p>
        </div>
        <div class="hero-badges">
            <a href="/search.php"><span>검색</span></a>
            <?php if ($auth): ?>
                <span>@<?= e($auth['username']) ?></span>
                <form action="/auth.php" method="post" class="inline-form"><input type="hidden" name="action" value="logout"><button class="button-secondary hero-auth-button" type="submit">로그아웃</button></form>
            <?php else: ?>
                <a href="/login.php"><span>로그인</span></a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="notice-card">
        <strong>테스트 환경 안내</strong>
        <p>현재 빌드는 Render 테스트용이라 글, 회원 정보, 업로드 이미지를 서버 로컬 JSON/파일에 저장합니다. 실제 서비스 단계에서는 PostgreSQL + 외부 파일 저장소(S3 호환)를 쓰는 방향이 가장 안전합니다.</p>
    </section>

    <section class="board-grid">
        <?php foreach ($boards as $boardKey => $board): ?>
            <a class="board-card accent-<?= e($board['accent']) ?>" href="/board.php?board=<?= e($boardKey) ?>">
                <div class="board-card-top">
                    <span class="board-slug">/<?= e($boardKey) ?>/</span>
                    <span class="board-go">Enter</span>
                </div>
                <div>
                    <h2><?= e($board['title']) ?></h2>
                    <p><?= e($board['subtitle']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
