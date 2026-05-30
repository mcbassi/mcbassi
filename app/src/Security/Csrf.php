<?php
declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    public static function boot(): void
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function token(): string
    {
        self::boot();

        return (string) $_SESSION['_csrf'];
    }

    public static function check(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals(self::token(), $token);
    }

    public static function requireValid(?string $token): void
    {
        if (!self::check($token)) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
