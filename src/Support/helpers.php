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

    $usersFile = $config['storage_path'] . '/data/users.json';
    if (!is_file($usersFile)) {
        file_put_contents($usersFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
    return $config['max_upload_mb'] * 1024 * 1024;
}

function format_bytes_label(int $bytes): string
{
    return number_format($bytes / 1024 / 1024, 0) . 'MB';
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
        <nav class="site-sidebar-links">
            <?php foreach ($links as $link): ?>
                <?php $href = (string) $link['href']; $hrefPath = strtok($href, '?') ?: $href; $isCurrent = $currentUri === $href || ($hrefPath === $currentPath && strpos($href, '?') === false); ?>
                <a class="site-sidebar-link<?= $isCurrent ? ' is-active' : '' ?>" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="site-sidebar-auth">
            <?php if ($auth): ?>
                <span class="site-sidebar-user">@<?= e($auth['username']) ?></span>
                <form action="/auth.php" method="post" class="sidebar-form-block">
                    <input type="hidden" name="action" value="logout">
                    <button class="button-secondary sidebar-action-button" type="submit">로그아웃</button>
                </form>
            <?php else: ?>
                <a class="button-secondary sidebar-action-button" href="/login.php">로그인</a>
            <?php endif; ?>
        </div>
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
    return 20;
}

function board_preview_batch_count(): int
{
    return 20;
}

function thread_preview_initial_count(): int
{
    return 20;
}

function thread_preview_batch_count(): int
{
    return 20;
}

function thread_display_number_map(array $thread): array
{
    $map = [];
    $next = 1;

    $threadId = (string) ($thread['id'] ?? '');
    if ($threadId !== '') {
        $map[$threadId] = $next++;
    }

    foreach (($thread['replies'] ?? []) as $reply) {
        $replyId = (string) ($reply['id'] ?? '');
        if ($replyId === '' || isset($map[$replyId])) {
            continue;
        }
        $map[$replyId] = $next++;
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
            <label><span>이름</span><input type="text" name="name" maxlength="30" placeholder="익명" value="<?= e($auth['username'] ?? '') ?>"></label>
            <label><span>내용</span><textarea name="comment" rows="5" maxlength="5000" placeholder="댓글 내용을 입력하세요"></textarea></label>
            <label><span>이미지</span><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
            <label><span>게시물 비밀번호</span><input type="password" name="post_password" minlength="4" maxlength="100" placeholder="수정/삭제할 때 사용" required oninvalid="this.setCustomValidity('비밀번호를 입력해주세요.')" oninput="this.setCustomValidity('')"></label>
            <button class="button-primary" type="submit">등록</button>
        </form>
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
    ?>
    <div class="post-actions-bar">
        <div class="post-actions-left">
            <?php if (!$threadLocked): ?>
                <button type="button" class="post-inline-action post-reply-action" data-toggle-group="reply-forms" data-toggle-target="<?= e($replyTarget) ?>"><?= e($replyLabel) ?></button>
            <?php else: ?>
                <span class="post-inline-action is-disabled">잠긴 스레드</span>
            <?php endif; ?>
        </div>
        <div class="post-actions-right">
            <button type="button" class="post-inline-action" data-toggle-group="manage-<?= e($postId) ?>" data-toggle-target="<?= e($prefix) ?>-edit"><?= $isUnlocked ? '수정 중' : '수정' ?></button>
            <button type="button" class="post-inline-action post-delete-action" data-toggle-group="manage-<?= e($postId) ?>" data-toggle-target="<?= e($prefix) ?>-delete">삭제</button>
        </div>
    </div>
    <div class="manage-stack">
        <section id="<?= e($prefix) ?>-edit" class="mini-manage-form<?= $isUnlocked ? "" : " is-collapsed" ?>" data-toggle-panel>
            <h3><?= $isReply ? '댓글 수정' : '스레드 수정' ?></h3>
            <?php if (!$isUnlocked): ?>
                <p class="mini-manage-note">먼저 게시물 비밀번호를 확인한 뒤 수정 단계로 넘어갑니다.</p>
                <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form">
                    <input type="hidden" name="manage_action" value="unlock_edit">
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                    <label><span>게시물 비밀번호</span><input type="password" name="post_password" required></label>
                    <button class="button-danger" type="submit"><?= $isReply ? '댓글 수정 열기' : '스레드 수정 열기' ?></button>
                </form>
            <?php else: ?>
                <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form">
                    <input type="hidden" name="manage_action" value="edit">
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                    <label><span>이름</span><input type="text" name="name" maxlength="30" value="<?= e((string) ($post['name'] ?? '')) ?>"></label>
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
        } elseif ($action === 'delete') {
            delete_upload_file((string) ($thread['image'] ?? ''));
            foreach (($thread['replies'] ?? []) as $reply) {
                delete_upload_file((string) ($reply['image'] ?? ''));
            }
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
        } elseif ($action === 'delete') {
            foreach (($thread['replies'] ?? []) as $reply) {
                if (($reply['id'] ?? '') === $replyId || ($reply['parent_reply_id'] ?? '') === $replyId) {
                    delete_upload_file((string) ($reply['image'] ?? ''));
                }
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
    }
    return $changed;
}
