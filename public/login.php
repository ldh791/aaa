<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
$config = app_config();
$flash = flash_get();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>로그인 - <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="theme-home">
<div class="page-shell compact-shell">
    <?php render_site_menu('/login.php'); ?>

    <section class="panel glass-card auth-card">
        <p class="eyebrow">Login</p>
        <h1>로그인</h1>
        <p class="muted">가입한 유저네임과 비밀번호로 로그인합니다.</p>
        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <form action="/auth.php" method="post" class="stack-form">
            <input type="hidden" name="action" value="login">
            <label><span>유저네임</span><input type="text" name="username" maxlength="20" required></label>
            <label><span>비밀번호</span><input type="password" name="password" maxlength="100" required></label>
            <button class="button-primary" type="submit">로그인</button>
        </form>
        <p class="muted helper-links"><a href="/register.php">회원가입으로 이동</a> · <a href="/">홈으로</a></p>
    </section>
</div>
</body>
</html>
