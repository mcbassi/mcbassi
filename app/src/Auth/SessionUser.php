<?php
declare(strict_types=1);

namespace App\Auth;

final class SessionUser
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $nivel,
        public readonly bool $isAdmin
    ) {
    }
}
