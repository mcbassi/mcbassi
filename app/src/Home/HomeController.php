<?php
declare(strict_types=1);

namespace App\Home;

use App\Auth\AuthService;
use App\Support\View;

use function redirect;
use function url;

final class HomeController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function index(): void
    {
        if ($this->auth->check()) {
            redirect(url('admin/responses.php'));
        }

        View::render('home/index', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'pageTitle' => 'Dashboard',
            'contentTitle' => 'Dashboard — Respostas',
        ]);
    }
}
