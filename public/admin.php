<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$gate = query_value('admin_key');
require_admin_gate_or_404($gate);

$adminConfig = admin_config();
$basePath = admin_gate_path();
$tab = query_value('tab') ?: 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $redirectTab = posted_value('redirect_tab') ?: 'dashboard';
    $redirectPath = $basePath . '?tab=' . rawurlencode($redirectTab);

    $boardKey = posted_value('board');
    $threadId = posted_value('thread_id');
    $replyId = posted_value('reply_id');
    $moderateAction = posted_value('moderate_action');

    $ok = false;
    switch ($action) {
        case 'moderate':
            if ($replyId !== '') {
                $ok = admin_reply_action($boardKey, $threadId, $replyId, $moderateAction);
            } elseif ($moderateAction === 'move') {
                $ok = move_thread_to_board($boardKey, $threadId, posted_value('target_board'));
            } else {
                $ok = admin_thread_action($boardKey, $threadId, $moderateAction);
            }
            flash_set($ok ? 'success' : 'error', $ok ? '관리자 작업이 처리되었습니다.' : '관리자 작업 처리에 실패했습니다.');
            break;
        case 'report_status':
            $ok = update_report_status(posted_value('report_id'), posted_value('status'));
            flash_set($ok ? 'success' : 'error', $ok ? '신고 상태를 변경했습니다.' : '신고 상태 변경에 실패했습니다.');
            break;
        case 'save_filters':
            save_filters([
                'blocked_words' => preg_split('/\r\n|\r|\n/', posted_value('blocked_words')) ?: [],
                'blocked_names' => preg_split('/\r\n|\r|\n/', posted_value('blocked_names')) ?: [],
                'blocked_links' => preg_split('/\r\n|\r|\n/', posted_value('blocked_links')) ?: [],
                'require_approval_words' => preg_split('/\r\n|\r|\n/', posted_value('require_approval_words')) ?: [],
                'blocked_image_hashes' => preg_split('/\r\n|\r|\n/', posted_value('blocked_image_hashes')) ?: [],
            ]);
            flash_set('success', '필터 설정을 저장했습니다.');
            $ok = true;
            break;
        case 'add_ban':
            $bans = active_bans();
            $bans[] = [
                'id' => date('YmdHis') . bin2hex(random_bytes(2)),
                'type' => posted_value('ban_type'),
                'value' => posted_value('ban_value'),
                'reason' => posted_value('ban_reason'),
                'expires_at' => posted_value('ban_expires_at'),
                'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
            save_bans($bans);
            flash_set('success', '차단 규칙을 추가했습니다.');
            $ok = true;
            break;
        case 'remove_ban':
            $bans = array_values(array_filter(active_bans(), static fn(array $ban): bool => (string) ($ban['id'] ?? '') !== posted_value('ban_id')));
            save_bans($bans);
            flash_set('success', '차단 규칙을 삭제했습니다.');
            $ok = true;
            break;
        case 'toggle_user':
            $ok = update_user_suspension(posted_value('user_id'), posted_value('suspended') === '1');
            flash_set($ok ? 'success' : 'error', $ok ? '회원 상태를 변경했습니다.' : '회원 상태 변경에 실패했습니다.');
            break;
        case 'save_settings':
            $settings = site_settings();
            $settings['site']['allow_anonymous'] = posted_value('allow_anonymous') === '1';
            $settings['site']['members_only_posting'] = posted_value('members_only_posting') === '1';
            $settings['site']['max_upload_mb'] = max(1, (int) posted_value('max_upload_mb'));
            $settings['site']['reply_depth_limit'] = max(1, (int) posted_value('reply_depth_limit'));
            $settings['site']['auto_lock_reply_count'] = max(0, (int) posted_value('auto_lock_reply_count'));
            save_site_settings($settings);
            flash_set('success', '사이트 설정을 저장했습니다.');
            $ok = true;
            break;
        case 'backup_create':
            backup_snapshot();
            flash_set('success', '백업 스냅샷을 생성했습니다.');
            $ok = true;
            break;
        case 'trash_restore':
            $trash = read_data_file('trash', []);
            $trashId = posted_value('trash_id');
            foreach ($trash as $idx => $item) {
                if ((string) ($item['id'] ?? '') !== $trashId) continue;
                if (($item['type'] ?? '') === 'thread') {
                    $threads = json_decode((string) file_get_contents(board_file_path((string) $item['board'])), true) ?: [];
                    $threads[] = $item['payload'];
                    write_board_threads_raw((string) $item['board'], $threads);
                } elseif (($item['type'] ?? '') === 'reply') {
                    $threads = json_decode((string) file_get_contents(board_file_path((string) $item['board'])), true) ?: [];
                    foreach ($threads as &$thread) {
                        if ((string) ($thread['id'] ?? '') === (string) $item['thread_id']) {
                            $thread['replies'][] = $item['payload'];
                            $thread['reply_count'] = count($thread['replies']);
                            break;
                        }
                    }
                    unset($thread);
                    write_board_threads_raw((string) $item['board'], $threads);
                }
                unset($trash[$idx]);
                write_data_file('trash', array_values($trash));
                admin_log('trash_restored', ['trash_id' => $trashId]);
                $ok = true;
                break;
            }
            flash_set($ok ? 'success' : 'error', $ok ? '휴지통 게시물을 복구했습니다.' : '휴지통 복구에 실패했습니다.');
            break;
        case 'archive_old':
            $count = archive_old_threads();
            flash_set('success', '오래된 스레드 아카이브 처리: ' . $count . '개');
            $ok = true;
            break;
    }
    redirect($redirectPath);
}

