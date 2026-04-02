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
            'href' => '/board.php?board=' . rawurlencode((string) $boardKey),
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

function board_manage_return_url(string $boardKey): string
{
    return '/board.php?board=' . rawurlencode($boardKey);
}

function thread_return_url(string $boardKey, string $threadId): string
{
    return '/thread.php?board=' . rawurlencode($boardKey) . '&id=' . rawurlencode($threadId);
}

function render_post_actions(string $boardKey, string $threadId, array $post, bool $isReply, string $context = 'thread'): void
{
    $postId = (string) ($post['id'] ?? '');
    $prefix = ($isReply ? 'reply' : 'thread') . '-' . $postId . '-' . $context;
    $returnTo = $context === 'board' ? board_manage_return_url($boardKey) : thread_return_url($boardKey, $threadId);
    ?>
    <div class="post-actions-bar">
        <div class="post-actions-left">
            <?php if ($context === 'thread'): ?>
                <button type="button" class="post-inline-action post-reply-action" data-quote-target="#reply-comment-box" data-quote-parent="<?= e($postId) ?>" data-quote-label="<?= e('No.' . $postId) ?>">↳ 답글</button>
            <?php else: ?>
                <a class="post-inline-action post-reply-action" href="<?= e(thread_return_url($boardKey, $threadId)) ?>#reply-comment-box">↳ 답글</a>
            <?php endif; ?>
        </div>
        <div class="post-actions-right">
            <button type="button" class="post-inline-action" data-toggle-group="manage-<?= e($postId) ?>" data-toggle-target="<?= e($prefix) ?>-edit">수정</button>
            <button type="button" class="post-inline-action post-delete-action" data-toggle-group="manage-<?= e($postId) ?>" data-toggle-target="<?= e($prefix) ?>-delete">삭제</button>
        </div>
    </div>
    <div class="manage-stack">
        <section id="<?= e($prefix) ?>-edit" class="mini-manage-form is-collapsed" data-toggle-panel>
            <h3><?= $isReply ? '댓글 수정' : '스레드 수정' ?></h3>
            <form action="/manage_post.php?board=<?= e($boardKey) ?>&thread_id=<?= e($threadId) ?><?= $isReply ? '&reply_id=' . rawurlencode($postId) : '' ?>" method="post" class="stack-form compact-form">
                <input type="hidden" name="manage_action" value="edit">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <label><span>이름</span><input type="text" name="name" maxlength="30" value="<?= e((string) ($post['name'] ?? '')) ?>"></label>
                <?php if (!$isReply): ?>
                    <label><span>제목</span><input type="text" name="subject" maxlength="80" value="<?= e((string) ($post['subject'] ?? '')) ?>"></label>
                <?php endif; ?>
                <label><span>내용</span><textarea name="comment" rows="<?= $isReply ? '4' : '5' ?>" maxlength="5000"><?= e((string) ($post['comment'] ?? '')) ?></textarea></label>
                <label><span>게시물 비밀번호</span><input type="password" name="post_password" required></label>
                <button class="button-secondary" type="submit"><?= $isReply ? '댓글 수정' : '스레드 수정' ?></button>
            </form>
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
