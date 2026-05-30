<?php
declare(strict_types=1);

use App\Support\Env;

return [
    'admin_user' => Env::get('ADMIN_USER', 'admin'),
    'admin_pass_hash' => Env::get('ADMIN_PASS_HASH', ''),
];
