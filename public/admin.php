<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$gate = query_value('admin_key');
require_admin_gate_or_404($gate);

$adminConfig = admin_config();
$basePath = admin_gate_path();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $action = posted_value('admin_action');

    if ($action === 'login') {
        $username = posted_value('username');
        $password = posted_value('password');
        if ($username === (string) ($adminConfig['username'] ?? '') && $password === (string) ($adminConfig['password'] ?? '')) {
            admin_login($username);
            flash_set('success', '관리자 모드에 접속했습니다.');
        } else {
            flash_set('error', '관리자 계정 정보가 올바르지 않습니다.');
        }
        redirect($basePath);
    }

    if ($action === 'logout') {
        admin_logout();
        flash_set('success', '관리자 로그아웃 되었습니다.');
        redirect($basePath);
    }

    if (!admin_session()) {
        http_response_code(403);
        exit('Forbidden');
    }

    $boardKey = posted_value('board');
    $threadId = posted_value('thread_id');
    $replyId = posted_value('reply_id');
    $moderateAction = posted_value('moderate_action');

    $ok = false;
    if ($replyId !== '') {
        $ok = admin_reply_action($boardKey, $threadId, $replyId, $moderateAction);
    } else {
        $ok = admin_thread_action($boardKey, $threadId, $moderateAction);
    }

    flash_set($ok ? 'success' : 'error', $ok ? '관리자 작업이 처리되었습니다.' : '관리자 작업 처리에 실패했습니다.');
    redirect($basePath);
}

$admin = admin_session();
$flash = flash_get();
$stats = $admin ? admin_dashboard_stats() : [];
$recentPosts = $admin ? admin_recent_posts(120) : [];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>관리자 모드 - <?= e(app_config()['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="theme-board accent-strawberry">
<div class="page-shell compact-shell admin-shell">
    <header class="topbar glass-card admin-topbar">
        <div>
            <p class="eyebrow">Admin</p>
            <h1>관리자 모드</h1>
            <p>잠금, 상단고정, 이미지 제거, 게시물 삭제를 여기서 처리할 수 있습니다.</p>
        </div>
        <div class="topbar-actions">
            <a class="header-chip-link" href="/">홈으로</a>
            <?php if ($admin): ?>
                <form action="<?= e($basePath) ?>" method="post" class="inline-form">
                    <input type="hidden" name="admin_action" value="logout">
                    <button class="button-secondary" type="submit">로그아웃</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!$admin): ?>
        <section class="panel glass-card admin-login-panel">
            <div class="panel-header">
                <h2>관리자 로그인</h2>
                <p>이 페이지는 공개 메뉴에 노출되지 않습니다.</p>
            </div>
            <form action="<?= e($basePath) ?>" method="post" class="stack-form">
                <input type="hidden" name="admin_action" value="login">
                <label><span>아이디</span><input type="text" name="username" required></label>
                <label><span>비밀번호</span><input type="password" name="password" required></label>
                <button class="button-danger" type="submit">관리자 로그인</button>
            </form>
        </section>
    <?php else: ?>
        <section class="admin-stats-grid">
            <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['threads']) ?></strong><span>스레드</span></article>
            <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['replies']) ?></strong><span>댓글/답글</span></article>
            <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['images']) ?></strong><span>이미지</span></article>
            <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['users']) ?></strong><span>회원</span></article>
            <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['sticky']) ?></strong><span>상단고정</span></article>
            <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['locked']) ?></strong><span>잠긴 스레드</span></article>
        </section>

        <section class="panel glass-card admin-moderation-panel">
            <div class="panel-header">
                <h2>최근 게시물 관리</h2>
                <p>이미지보드에서 자주 쓰는 상단고정, 잠금, 이미지 제거, 삭제를 지원합니다.</p>
            </div>
            <div class="admin-post-list">
                <?php foreach ($recentPosts as $item): ?>
                    <article class="admin-post-card glass-card">
                        <div class="admin-post-head">
                            <div>
                                <p class="eyebrow">/<?= e($item['board']) ?>/ <?= e($item['type'] === 'thread' ? 'THREAD' : 'REPLY') ?></p>
                                <h3><?= e($item['type'] === 'thread' ? ($item['subject'] ?: '무제') : ($item['name'] ?: '익명')) ?></h3>
                                <p class="muted">
                                    <?= e($item['type'] === 'thread' ? ('스레드 #' . $item['thread_id']) : ('댓글 #' . $item['reply_id'] . ' · 스레드 #' . $item['thread_id'])) ?>
                                    · <?= e(render_time($item['created_at'])) ?>
                                </p>
                            </div>
                            <div class="admin-post-head-links">
                                <a class="header-chip-link" href="<?= e(thread_url($item['board'], $item['thread_id'])) ?>" target="_blank" rel="noreferrer">열기</a>
                                <?php if ($item['sticky']): ?><span class="count-chip">상단고정</span><?php endif; ?>
                                <?php if ($item['locked']): ?><span class="count-chip">잠금</span><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($item['comment'] !== ''): ?>
                            <p class="admin-post-preview"><?= e(text_preview($item['comment'], 180)) ?></p>
                        <?php endif; ?>
                        <div class="admin-action-row">
                            <?php if ($item['type'] === 'thread'): ?>
                                <form action="<?= e($basePath) ?>" method="post" class="inline-form">
                                    <input type="hidden" name="board" value="<?= e($item['board']) ?>">
                                    <input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>">
                                    <input type="hidden" name="moderate_action" value="toggle_sticky">
                                    <button class="button-secondary" type="submit"><?= $item['sticky'] ? '상단고정 해제' : '상단고정' ?></button>
                                </form>
                                <form action="<?= e($basePath) ?>" method="post" class="inline-form">
                                    <input type="hidden" name="board" value="<?= e($item['board']) ?>">
                                    <input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>">
                                    <input type="hidden" name="moderate_action" value="toggle_lock">
                                    <button class="button-secondary" type="submit"><?= $item['locked'] ? '잠금 해제' : '스레드 잠금' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($item['has_image']): ?>
                                <form action="<?= e($basePath) ?>" method="post" class="inline-form" onsubmit="return confirm('이미지를 제거할까요?');">
                                    <input type="hidden" name="board" value="<?= e($item['board']) ?>">
                                    <input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>">
                                    <?php if ($item['reply_id'] !== ''): ?><input type="hidden" name="reply_id" value="<?= e($item['reply_id']) ?>"><?php endif; ?>
                                    <input type="hidden" name="moderate_action" value="delete_image">
                                    <button class="button-secondary" type="submit">이미지 제거</button>
                                </form>
                            <?php endif; ?>
                            <form action="<?= e($basePath) ?>" method="post" class="inline-form" onsubmit="return confirm('정말 삭제할까요?');">
                                <input type="hidden" name="board" value="<?= e($item['board']) ?>">
                                <input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>">
                                <?php if ($item['reply_id'] !== ''): ?><input type="hidden" name="reply_id" value="<?= e($item['reply_id']) ?>"><?php endif; ?>
                                <input type="hidden" name="moderate_action" value="delete">
                                <button class="button-danger" type="submit"><?= $item['type'] === 'thread' ? '스레드 삭제' : '댓글 삭제' ?></button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