$admin = admin_session();
$flash = flash_get();
$stats = $admin ? admin_dashboard_stats() : [];
$recentPosts = $admin ? admin_recent_posts(120) : [];
$reports = $admin ? admin_reports() : [];
$filters = $admin ? active_filters() : [];
$bans = $admin ? active_bans() : [];
$users = $admin ? all_users_raw() : [];
$settings = $admin ? site_settings() : [];
$logs = $admin ? array_reverse(read_data_file('admin_logs', [])) : [];
$trash = $admin ? array_reverse(read_data_file('trash', [])) : [];
$backups = $admin ? backup_files() : [];
$menu = [
    'dashboard' => '대시보드',
    'reports' => '신고 관리',
    'moderation' => '게시물 관리',
    'members' => '회원 관리',
    'filters' => '금칙어/필터',
    'bans' => '차단 규칙',
    'settings' => '사이트 설정',
    'backup' => '백업/복구',
    'logs' => '관리 로그',
];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>관리자 모드 - <?= e(app_config()['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="theme-board accent-strawberry sidebar-layout admin-mode-body">
<div class="page-shell admin-page-shell admin-shell">
    <header class="topbar glass-card admin-topbar">
        <div>
            <p class="eyebrow">Admin</p>
            <h1>관리자 모드</h1>
            <p>테스트 계정은 현재 admin / admin 입니다. 운영 배포 전에는 반드시 변경하세요.</p>
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

    <?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

    <?php if (!$admin): ?>
        <section class="panel glass-card admin-login-panel">
            <div class="panel-header"><h2>관리자 로그인</h2><p>공개 메뉴에 노출되지 않는 관리자 전용 진입점입니다.</p></div>
            <form action="<?= e($basePath) ?>" method="post" class="stack-form">
                <input type="hidden" name="admin_action" value="login">
                <label><span>아이디</span><input type="text" name="username" required value="admin"></label>
                <label><span>비밀번호</span><input type="password" name="password" required value="admin"></label>
                <button class="button-danger" type="submit">관리자 로그인</button>
            </form>
        </section>
    <?php else: ?>
        <div class="admin-layout">
            <aside class="glass-card admin-nav-card">
                <div class="admin-nav-head">
                    <p class="eyebrow">관리 메뉴</p>
                    <strong>기능 이동</strong>
                    <p class="muted">자주 쓰는 관리자 기능을 한곳에 모았습니다.</p>
                </div>
                <nav class="admin-nav-list" aria-label="관리자 메뉴">
                    <?php foreach ($menu as $key => $label): ?>
                        <a class="admin-nav-link<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e($basePath . '?tab=' . $key) ?>">
                            <span class="admin-nav-dot"></span>
                            <span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <main class="admin-content-stack">
                <section class="glass-card admin-mobile-tabs">
                    <div class="admin-toolbar-head">
                        <p class="eyebrow">빠른 이동</p>
                        <strong>관리 화면을 선택하세요.</strong>
                    </div>
                    <nav class="admin-toolbar-nav" aria-label="관리자 탭">
                        <?php foreach ($menu as $key => $label): ?>
                            <a class="admin-toolbar-link<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e($basePath . '?tab=' . $key) ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </section>
                <?php if ($tab === 'dashboard'): ?>
                    <section class="admin-stats-grid">
                        <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['threads']) ?></strong><span>스레드</span></article>
                        <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['replies']) ?></strong><span>댓글/답글</span></article>
                        <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['images']) ?></strong><span>이미지</span></article>
                        <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['users']) ?></strong><span>회원</span></article>
                        <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['reports']) ?></strong><span>미처리 신고</span></article>
                        <article class="glass-card admin-stat-card"><strong><?= e((string) $stats['bans']) ?></strong><span>차단 규칙</span></article>
                    </section>
                    <section class="panel glass-card">
                        <div class="panel-header"><h2>운영 작업 바로가기</h2></div>
                        <div class="admin-action-row">
                            <form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="backup_create"><input type="hidden" name="redirect_tab" value="dashboard"><button class="button-secondary" type="submit">백업 생성</button></form>
                            <form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="archive_old"><input type="hidden" name="redirect_tab" value="dashboard"><button class="button-secondary" type="submit">오래된 스레드 아카이브</button></form>
                        </div>
                    </section>
                <?php elseif ($tab === 'reports'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>신고 접수 관리</h2><p>스레드/댓글/답글의 사용자 신고를 확인하고 처리합니다.</p></div>
                        <div class="admin-post-list">
                            <?php foreach ($reports as $report): ?>
                                <article class="admin-post-card glass-card">
                                    <div class="admin-post-head"><div><h3><?= e((string) $report['reason']) ?></h3><p class="muted">/<?= e((string) $report['board']) ?>/ · <?= e(render_time((string) $report['created_at'])) ?> · 상태 <?= e((string) $report['status']) ?></p></div></div>
                                    <?php if (!empty($report['detail'])): ?><p class="admin-post-preview"><?= e((string) $report['detail']) ?></p><?php endif; ?>
                                    <div class="admin-action-row">
                                        <?php foreach (['open' => '접수', 'reviewed' => '검토', 'closed' => '종결'] as $statusKey => $label): ?>
                                            <form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="report_status"><input type="hidden" name="redirect_tab" value="reports"><input type="hidden" name="report_id" value="<?= e((string) $report['id']) ?>"><input type="hidden" name="status" value="<?= e($statusKey) ?>"><button class="button-secondary" type="submit"><?= e($label) ?></button></form>
                                        <?php endforeach; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php elseif ($tab === 'moderation'): ?>
                    <section class="panel glass-card admin-moderation-panel">
                        <div class="panel-header"><h2>최근 게시물 관리</h2><p>잠금, 상단고정, 이동, 이미지 제거, 삭제를 한 곳에서 처리합니다.</p></div>
                        <div class="admin-post-list">
                            <?php foreach ($recentPosts as $item): ?>
                                <article class="admin-post-card glass-card">
                                    <div class="admin-post-head"><div><p class="eyebrow">/<?= e($item['board']) ?>/ <?= e($item['type'] === 'thread' ? 'THREAD' : 'REPLY') ?></p><h3><?= e($item['type'] === 'thread' ? ($item['subject'] ?: '무제') : ($item['name'] ?: '익명')) ?></h3><p class="muted"><?= e(render_time($item['created_at'])) ?></p></div><div class="admin-post-head-links"><a class="header-chip-link" href="<?= e(thread_url($item['board'], $item['thread_id'])) ?>" target="_blank" rel="noreferrer">열기</a></div></div>
                                    <?php if ($item['comment'] !== ''): ?><p class="admin-post-preview"><?= e(text_preview($item['comment'], 180)) ?></p><?php endif; ?>
                                    <div class="admin-action-row">
                                        <?php if ($item['type'] === 'thread'): ?>
                                            <?php foreach (['toggle_sticky' => ($item['sticky'] ? '고정 해제' : '상단 고정'), 'toggle_lock' => ($item['locked'] ? '잠금 해제' : '스레드 잠금'), 'archive' => '아카이브'] as $modAction => $label): ?>
                                                <form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="moderate"><input type="hidden" name="redirect_tab" value="moderation"><input type="hidden" name="board" value="<?= e($item['board']) ?>"><input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>"><input type="hidden" name="moderate_action" value="<?= e($modAction) ?>"><button class="button-secondary" type="submit"><?= e($label) ?></button></form>
                                            <?php endforeach; ?>
                                            <form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="moderate"><input type="hidden" name="redirect_tab" value="moderation"><input type="hidden" name="board" value="<?= e($item['board']) ?>"><input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>"><input type="hidden" name="moderate_action" value="move"><select name="target_board"><?php foreach (app_config()['boards'] as $key => $boardInfo): ?><option value="<?= e((string) $key) ?>"><?= e('/' . $key . '/ ' . $boardInfo['title']) ?></option><?php endforeach; ?></select><button class="button-secondary" type="submit">이동</button></form>
                                        <?php endif; ?>
                                        <?php if ($item['has_image']): ?><form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="moderate"><input type="hidden" name="redirect_tab" value="moderation"><input type="hidden" name="board" value="<?= e($item['board']) ?>"><input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>"><?php if ($item['reply_id'] !== ''): ?><input type="hidden" name="reply_id" value="<?= e($item['reply_id']) ?>"><?php endif; ?><input type="hidden" name="moderate_action" value="delete_image"><button class="button-secondary" type="submit">이미지 제거</button></form><?php endif; ?>
                                        <form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="moderate"><input type="hidden" name="redirect_tab" value="moderation"><input type="hidden" name="board" value="<?= e($item['board']) ?>"><input type="hidden" name="thread_id" value="<?= e($item['thread_id']) ?>"><?php if ($item['reply_id'] !== ''): ?><input type="hidden" name="reply_id" value="<?= e($item['reply_id']) ?>"><?php endif; ?><input type="hidden" name="moderate_action" value="delete"><button class="button-danger" type="submit">삭제</button></form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php elseif ($tab === 'members'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>회원 관리</h2></div><div class="admin-post-list"><?php foreach ($users as $user): ?><article class="admin-post-card glass-card"><div class="admin-post-head"><div><h3>@<?= e((string) $user['username']) ?></h3><p class="muted">가입일 <?= e(render_time((string) $user['created_at'])) ?></p></div><span class="count-chip"><?= !empty($user['suspended']) ? '정지됨' : '정상' ?></span></div><div class="admin-action-row"><form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="toggle_user"><input type="hidden" name="redirect_tab" value="members"><input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>"><input type="hidden" name="suspended" value="<?= !empty($user['suspended']) ? '0' : '1' ?>"><button class="<?= !empty($user['suspended']) ? 'button-secondary' : 'button-danger' ?>" type="submit"><?= !empty($user['suspended']) ? '정지 해제' : '회원 정지' ?></button></form></div></article><?php endforeach; ?></div></section>
                <?php elseif ($tab === 'filters'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>금칙어 / 자동 필터</h2><p>줄바꿈으로 여러 값을 입력할 수 있습니다.</p></div><form action="<?= e($basePath) ?>" method="post" class="stack-form"><input type="hidden" name="admin_action" value="save_filters"><input type="hidden" name="redirect_tab" value="filters"><label><span>금칙어</span><textarea name="blocked_words" rows="5"><?= e(implode("\n", $filters['blocked_words'] ?? [])) ?></textarea></label><label><span>차단 이름</span><textarea name="blocked_names" rows="4"><?= e(implode("\n", $filters['blocked_names'] ?? [])) ?></textarea></label><label><span>차단 링크</span><textarea name="blocked_links" rows="4"><?= e(implode("\n", $filters['blocked_links'] ?? [])) ?></textarea></label><label><span>승인 대기 키워드</span><textarea name="require_approval_words" rows="4"><?= e(implode("\n", $filters['require_approval_words'] ?? [])) ?></textarea></label><label><span>차단 이미지 해시</span><textarea name="blocked_image_hashes" rows="4"><?= e(implode("\n", $filters['blocked_image_hashes'] ?? [])) ?></textarea></label><button class="button-danger" type="submit">필터 저장</button></form></section>
                <?php elseif ($tab === 'bans'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>차단 규칙</h2></div><form action="<?= e($basePath) ?>" method="post" class="search-form-grid"><input type="hidden" name="admin_action" value="add_ban"><input type="hidden" name="redirect_tab" value="bans"><label><span>차단 종류</span><select name="ban_type"><option value="ip">IP</option><option value="name">닉네임</option><option value="user">회원 ID</option></select></label><label><span>값</span><input type="text" name="ban_value" required></label><label><span>사유</span><input type="text" name="ban_reason"></label><label><span>만료일(선택)</span><input type="text" name="ban_expires_at" placeholder="2026-12-31T23:59:00+09:00"></label><button class="button-danger" type="submit">차단 추가</button></form><div class="admin-post-list"><?php foreach ($bans as $ban): ?><article class="admin-post-card glass-card"><div class="admin-post-head"><div><h3><?= e((string) $ban['type']) ?> · <?= e((string) $ban['value']) ?></h3><p class="muted"><?= e((string) ($ban['reason'] ?? '')) ?></p></div></div><div class="admin-action-row"><form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="remove_ban"><input type="hidden" name="redirect_tab" value="bans"><input type="hidden" name="ban_id" value="<?= e((string) $ban['id']) ?>"><button class="button-secondary" type="submit">삭제</button></form></div></article><?php endforeach; ?></div></section>
                <?php elseif ($tab === 'settings'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>사이트 설정</h2></div><form action="<?= e($basePath) ?>" method="post" class="stack-form"><input type="hidden" name="admin_action" value="save_settings"><input type="hidden" name="redirect_tab" value="settings"><label><span>익명 글쓰기 허용</span><select name="allow_anonymous"><option value="1"<?= !empty($settings['site']['allow_anonymous']) ? ' selected' : '' ?>>허용</option><option value="0"<?= empty($settings['site']['allow_anonymous']) ? ' selected' : '' ?>>비허용</option></select></label><label><span>회원 전용 글쓰기</span><select name="members_only_posting"><option value="0"<?= empty($settings['site']['members_only_posting']) ? ' selected' : '' ?>>끄기</option><option value="1"<?= !empty($settings['site']['members_only_posting']) ? ' selected' : '' ?>>켜기</option></select></label><label><span>최대 업로드 MB</span><input type="text" name="max_upload_mb" value="<?= e((string) ($settings['site']['max_upload_mb'] ?? app_config()['max_upload_mb'])) ?>"></label><label><span>답글 최대 깊이</span><input type="text" name="reply_depth_limit" value="<?= e((string) ($settings['site']['reply_depth_limit'] ?? 100)) ?>"></label><label><span>자동 잠금 reply 수</span><input type="text" name="auto_lock_reply_count" value="<?= e((string) ($settings['site']['auto_lock_reply_count'] ?? 250)) ?>"></label><button class="button-danger" type="submit">설정 저장</button></form></section>
                <?php elseif ($tab === 'backup'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>백업 / 복구 / 휴지통</h2></div><div class="admin-action-row"><form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="backup_create"><input type="hidden" name="redirect_tab" value="backup"><button class="button-danger" type="submit">새 백업 생성</button></form></div><div class="admin-post-list"><?php foreach ($backups as $file): ?><article class="admin-post-card glass-card"><div class="admin-post-head"><div><h3><?= e(basename($file)) ?></h3><p class="muted">백업 파일</p></div><a class="header-chip-link" href="<?= e('/admin_backup.php?admin_key=' . rawurlencode((string) (admin_config()['gate_key'] ?? '')) . '&file=' . rawurlencode(basename($file))) ?>">다운로드</a></div></article><?php endforeach; ?><?php foreach ($trash as $item): ?><article class="admin-post-card glass-card"><div class="admin-post-head"><div><h3>휴지통 <?= e((string) ($item['type'] ?? 'item')) ?></h3><p class="muted"><?= e((string) ($item['board'] ?? '')) ?> · <?= e(render_time((string) ($item['deleted_at'] ?? ''))) ?></p></div></div><div class="admin-action-row"><form action="<?= e($basePath) ?>" method="post" class="inline-form"><input type="hidden" name="admin_action" value="trash_restore"><input type="hidden" name="redirect_tab" value="backup"><input type="hidden" name="trash_id" value="<?= e((string) ($item['id'] ?? '')) ?>"><button class="button-secondary" type="submit">복구</button></form></div></article><?php endforeach; ?></div></section>
                <?php elseif ($tab === 'logs'): ?>
                    <section class="panel glass-card"><div class="panel-header"><h2>관리자 액션 로그</h2></div><div class="admin-post-list"><?php foreach ($logs as $log): ?><article class="admin-post-card glass-card"><div class="admin-post-head"><div><h3><?= e((string) $log['action']) ?></h3><p class="muted"><?= e(render_time((string) $log['created_at'])) ?> · <?= e((string) ($log['admin'] ?? 'system')) ?></p></div></div><pre class="admin-log-preview"><?= e(json_encode($log['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></article><?php endforeach; ?></div></section>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
