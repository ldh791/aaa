<?php
declare(strict_types=1);

use App\Repository\JsonPostRepository;
use App\Repository\JsonUserRepository;
use App\Repository\PdoPostRepository;
use App\Repository\PostRepositoryInterface;
use App\Repository\UserRepositoryInterface;

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/app.php';
    }

    return $config;
}

function ensure_storage(array $config): void
{
    $paths = [
        $config['storage_path'],
        $config['storage_path'] . '/data',
        $config['storage_path'] . '/data/boards',
        $config['storage_path'] . '/logs',
        $config['upload_path'],
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    foreach (array_keys($config['boards']) as $boardKey) {
        $file = board_file_path($boardKey, $config);
        if (!is_file($file)) {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    $jsonFiles = [
        $config['storage_path'] . '/data/users.json' => [],
        $config['storage_path'] . '/data/reports.json' => [],
        $config['storage_path'] . '/data/bans.json' => [],
        $config['storage_path'] . '/data/filters.json' => [
            'blocked_words' => [],
            'blocked_names' => [],
            'blocked_links' => [],
            'require_approval_words' => [],
            'blocked_image_hashes' => [],
        ],
        $config['storage_path'] . '/data/settings.json' => [
            'site' => [
                'allow_anonymous' => true,
                'members_only_posting' => false,
                'max_upload_mb' => (int) ($config['max_upload_mb'] ?? 5),
                'reply_depth_limit' => 100,
                'auto_lock_reply_count' => 250,
            ],
            'boards' => [],
        ],
        $config['storage_path'] . '/data/admin_logs.json' => [],
        $config['storage_path'] . '/data/trash.json' => [],
        $config['storage_path'] . '/data/rate_limits.json' => [],
    ];

    foreach ($jsonFiles as $file => $defaultContent) {
        if (!is_file($file)) {
            file_put_contents($file, json_encode($defaultContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    $backupDir = $config['storage_path'] . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
    }
}

function board_file_path(string $boardKey, ?array $config = null): string
{
    $config ??= app_config();
    return $config['storage_path'] . '/data/boards/' . $boardKey . '.json';
}

function repository(): PostRepositoryInterface
{
    static $repository;
    if ($repository instanceof PostRepositoryInterface) {
        return $repository;
    }

    $config = app_config();

    if ($config['data_driver'] === 'pdo') {
        $repository = new PdoPostRepository($config);
        return $repository;
    }

    $repository = new JsonPostRepository($config);
    return $repository;
}

function user_repository(): UserRepositoryInterface
{
    static $repository;
    if ($repository instanceof UserRepositoryInterface) {
        return $repository;
    }

    $repository = new JsonUserRepository(app_config());
    return $repository;
}

function raw_repository_find_thread(string $boardKey, string $threadId): ?array
{
    $path = board_file_path($boardKey);
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return null;
    }
    foreach ($decoded as $thread) {
        if (($thread['id'] ?? '') === $threadId) {
            return $thread;
        }
    }
    return null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    $config = app_config();
    $base = $config['base_url'];
    $location = $base ? $base . $path : $path;
    header('Location: ' . $location);
    exit;
}

function board_or_404(string $boardKey): array
{
    $boards = app_config()['boards'];
    if (!isset($boards[$boardKey])) {
        http_response_code(404);
        include __DIR__ . '/../View/404.php';
        exit;
    }

    return $boards[$boardKey];
}

function posted_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function query_value(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

function flash_set(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function auth_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $auth = $_SESSION['auth'] ?? null;
    return is_array($auth) ? $auth : null;
}

function render_time(string $isoDate): string
{
    try {
        $date = new DateTimeImmutable($isoDate);
        return $date->format('Y-m-d H:i');
    } catch (Throwable) {
        return $isoDate;
    }
}

function max_upload_bytes(): int
{
    $config = app_config();
    $settings = site_settings();
    $mb = (int) (($settings['site']['max_upload_mb'] ?? $config['max_upload_mb']) ?: $config['max_upload_mb']);
    return $mb * 1024 * 1024;
}

function format_bytes_label(int $bytes): string
{
    return number_format($bytes / 1024 / 1024, 0) . 'MB';
}

function lower_text(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function text_limit(string $value, int $length): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length);
    }
    return substr($value, 0, $length);
}

function text_preview(string $value, int $length): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $length, '…', 'UTF-8');
    }
    if (strlen($value) <= $length) {
        return $value;
    }
    return substr($value, 0, max(0, $length - 3)) . '...';
}

function public_upload_url(string $fileName): string
{
    return '/uploads/' . rawurlencode($fileName);
}

function delete_upload_file(string $fileName): void
{
    if ($fileName === '') {
        return;
    }
    $path = app_config()['upload_path'] . '/' . basename($fileName);
    if (is_file($path)) {
        @unlink($path);
    }
}

function board_url(string $boardKey): string
{
    return '/' . rawurlencode($boardKey);
}

function thread_url(string $boardKey, string $threadId): string
{
    return '/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId);
}

function detect_board_key_from_request(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $trimmed = trim($path, '/');
    if ($trimmed === '' || str_contains($trimmed, '.php') || str_contains($trimmed, '/')) {
        return '';
    }

    $boards = app_config()['boards'];
    return isset($boards[$trimmed]) ? $trimmed : '';
}

function menu_links(): array
{
    $config = app_config();
    $links = [
        ['label' => '홈', 'href' => '/'],
        ['label' => '전체 검색', 'href' => '/search.php'],
    ];

    foreach ($config['boards'] as $boardKey => $board) {
        $links[] = [
            'label' => '/' . $boardKey . '/ ' . $board['title'],
            'href' => board_url((string) $boardKey),
        ];
    }

    return $links;
}

function current_path(): string
{
    return strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
}

function render_site_menu(?string $currentPath = null): void
{
    $currentPath ??= current_path();
    $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? $currentPath);
    $auth = auth_user();
    $links = menu_links();
    ?>
    <button type="button" class="hamburger-button" data-menu-toggle aria-expanded="true" aria-controls="site-sidebar">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-backdrop" data-menu-backdrop></div>
    <aside id="site-sidebar" class="site-sidebar glass-card" data-menu-panel>
        <div class="site-sidebar-head">
            <button type="button" class="sidebar-collapse-button" data-menu-collapse aria-expanded="true" aria-label="메뉴 접기">&lt;</button>
        </div>
        <div class="site-sidebar-auth site-sidebar-auth-top">
            <?php if ($auth): ?>
                <span class="site-sidebar-user">@<?= e($auth['username']) ?></span>
                <form action="/auth.php" method="post" class="sidebar-form-block">
                    <input type="hidden" name="action" value="logout">
                    <button class="button-secondary sidebar-action-button sidebar-login-button" type="submit">로그아웃</button>
                </form>
            <?php else: ?>
                <a class="button-secondary sidebar-action-button sidebar-login-button" href="/login.php">로그인</a>
            <?php endif; ?>
        </div>
        <nav class="site-sidebar-links">
            <?php foreach ($links as $link): ?>
                <?php $href = (string) $link['href']; $hrefPath = strtok($href, '?') ?: $href; $isCurrent = $currentUri === $href || ($hrefPath === $currentPath && strpos($href, '?') === false); ?>
                <a class="site-sidebar-link<?= $isCurrent ? ' is-active' : '' ?>" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <?php
}

