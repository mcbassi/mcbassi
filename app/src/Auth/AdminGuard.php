<?php
declare(strict_types=1);

namespace App\Auth;

final class AdminGuard
{
    public static function isAdmin(): bool
    {
        if (($_SESSION['is_admin'] ?? false) === true) {
            return true;
        }

        $user = SessionUser::user();

        return is_array($user) && (($user['is_admin'] ?? false) === true);
    }
}
