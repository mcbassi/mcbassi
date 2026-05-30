<?php
declare(strict_types=1);

namespace App\Analitica;

use App\Support\Request;
use App\Support\View;

final class AnaliticaController
{
    public function index(Request $request): string
    {
        return View::make('analitica/index', ['title' => 'Analítica']);
    }

    public function finalReport(Request $request): string
    {
        return View::make('analitica/final_report', ['title' => 'Analítica - Relatório Final']);
    }

    public function exportWord(Request $request): string
    {
        return View::make('analitica/export_word', ['title' => 'Analítica - Export Word']);
    }

    public function prefill(Request $request): array
    {
        return ['ok' => true, 'data' => [], 'message' => 'Prefill ainda não migrado.'];
    }

    public function duvidas(Request $request): string
    {
        return View::make('analitica/duvidas', ['title' => 'Analítica - Dúvidas']);
    }
}