function is_member_post(array $post): bool
{
    return !empty($post['user_id']) || !empty($post['username']);
}

function member_badge_html(array $post): string
{
    if (!is_member_post($post)) {
        return '';
    }

    return '<span class="member-badge" title="회원">✔</span>';
}

function reply_target_prefix(array $post): string
{
    return '>>' . ($post['id'] ?? '');
}



function edit_unlock_key(string $boardKey, string $threadId, string $postId): string
{
    return $boardKey . ':' . $threadId . ':' . $postId;
}

function unlock_post_edit(string $boardKey, string $threadId, string $postId): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['edit_unlocks'][edit_unlock_key($boardKey, $threadId, $postId)] = time();
}

function is_post_edit_unlocked(string $boardKey, string $threadId, string $postId): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $key = edit_unlock_key($boardKey, $threadId, $postId);
    return isset($_SESSION['edit_unlocks'][$key]);
}

function clear_post_edit_unlock(string $boardKey, string $threadId, string $postId): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION['edit_unlocks'][edit_unlock_key($boardKey, $threadId, $postId)]);
}

function flatten_reply_tree_for_preview(array $replyTree): array
{
    $flat = [];
    $walk = static function (array $nodes, int $depth = 0) use (&$walk, &$flat): void {
        foreach ($nodes as $node) {
            $item = $node;
            $children = $item['children'] ?? [];
            $item['preview_depth'] = $depth;
            unset($item['children']);
            $flat[] = $item;
            if (is_array($children) && $children !== []) {
                $walk($children, min($depth + 1, 1));
            }
        }
    };
    $walk($replyTree, 0);
    return $flat;
}

function board_preview_initial_count(): int
{
    return 5;
}

function board_preview_batch_count(): int
{
    return 5;
}

function thread_preview_initial_count(): int
{
    return 20;
}

function thread_preview_batch_count(): int
{
    return 10;
}

