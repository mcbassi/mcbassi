<?php
declare(strict_types=1);

namespace App\Ppt;

use App\Auth\AuthService;
use App\Support\View;

final class PptController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function index(): void
    {
        $this->auth->requireAuth();

        View::render('ppt/index', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'pageTitle' => \t('menu.ppt_generator'),
            'contentTitle' => \t('menu.ppt_generator'),
            'subtitle' => 'ProdCol',
            'embedUrl' => '/ppt_dynamic_builder/public/admin_presentations.php',
        ]);
    }
}