<?php
declare(strict_types=1);

return App\Infra\Database::connection(config_get('database', []));
