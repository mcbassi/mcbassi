<?php
declare(strict_types=1);

use App\Support\Env;

return [
    'dropbox_app_key' => Env::get('DROPBOX_APP_KEY', ''),
    'dropbox_app_secret' => Env::get('DROPBOX_APP_SECRET', ''),
    'dropbox_refresh_token' => Env::get('DROPBOX_REFRESH_TOKEN', ''),
    'rclone_remote' => Env::get('RCLONE_REMOTE', 'dropbox'),
    'rclone_binary' => Env::get('RCLONE_BINARY', '/usr/bin/rclone'),
];
