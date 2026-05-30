<?php
declare(strict_types=1);

namespace App\Auth;

use App\Infra\Env;
use App\Support\Request;

final class AuthService
{
    public function __construct(private readonly Request $request)
    {
    }

    public function loginFromQuery(): void
    {
        $enabled = filter_var(Env::get('AUTH_QUERY_LOGIN_ENABLED', 'true'), FILTER_VALIDATE_BOOL);

        if (!$enabled) {
            return;
        }

        $user = $this->request->query('user');
        $email = $this->request->query('email');
        $nivel = $this->request->query('nivel');

        if ($user === null && $email === null && $nivel === null) {
            return;
        }

        if ($user === null || $user === '' || $email === null || $email === '' || $nivel === null || $nivel === '') {
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        session_regenerate_id(true);

        $_SESSION['auth'] = true;
        $_SESSION['user'] = $user;
        $_SESSION['email'] = $email;
        $_SESSION['nivel'] = $nivel;
        $_SESSION['is_admin'] = filter_var(Env::get('AUTH_DEFAULT_IS_ADMIN', 'false'), FILTER_VALIDATE_BOOL);
        $_SESSION['auth_source'] = 'query_string';
        $_SESSION['logged_at'] = date('c');

        $cleanUrl = $this->request->basePath() . '/';
        header('Location: ' . ($cleanUrl === '' ? '/' : $cleanUrl), true, 302);
        exit;
    }

    public function ensureLegacyAliases(): void
    {
        if (!$this->check()) {
            return;
        }

        $_SESSION['usuario'] = $_SESSION['user'] ?? null;
        $_SESSION['user_name'] = $_SESSION['user'] ?? null;
        $_SESSION['user_email'] = $_SESSION['email'] ?? null;
    }

    public function check(): bool
    {
        return (bool) ($_SESSION['auth'] ?? false);
    }

    public function user(): SessionUser
    {
        return new SessionUser(
            (string) ($_SESSION['user'] ?? ''),
            (string) ($_SESSION['email'] ?? ''),
            (string) ($_SESSION['nivel'] ?? ''),
            (bool) ($_SESSION['is_admin'] ?? false)
        );
    }

    public function requireAuth(): void
    {
        if ($this->check()) {
            return;
        }

        header('Location: ' . $this->request->basePath() . '/index.php', true, 302);
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
