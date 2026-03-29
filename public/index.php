<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$config = app_config();
$flash = flash_get();
$boards = $config['boards'];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="theme-home">
<div class="page-shell">
    <header class="hero glass-card">
        <div>
            <p class="eyebrow">Cute imageboard for Render test</p>
            <h1><?= e($config['app_name']) ?></h1>
            <p class="hero-copy">ptchan 계열의 가벼운 이미지보드 감성을 참고해, 더 부드럽고 귀여운 색감으로 다시 만든 테스트용 빌드입니다.</p>
        </div>
        <div class="hero-badges">
            <span>모바일 대응</span>
            <span>JSON 저장</span>
            <span>DB 전환 준비</span>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="notice-card">
        <strong>테스트 환경 안내</strong>
        <p>현재 빌드는 Render 테스트용이라 글과 업로드 이미지를 서버 로컬 파일에 저장합니다. Render 기본 웹 서비스 파일시스템은 재배포/재시작 시 초기화될 수 있으므로, 실제 서비스 단계에서는 PostgreSQL + 외부 파일 저장소(S3 호환)를 연결하는 구조로 바꾸는 것이 맞습니다.</p>
    </section>

    <section class="board-grid">
        <?php foreach ($boards as $board): ?>
            <a class="board-card accent-<?= e($board['accent']) ?>" href="/board.php?board=<?= e($board['key']) ?>">
                <div class="board-card-top">
                    <span class="board-slug">/<?= e($board['key']) ?>/</span>
                    <span class="board-go">enter →</span>
                </div>
                <h2><?= e($board['title']) ?></h2>
                <p><?= e($board['subtitle']) ?></p>
            </a>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
