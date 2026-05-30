<?php
declare(strict_types=1);

namespace App\Modules;

use App\Auth\AuthService;
use App\Support\View;

final class PlaceholderController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function show(string $module, string $title): void
    {
        $this->auth->requireAuth();

        View::render('module/placeholder', [
            'title' => $title,
            'module' => $module,
            'user' => $this->auth->user(),
            'pageTitle' => $title,
            'contentTitle' => $title,
        ]);
    }
}