function global_display_number_index(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $posts = [];
    foreach (all_board_threads_raw() as $thread) {
        $threadId = (string) ($thread['id'] ?? '');
        if ($threadId !== '') {
            $posts[] = [
                'id' => $threadId,
                'created_at' => (string) ($thread['created_at'] ?? ''),
            ];
        }
        foreach (($thread['replies'] ?? []) as $reply) {
            $replyId = (string) ($reply['id'] ?? '');
            if ($replyId === '') {
                continue;
            }
            $posts[] = [
                'id' => $replyId,
                'created_at' => (string) ($reply['created_at'] ?? ''),
            ];
        }
    }

    usort($posts, static function (array $a, array $b): int {
        $timeCompare = strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        if ($timeCompare !== 0) {
            return $timeCompare;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $cache = [];
    $next = 1;
    foreach ($posts as $post) {
        $postId = (string) ($post['id'] ?? '');
        if ($postId === '' || isset($cache[$postId])) {
            continue;
        }
        $cache[$postId] = $next++;
    }

    return $cache;
}

function thread_display_number_map(array $thread): array
{
    $globalMap = global_display_number_index();
    $map = [];

    $threadId = (string) ($thread['id'] ?? '');
    if ($threadId !== '') {
        $map[$threadId] = $globalMap[$threadId] ?? $threadId;
    }

    foreach (($thread['replies'] ?? []) as $reply) {
        $replyId = (string) ($reply['id'] ?? '');
        if ($replyId === '') {
            continue;
        }
        $map[$replyId] = $globalMap[$replyId] ?? $replyId;
    }

    return $map;
}

function post_display_number(array $thread, string $postId): string
{
    static $cache = [];

    $threadId = (string) ($thread['id'] ?? '');
    $cacheKey = $threadId !== '' ? $threadId : spl_object_hash((object) $thread);

    if (!isset($cache[$cacheKey])) {
        $cache[$cacheKey] = thread_display_number_map($thread);
    }

    return (string) ($cache[$cacheKey][$postId] ?? $postId);
}

function board_manage_return_url(string $boardKey): string
{
    return board_url($boardKey);
}

function thread_return_url(string $boardKey, string $threadId): string
{
    return thread_url($boardKey, $threadId);
}

function render_inline_reply_form(string $boardKey, string $threadId, string $parentReplyId = '', string $label = '댓글 작성', string $context = 'thread'): void
{
    $auth = auth_user();
    if ($context === 'board') {
        $formId = $parentReplyId !== ''
            ? 'board-reply-form-' . $threadId . '-' . $parentReplyId
            : 'board-reply-form-' . $threadId;
        $returnTo = board_url($boardKey) . '#post-' . $threadId;
    } else {
        $formId = $parentReplyId !== '' ? 'reply-form-' . $parentReplyId : 'reply-form-thread';
        $returnTo = thread_url($boardKey, $threadId) . '#post-' . ($parentReplyId !== '' ? $parentReplyId : $threadId);
    }
    ?>
    <section id="<?= e($formId) ?>" class="inline-reply-form glass-card is-collapsed" data-toggle-panel>
        <div class="inline-reply-form-head">
            <strong><?= e($label) ?></strong>
            <button type="button" class="reply-context-clear" data-toggle-target="<?= e($formId) ?>" data-toggle-group="reply-forms">닫기</button>
        </div>
        <form action="/post.php?board=<?= e($boardKey) ?>&thread=<?= e($threadId) ?>" method="post" enctype="multipart/form-data" class="stack-form compact-form">
            <input type="hidden" name="parent_reply_id" value="<?= e($parentReplyId) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <label><span>이름</span><input type="text" name="name" maxlength="30" placeholder="익명" value="<?= e($auth['username'] ?? '') ?>" data-remember-name autocomplete="nickname"></label>
            <label><span>내용</span><textarea name="comment" rows="5" maxlength="5000" placeholder="댓글 내용을 입력하세요"></textarea></label>
            <label><span>이미지</span><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
            <label><span>게시물 비밀번호</span><input type="password" name="post_password" minlength="4" maxlength="100" placeholder="수정/삭제할 때 사용" required oninvalid="this.setCustomValidity('비밀번호를 입력해주세요.')" oninput="this.setCustomValidity('')" data-remember-password autocomplete="off"></label>
            <button class="button-primary" type="submit">등록</button>
        </form>
    </section>
    <?php
}

function render_mobile_reply_dock(string $boardKey): void
{
    $auth = auth_user();
    ?>
    <section id="mobile-reply-dock" class="mobile-reply-dock is-collapsed" data-mobile-reply-dock>
        <div class="mobile-reply-backdrop" data-mobile-reply-close></div>
        <div class="mobile-reply-sheet glass-card" role="dialog" aria-modal="true" aria-labelledby="mobile-reply-title">
            <div class="mobile-reply-head">
                <div class="mobile-reply-copy">
                    <p class="eyebrow">빠른 답글</p>
                    <strong id="mobile-reply-title" class="mobile-reply-title"><span class="mobile-reply-marker" data-mobile-reply-title>답글 작성</span></strong>
                    <p class="mobile-reply-meta" data-mobile-reply-meta>선택한 게시물 아래로 댓글이 등록됩니다.</p>
                </div>
                <button type="button" class="mobile-reply-close-button" data-mobile-reply-close aria-label="닫기">닫기</button>
            </div>
            <form action="/post.php" method="post" enctype="multipart/form-data" class="stack-form compact-form mobile-reply-form" data-mobile-reply-form>
                <input type="hidden" name="board" value="<?= e($boardKey) ?>" data-mobile-reply-board>
                <input type="hidden" name="thread_id" value="" data-mobile-reply-thread>
                <input type="hidden" name="parent_reply_id" value="" data-mobile-reply-parent>
                <input type="hidden" name="return_to" value="" data-mobile-reply-return>
                <label><span>이름</span><input type="text" name="name" maxlength="30" placeholder="익명" value="<?= e($auth['username'] ?? '') ?>" data-remember-name autocomplete="nickname"></label>
                <label><span>내용</span><textarea name="comment" rows="4" maxlength="5000" placeholder="댓글 내용을 입력하세요"></textarea></label>
                <label><span>이미지</span><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
                <label><span>게시물 비밀번호</span><input type="password" name="post_password" minlength="4" maxlength="100" placeholder="수정/삭제할 때 사용" required data-remember-password autocomplete="off"></label>
                <button class="button-primary" type="submit">등록</button>
            </form>
        </div>
    </section>
    <?php
}

function render_report_modal(string $boardKey, string $threadId, array $post, bool $isReply): void
{
    $postId = (string) ($post['id'] ?? '');
    $modalId = 'report-modal-' . $postId;
    $returnTo = ($isReply ? thread_url($boardKey, $threadId) . '#post-' . $postId : thread_url($boardKey, $threadId) . '#post-' . $threadId);
    $reasons = ['스팸/도배', '욕설/혐오', '불법/유해 콘텐츠', '개인정보 노출', '광고/홍보', '기타'];
    ?>
    <section id="<?= e($modalId) ?>" class="report-modal is-collapsed" data-toggle-panel>
        <div class="report-modal-backdrop" data-toggle-target="<?= e($modalId) ?>" data-toggle-group="report-single-<?= e($postId) ?>"></div>
        <div class="report-modal-sheet glass-card">
            <div class="report-modal-head">
                <strong>신고 접수</strong>
                <button type="button" class="reply-context-clear" data-toggle-target="<?= e($modalId) ?>" data-toggle-group="report-single-<?= e($postId) ?>">닫기</button>
            </div>
            <form action="/report.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <label><span>신고 사유</span>
                    <select name="reason" required>
                        <?php foreach ($reasons as $reason): ?>
                            <option value="<?= e($reason) ?>"><?= e($reason) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>신고 내용</span><textarea name="detail" rows="4" maxlength="1000" placeholder="상세한 신고 내용을 입력해주세요."></textarea></label>
                <button class="button-danger" type="submit">신고 접수</button>
            </form>
        </div>
    </section>
    <?php
}

function render_post_actions(string $boardKey, string $threadId, array $post, bool $isReply, string $context = 'thread'): void
{
    $postId = (string) ($post['id'] ?? '');
    $prefix = ($isReply ? 'reply' : 'thread') . '-' . $postId . '-' . $context;
    $returnTo = $context === 'board' ? board_manage_return_url($boardKey) : thread_return_url($boardKey, $threadId);
    $threadRaw = raw_repository_find_thread($boardKey, $threadId);
    $threadLocked = !empty($threadRaw['locked']);
    $replyTarget = $context === 'board'
        ? ($isReply ? 'board-reply-form-' . $threadId . '-' . $postId : 'board-reply-form-' . $threadId)
        : ($isReply ? 'reply-form-' . $postId : 'reply-form-thread');
    $replyLabel = $isReply ? '답글' : '댓글';
    $displayThread = raw_repository_find_thread($boardKey, $threadId) ?? ['id' => $threadId, 'replies' => []];
    $displayNo = post_display_number($displayThread, $postId);
    $isUnlocked = is_post_edit_unlocked($boardKey, $threadId, $postId);
    $reportModalId = 'report-modal-' . $postId;
    $mobileReturn = $context === 'board' ? board_url($boardKey) . '#post-' . $threadId : thread_url($boardKey, $threadId) . '#post-' . $postId;
    ?>
    <div class="post-actions-bar">
        <div class="post-actions-left">
            <?php if (!$threadLocked): ?>
                <button type="button" class="post-inline-action post-reply-action" data-toggle-group="reply-forms" data-toggle-target="<?= e($replyTarget) ?>" data-mobile-reply-button data-board="<?= e($boardKey) ?>" data-thread="<?= e($threadId) ?>" data-parent="<?= e($isReply ? $postId : '') ?>" data-label="<?= e(($isReply ? ('댓글 No.' . $displayNo . '에 답글') : '새 댓글 작성')) ?>" data-return="<?= e($mobileReturn) ?>"><?= e($replyLabel) ?></button>
            <?php else: ?>
                <span class="post-inline-action is-disabled">잠긴 스레드</span>
            <?php endif; ?>
        </div>
        <div class="post-actions-right">
            <button type="button" class="post-inline-action post-report-action" data-toggle-group="report-single-<?= e($postId) ?>" data-toggle-target="<?= e($reportModalId) ?>" aria-label="신고">⚠</button>
            <button type="button" class="post-inline-action" data-toggle-group="manage-<?= e($postId) ?>" data-toggle-target="<?= e($prefix) ?>-edit"><?= $isUnlocked ? '수정 중' : '수정' ?></button>
            <button type="button" class="post-inline-action post-delete-action" data-toggle-group="manage-<?= e($postId) ?>" data-toggle-target="<?= e($prefix) ?>-delete">삭제</button>
        </div>
    </div>
    <div class="manage-stack">
        <section id="<?= e($prefix) ?>-edit" class="mini-manage-form<?= $isUnlocked ? '' : ' is-collapsed' ?>" data-toggle-panel>
            <h3><?= $isReply ? '댓글 수정' : '스레드 수정' ?></h3>
            <?php if (!$isUnlocked): ?>
                <p class="mini-manage-note">먼저 게시물 비밀번호를 확인한 뒤 수정 단계로 넘어갑니다.</p>
                <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form">
                    <input type="hidden" name="manage_action" value="unlock_edit">
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                    <label><span>게시물 비밀번호</span><input type="password" name="post_password" required data-remember-password autocomplete="off"></label>
                    <button class="button-danger" type="submit"><?= $isReply ? '댓글 수정 열기' : '스레드 수정 열기' ?></button>
                </form>
            <?php else: ?>
                <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form">
                    <input type="hidden" name="manage_action" value="edit">
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                    <label class="readonly-field"><span>이름</span><input type="text" value="<?= e((string) ($post['name'] ?? '')) ?>" readonly disabled></label>
                    <?php if (!$isReply): ?>
                        <label><span>제목</span><input type="text" name="subject" maxlength="80" value="<?= e((string) ($post['subject'] ?? '')) ?>"></label>
                    <?php endif; ?>
                    <label><span>내용</span><textarea name="comment" rows="<?= $isReply ? '4' : '5' ?>" maxlength="5000"><?= e((string) ($post['comment'] ?? '')) ?></textarea></label>
                    <button class="button-danger" type="submit"><?= $isReply ? '댓글 수정' : '스레드 수정' ?></button>
                </form>
            <?php endif; ?>
        </section>
        <section id="<?= e($prefix) ?>-delete" class="mini-manage-form danger-form is-collapsed" data-toggle-panel>
            <h3><?= $isReply ? '댓글 삭제' : '스레드 삭제' ?></h3>
            <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form" onsubmit="return confirm('<?= $isReply ? '댓글' : '스레드' ?>을 삭제할까요?');">
                <input type="hidden" name="manage_action" value="delete">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <label><span>게시물 비밀번호</span><input type="password" name="post_password" required></label>
                <button class="button-danger" type="submit"><?= $isReply ? '댓글 삭제' : '스레드 삭제' ?></button>
            </form>
        </section>
    </div>
    <?php if (!$threadLocked): ?>
        <?php render_inline_reply_form($boardKey, $threadId, $isReply ? $postId : '', $isReply ? ('댓글 No.' . $displayNo . '에 답글') : '새 댓글 작성', $context); ?>
    <?php endif; ?>
    <?php render_report_modal($boardKey, $threadId, $post, $isReply); ?>
    <?php
}

function build_reply_tree(array $replies): array
{
    $byParent = [];
    foreach ($replies as $reply) {
        $parent = (string) ($reply['parent_reply_id'] ?? '');
        $byParent[$parent][] = $reply;
    }

    $walk = static function (string $parentId) use (&$walk, $byParent): array {
        $items = $byParent[$parentId] ?? [];
        $built = [];
        foreach ($items as $item) {
            $item['children'] = $walk((string) ($item['id'] ?? ''));
            $built[] = $item;
        }
        return $built;
    };

    return $walk('');
}


function flatten_descendant_replies(array $children): array
{
    $flat = [];
    $walk = static function (array $nodes) use (&$walk, &$flat): void {
        foreach ($nodes as $node) {
            $copy = $node;
            unset($copy['children']);
            $flat[] = $copy;
            if (!empty($node['children']) && is_array($node['children'])) {
                $walk($node['children']);
            }
        }
    };
    $walk($children);
    return $flat;
}


function reply_lookup_map(array $thread): array
{
    $lookup = [];
    $lookup[(string) ($thread['id'] ?? '')] = $thread;
    foreach (($thread['replies'] ?? []) as $reply) {
        $lookup[(string) ($reply['id'] ?? '')] = $reply;
    }
    return $lookup;
}

function group_replies_by_parent(array $replies): array
{
    $grouped = [];
    foreach ($replies as $reply) {
        $parentId = (string) ($reply['parent_reply_id'] ?? '');
        $grouped[$parentId][] = $reply;
    }
    return $grouped;
}

function render_bundle_group_quote(array $parentPost, array $displayNumbers): void
{
    $parentId = (string) ($parentPost['id'] ?? '');
    $parentNo = (string) ($displayNumbers[$parentId] ?? $parentId);
    $preview = text_preview(trim((string) ($parentPost['comment'] ?? '')), 90);
    if ($preview === '') {
        $preview = '내용 없음';
    }
    ?>
    <div class="reply-bundle-group-head">
        <span class="reply-bundle-target">댓글 No.<?= e($parentNo) ?></span>
        <p class="reply-bundle-quote"><?= e($preview) ?></p>
    </div>
    <?php
}
function direct_reply_count(array $replyTree): int
{
    return count($replyTree);
}

function visible_inline_children(array $children): array
{
    if ($children === []) {
        return [];
    }
    return [reset($children)];
}

function hidden_bundle_children(array $children): array
{
    if (count($children) <= 1) {
        return [];
    }
    return array_slice(array_values($children), 1);
}

function hidden_bundle_descendants(array $children): array
{
    $flat = flatten_descendant_replies($children);
    if (count($flat) <= 1) {
        return [];
    }
    return array_slice($flat, 1);
}

function all_board_threads_raw(): array
{
    $all = [];
    foreach (array_keys(app_config()['boards']) as $boardKey) {
        $decoded = json_decode((string) file_get_contents(board_file_path($boardKey)), true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $thread) {
            $thread['board'] = $boardKey;
            $all[] = $thread;
        }
    }
    return $all;
}

function write_board_threads_raw(string $boardKey, array $threads): void
{
    file_put_contents(board_file_path($boardKey), json_encode(array_values($threads), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function admin_config(): array
{
    return app_config()['admin'] ?? [];
}

function admin_gate_path(): string
{
    return '/mod-' . rawurlencode((string) (admin_config()['gate_key'] ?? 'momo-entry'));
}

function admin_session(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $admin = $_SESSION['admin_auth'] ?? null;
    return is_array($admin) ? $admin : null;
}

function admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION['admin_auth']);
}

function admin_login(string $username): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['admin_auth'] = ['username' => $username, 'logged_at' => time()];
}

function require_admin_gate_or_404(string $gate): void
{
    if ($gate !== (string) (admin_config()['gate_key'] ?? '')) {
        http_response_code(404);
        include __DIR__ . '/../View/404.php';
        exit;
    }
}

function admin_dashboard_stats(): array
{
    $threads = 0;
    $replies = 0;
    $images = 0;
    $locked = 0;
    $sticky = 0;
    foreach (all_board_threads_raw() as $thread) {
        $threads++;
        $replies += count($thread['replies'] ?? []);
        if (!empty($thread['image'])) {
            $images++;
        }
        if (!empty($thread['locked'])) {
            $locked++;
        }
        if (!empty($thread['sticky'])) {
            $sticky++;
        }
        foreach (($thread['replies'] ?? []) as $reply) {
            if (!empty($reply['image'])) {
                $images++;
            }
        }
    }
    $users = json_decode((string) file_get_contents(app_config()['storage_path'] . '/data/users.json'), true);
    return [
        'threads' => $threads,
        'replies' => $replies,
        'images' => $images,
        'locked' => $locked,
        'sticky' => $sticky,
        'users' => is_array($users) ? count($users) : 0,
        'reports' => count(array_filter(admin_reports(), static fn(array $report): bool => ($report['status'] ?? 'open') === 'open')),
        'bans' => count(active_bans()),
    ];
}

function admin_recent_posts(int $limit = 120): array
{
    $items = [];
    foreach (all_board_threads_raw() as $thread) {
        $boardKey = (string) ($thread['board'] ?? '');
        $items[] = [
            'type' => 'thread',
            'board' => $boardKey,
            'thread_id' => (string) ($thread['id'] ?? ''),
            'reply_id' => '',
            'subject' => (string) ($thread['subject'] ?? ''),
            'name' => (string) ($thread['name'] ?? ''),
            'comment' => (string) ($thread['comment'] ?? ''),
            'created_at' => (string) ($thread['created_at'] ?? ''),
            'reply_count' => count($thread['replies'] ?? []),
            'has_image' => !empty($thread['image']),
            'sticky' => !empty($thread['sticky']),
            'locked' => !empty($thread['locked']),
        ];
        foreach (($thread['replies'] ?? []) as $reply) {
            $items[] = [
                'type' => 'reply',
                'board' => $boardKey,
                'thread_id' => (string) ($thread['id'] ?? ''),
                'reply_id' => (string) ($reply['id'] ?? ''),
                'subject' => (string) ($thread['subject'] ?? ''),
                'name' => (string) ($reply['name'] ?? ''),
                'comment' => (string) ($reply['comment'] ?? ''),
                'created_at' => (string) ($reply['created_at'] ?? ''),
                'reply_count' => 0,
                'has_image' => !empty($reply['image']),
                'sticky' => false,
                'locked' => false,
            ];
        }
    }
    usort($items, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return array_slice($items, 0, $limit);
}

function admin_thread_action(string $boardKey, string $threadId, string $action): bool
{
    $threads = json_decode((string) file_get_contents(board_file_path($boardKey)), true);
    if (!is_array($threads)) {
        return false;
    }
    $changed = false;
    foreach ($threads as &$thread) {
        if (($thread['id'] ?? '') !== $threadId) {
            continue;
        }
        if ($action === 'toggle_lock') {
            $thread['locked'] = empty($thread['locked']);
            $changed = true;
        } elseif ($action === 'toggle_sticky') {
            $thread['sticky'] = empty($thread['sticky']);
            $changed = true;
        } elseif ($action === 'delete_image' && !empty($thread['image'])) {
            delete_upload_file((string) $thread['image']);
            $thread['image'] = null;
            $thread['image_original_name'] = null;
            $changed = true;
        } elseif ($action === 'archive') {
            $thread['archived'] = true;
            $changed = true;
        } elseif ($action === 'unarchive') {
            $thread['archived'] = false;
            $changed = true;
        } elseif ($action === 'delete') {
            delete_upload_file((string) ($thread['image'] ?? ''));
            foreach (($thread['replies'] ?? []) as $reply) {
                delete_upload_file((string) ($reply['image'] ?? ''));
            }
            $trash = read_data_file('trash', []);
            $trash[] = ['id' => date('YmdHis') . bin2hex(random_bytes(2)), 'type' => 'thread', 'board' => $boardKey, 'thread_id' => $threadId, 'payload' => $thread, 'deleted_at' => (new DateTimeImmutable())->format(DATE_ATOM)];
            write_data_file('trash', $trash);
        }
    }
    unset($thread);
    if ($action === 'delete') {
        $before = count($threads);
        $threads = array_values(array_filter($threads, static fn(array $thread): bool => ($thread['id'] ?? '') !== $threadId));
        $changed = $changed || count($threads) !== $before;
    }
    if ($changed) {
        write_board_threads_raw($boardKey, $threads);
        admin_log('thread_action', ['board' => $boardKey, 'thread_id' => $threadId, 'action' => $action]);
    }
    return $changed;
}

function admin_reply_action(string $boardKey, string $threadId, string $replyId, string $action): bool
{
    $threads = json_decode((string) file_get_contents(board_file_path($boardKey)), true);
    if (!is_array($threads)) {
        return false;
    }
    $changed = false;
    foreach ($threads as &$thread) {
        if (($thread['id'] ?? '') !== $threadId) {
            continue;
        }
        if ($action === 'delete_image') {
            foreach ($thread['replies'] as &$reply) {
                if (($reply['id'] ?? '') !== $replyId) {
                    continue;
                }
                if (!empty($reply['image'])) {
                    delete_upload_file((string) $reply['image']);
                    $reply['image'] = null;
                    $reply['image_original_name'] = null;
                    $changed = true;
                }
            }
            unset($reply);
        } elseif ($action === 'archive') {
            $thread['archived'] = true;
            $changed = true;
        } elseif ($action === 'unarchive') {
            $thread['archived'] = false;
            $changed = true;
        } elseif ($action === 'delete') {
            $removedReplies = [];
            foreach (($thread['replies'] ?? []) as $reply) {
                if (($reply['id'] ?? '') === $replyId || ($reply['parent_reply_id'] ?? '') === $replyId) {
                    delete_upload_file((string) ($reply['image'] ?? ''));
                    $removedReplies[] = $reply;
                }
            }
            if ($removedReplies !== []) {
                $trash = read_data_file('trash', []);
                foreach ($removedReplies as $removed) {
                    $trash[] = ['id' => date('YmdHis') . bin2hex(random_bytes(2)), 'type' => 'reply', 'board' => $boardKey, 'thread_id' => $threadId, 'payload' => $removed, 'deleted_at' => (new DateTimeImmutable())->format(DATE_ATOM)];
                }
                write_data_file('trash', $trash);
            }
            $before = count($thread['replies']);
            $thread['replies'] = array_values(array_filter($thread['replies'], static fn(array $reply): bool => ($reply['id'] ?? '') !== $replyId && ($reply['parent_reply_id'] ?? '') !== $replyId));
            $thread['reply_count'] = count($thread['replies']);
            $changed = $changed || count($thread['replies']) !== $before;
        }
        break;
    }
    unset($thread);
    if ($changed) {
        write_board_threads_raw($boardKey, $threads);
        admin_log('reply_action', ['board' => $boardKey, 'thread_id' => $threadId, 'reply_id' => $replyId, 'action' => $action]);
    }
    return $changed;
}




function all_users_raw(): array
{
    return read_data_file('users', []);
}

function save_users_raw(array $users): void
{
    write_data_file('users', $users);
}

function update_user_suspension(string $userId, bool $suspended): bool
{
    $users = all_users_raw();
    $changed = false;
    foreach ($users as &$user) {
        if ((string) ($user['id'] ?? '') === $userId) {
            $user['suspended'] = $suspended;
            $changed = true;
            break;
        }
    }
    unset($user);
    if ($changed) {
        save_users_raw($users);
        admin_log('user_suspension_updated', ['user_id' => $userId, 'suspended' => $suspended]);
    }
    return $changed;
}

function backup_files(): array
{
    $dir = app_config()['storage_path'] . '/backups';
    $files = glob($dir . '/*.json') ?: [];
    rsort($files);
    return $files;
}
function data_file_path(string $name): string
{
    return app_config()['storage_path'] . '/data/' . $name . '.json';
}

function read_data_file(string $name, array $default = []): array
{
    $path = data_file_path($name);
    if (!is_file($path)) {
        return $default;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $default;
}

function write_data_file(string $name, array $data): void
{
    file_put_contents(data_file_path($name), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function current_client_ip(): string
{
    return (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function is_member_suspended(?string $userId): bool
{
    if ($userId === null || $userId === '') {
        return false;
    }
    foreach (read_data_file('users', []) as $user) {
        if ((string) ($user['id'] ?? '') === $userId) {
            return !empty($user['suspended']);
        }
    }
    return false;
}

function report_reasons(): array
{
    return ['스팸/도배', '욕설/혐오', '불법/유해 콘텐츠', '개인정보 노출', '광고/홍보', '기타'];
}

function create_report(string $boardKey, string $threadId, string $replyId, string $reason, string $detail): void
{
    $reports = read_data_file('reports', []);
    $reports[] = [
        'id' => date('YmdHis') . bin2hex(random_bytes(3)),
        'board' => $boardKey,
        'thread_id' => $threadId,
        'reply_id' => $replyId,
        'reason' => $reason,
        'detail' => $detail,
        'status' => 'open',
        'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        'ip' => current_client_ip(),
    ];
    write_data_file('reports', $reports);
    admin_log('report_created', ['board' => $boardKey, 'thread_id' => $threadId, 'reply_id' => $replyId, 'reason' => $reason]);
}

function admin_log(string $action, array $context = []): void
{
    $logs = read_data_file('admin_logs', []);
    $logs[] = [
        'id' => date('YmdHis') . bin2hex(random_bytes(2)),
        'action' => $action,
        'context' => $context,
        'admin' => admin_session()['username'] ?? 'system',
        'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
    write_data_file('admin_logs', array_slice($logs, -1000));
}

function site_settings(): array
{
    return read_data_file('settings', ['site' => ['allow_anonymous' => true, 'members_only_posting' => false, 'max_upload_mb' => app_config()['max_upload_mb'], 'reply_depth_limit' => 100, 'auto_lock_reply_count' => 250], 'boards' => []]);
}

function save_site_settings(array $settings): void
{
    write_data_file('settings', $settings);
    admin_log('settings_saved');
}

function active_filters(): array
{
    return read_data_file('filters', ['blocked_words' => [], 'blocked_names' => [], 'blocked_links' => [], 'require_approval_words' => [], 'blocked_image_hashes' => []]);
}

function save_filters(array $filters): void
{
    write_data_file('filters', $filters);
    admin_log('filters_saved');
}

function active_bans(): array
{
    return read_data_file('bans', []);
}

function save_bans(array $bans): void
{
    write_data_file('bans', $bans);
    admin_log('bans_saved');
}

function moderation_guard_errors(string $name, string $comment, ?string $userId = null, ?string $imageTmpPath = null): array
{
    $errors = [];
    $bans = active_bans();
    $ip = current_client_ip();
    foreach ($bans as $ban) {
        if (!empty($ban['expires_at']) && strtotime((string) $ban['expires_at']) < time()) {
            continue;
        }
        if (($ban['type'] ?? '') === 'ip' && (string) ($ban['value'] ?? '') === $ip) {
            $errors[] = '현재 환경에서는 글을 작성할 수 없습니다.';
        }
        if (($ban['type'] ?? '') === 'name' && lower_text((string) ($ban['value'] ?? '')) === lower_text($name)) {
            $errors[] = '사용할 수 없는 이름입니다.';
        }
        if (($ban['type'] ?? '') === 'user' && $userId !== null && (string) ($ban['value'] ?? '') === $userId) {
            $errors[] = '정지된 회원 계정입니다.';
        }
    }
    if ($userId && is_member_suspended($userId)) {
        $errors[] = '정지된 회원 계정입니다.';
    }
    $filters = active_filters();
    $haystack = lower_text($name . ' ' . $comment);
    foreach (($filters['blocked_words'] ?? []) as $word) {
        $word = trim((string) $word);
        if ($word !== '' && str_contains($haystack, lower_text($word))) {
            $errors[] = '금칙어가 포함되어 있습니다.';
            break;
        }
    }
    foreach (($filters['blocked_names'] ?? []) as $word) {
        $word = trim((string) $word);
        if ($word !== '' && str_contains(lower_text($name), lower_text($word))) {
            $errors[] = '사용할 수 없는 이름입니다.';
            break;
        }
    }
    foreach (($filters['blocked_links'] ?? []) as $link) {
        $link = trim((string) $link);
        if ($link !== '' && str_contains($comment, $link)) {
            $errors[] = '허용되지 않는 링크가 포함되어 있습니다.';
            break;
        }
    }
    return array_values(array_unique($errors));
}

function backup_snapshot(): string
{
    $name = 'backup_' . date('Ymd_His') . '.json';
    $payload = [
        'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        'boards' => [],
        'users' => read_data_file('users', []),
        'reports' => read_data_file('reports', []),
        'bans' => read_data_file('bans', []),
        'filters' => active_filters(),
        'settings' => site_settings(),
        'logs' => read_data_file('admin_logs', []),
        'trash' => read_data_file('trash', []),
    ];
    foreach (array_keys(app_config()['boards']) as $boardKey) {
        $payload['boards'][$boardKey] = json_decode((string) file_get_contents(board_file_path($boardKey)), true) ?: [];
    }
    $path = app_config()['storage_path'] . '/backups/' . $name;
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    admin_log('backup_created', ['file' => $name]);
    return $path;
}

function admin_reports(): array
{
    $reports = read_data_file('reports', []);
    usort($reports, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return $reports;
}

function update_report_status(string $reportId, string $status): bool
{
    $reports = read_data_file('reports', []);
    $changed = false;
    foreach ($reports as &$report) {
        if ((string) ($report['id'] ?? '') === $reportId) {
            $report['status'] = $status;
            $report['handled_at'] = (new DateTimeImmutable())->format(DATE_ATOM);
            $changed = true;
            break;
        }
    }
    unset($report);
    if ($changed) {
        write_data_file('reports', $reports);
        admin_log('report_status_updated', ['id' => $reportId, 'status' => $status]);
    }
    return $changed;
}

function move_thread_to_board(string $fromBoard, string $threadId, string $toBoard): bool
{
    if ($fromBoard === $toBoard) {
        return false;
    }
    $from = json_decode((string) file_get_contents(board_file_path($fromBoard)), true);
    $to = json_decode((string) file_get_contents(board_file_path($toBoard)), true);
    if (!is_array($from) || !is_array($to)) {
        return false;
    }
    foreach ($from as $idx => $thread) {
        if ((string) ($thread['id'] ?? '') === $threadId) {
            $thread['board'] = $toBoard;
            $to[] = $thread;
            unset($from[$idx]);
            write_board_threads_raw($fromBoard, array_values($from));
            write_board_threads_raw($toBoard, $to);
            admin_log('thread_moved', ['thread_id' => $threadId, 'from' => $fromBoard, 'to' => $toBoard]);
            return true;
        }
    }
    return false;
}

function archive_old_threads(): int
{
    $settings = site_settings();
    $count = 0;
    foreach (all_board_threads_raw() as $thread) {
        $threshold = strtotime('-30 days');
        if (strtotime((string) ($thread['bumped_at'] ?? $thread['created_at'] ?? 'now')) < $threshold) {
            if (admin_thread_action((string) $thread['board'], (string) $thread['id'], 'archive')) {
                $count++;
            }
        }
    }
    return $count;
}
