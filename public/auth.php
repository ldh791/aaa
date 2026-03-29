<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\Http\AuthAction;
use App\Service\AuthService;

$action = posted_value('action');
$controller = new AuthAction(new AuthService(user_repository()));

if ($action === 'register') {
    $controller->register();
}
if ($action === 'login') {
    $controller->login();
}
if ($action === 'logout') {
    $controller->logout();
}

flash_set('error', '잘못된 인증 요청입니다.');
redirect('/');
