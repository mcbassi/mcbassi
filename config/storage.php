<?php
declare(strict_types=1);

use App\Support\Env;

return [
    'upload_max_mb' => Env::int('UPLOAD_MAX_MB', 15),
    'artifacts_base_dir' => Env::get('ARTIFACTS_BASE_DIR', dirname(__DIR__) . '/storage/clientes') ?? (dirname(__DIR__) . '/storage/clientes'),
];
