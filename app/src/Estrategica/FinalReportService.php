<?php
declare(strict_types=1);

namespace App\Estrategica;

use App\Infra\Database;

final class FinalReportService
{
    public function __construct(private readonly Database $database)
    {
    }
}
