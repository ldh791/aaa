<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepositoryInterface;

final class AuthService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function register(string $username, string $password, string $passwordConfirm): array
    {
        $username = trim($username);
        $errors = [];

        if ($username === '' || strlen($username) < 3 || strlen($username) > 20) {
            $errors[] = '유저네임은 3자 이상 20자 이하로 입력해주세요.';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = '유저네임은 영문, 숫자, 밑줄(_)만 사용할 수 있습니다.';
        }
        if ($password === '' || strlen($password) < 4 || strlen($password) > 100) {
            $errors[] = '비밀번호는 4자 이상으로 입력해주세요.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = '비밀번호 확인이 일치하지 않습니다.';
        }
        if ($this->users->findByUsername($username) !== null) {
            $errors[] = '이미 존재하는 유저네임입니다.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ok = $this->users->createUser($username, $hash);

        return ['ok' => $ok, 'errors' => $ok ? [] : ['회원가입에 실패했습니다.']];
    }

    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(trim($username));
        if ($user === null || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'errors' => ['아이디 또는 비밀번호가 올바르지 않습니다.']];
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['auth'] = [
            'id' => $user['id'],
            'username' => $user['username'],
        ];

        return ['ok' => true, 'errors' => []];
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['auth']);
    }
}
