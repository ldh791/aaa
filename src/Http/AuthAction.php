<?php
declare(strict_types=1);

namespace App\Http;

use App\Service\AuthService;

final class AuthAction
{
    public function __construct(private AuthService $authService)
    {
    }

    public function register(): never
    {
        $result = $this->authService->register(posted_value('username'), posted_value('password'), posted_value('password_confirm'));
        if (!$result['ok']) {
            flash_set('error', implode(' ', $result['errors']));
            redirect('/register.php');
        }

        $this->authService->login(posted_value('username'), posted_value('password'));
        flash_set('success', '회원가입이 완료되었습니다.');
        redirect('/');
    }

    public function login(): never
    {
        $result = $this->authService->login(posted_value('username'), posted_value('password'));
        if (!$result['ok']) {
            flash_set('error', implode(' ', $result['errors']));
            redirect('/login.php');
        }

        flash_set('success', '로그인되었습니다.');
        redirect('/');
    }

    public function logout(): never
    {
        $this->authService->logout();
        flash_set('success', '로그아웃되었습니다.');
        redirect('/');
    }
}
