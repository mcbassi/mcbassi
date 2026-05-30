<?php
declare(strict_types=1);

require __DIR__ . '/autoload.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/legacy_config.php';

use App\Auth\AuthService;
use App\Infra\Database;
use App\Infra\Env;
use App\Support\I18n;
use App\Support\Request;
use App\Support\Url;

Env::boot(app_path('.env'));

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Sao_Paulo'));

$request = Request::capture();
Url::setBasePath($request->basePath());

require __DIR__ . '/session.php';
I18n::boot(app_path('app/lang'), Env::get('APP_LOCALE', 'pt'));
require __DIR__ . '/security.php';
require __DIR__ . '/legacy.php';

$database = new Database();
$auth = new AuthService($request);

$auth->loginFromQuery();
$auth->ensureLegacyAliases();

return [
    'env' => Env::class,
    'request' => $request,
    'db' => $database,
    'auth' => $auth,
    'lang' => I18n::lang(),
];
