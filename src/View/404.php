<?php $config = app_config(); ?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="theme-home">
<div class="page-shell compact-shell">
    <section class="panel glass-card">
        <p class="eyebrow">404</p>
        <h1>페이지를 찾지 못했어요.</h1>
        <p>잘못된 보드 주소이거나 삭제된 스레드입니다.</p>
        <a class="button-primary" href="/">홈으로 돌아가기</a>
    </section>
</div>
</body>
</html>
