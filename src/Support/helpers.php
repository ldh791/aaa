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

function is_registered_post(array $post): bool
{
    return !empty($post['username']) || !empty($post['user_id']);
}

function render_author_html(array $post): string
{
    $name = e((string) ($post['name'] ?? '익명'));
    if (!is_registered_post($post)) {
        return '<strong>' . $name . '</strong>';
    }

    $username = e((string) ($post['username'] ?? $post['name'] ?? 'member'));
    return '<span class="author-chip is-member"><strong>' . $name . '</strong><span class="member-badge" title="회원 계정">✔</span><span class="member-handle">@' . $username . '</span></span>';
}

function render_site_menu(?string $currentPath = null): void
{
    $currentPath ??= current_path();
    $auth = auth_user();
    $links = menu_links();
    ?>
    <section class="site-menu-wrap">
        <button type="button" class="site-menu-toggle" data-menu-toggle aria-expanded="false" aria-label="메뉴 열기">
            <span></span><span></span><span></span>
        </button>
        <nav class="site-menu glass-card" data-menu-panel>
            <div class="site-menu-head">
                <div>
                    <p class="eyebrow">Menu</p>
                    <strong>빠른 이동</strong>
                </div>
                <button type="button" class="button-secondary menu-collapse-button" data-menu-collapse aria-expanded="true">접기</button>
            </div>
            <div class="site-menu-body">
                <div class="site-menu-links">
                    <?php foreach ($links as $link): ?>
                        <?php $isCurrent = str_starts_with($link['href'], $currentPath) && $currentPath !== '/'; ?>
                        <a class="site-menu-link<?= $isCurrent ? ' is-active' : '' ?>" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="site-menu-auth">
                    <?php if ($auth): ?>
                        <span class="site-menu-user">@<?= e($auth['username']) ?><span class="member-badge" title="회원 계정">✔</span></span>
                        <form action="/auth.php" method="post" class="inline-form">
                            <input type="hidden" name="action" value="logout">
                            <button class="button-secondary menu-auth-button" type="submit">로그아웃</button>
                        </form>
                    <?php else: ?>
                        <a class="button-secondary menu-auth-button" href="/login.php">로그인</a>
                        <a class="button-secondary menu-auth-button" href="/register.php">회원가입</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </section>
    <?php
}
