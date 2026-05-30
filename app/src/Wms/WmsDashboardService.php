<?php
declare(strict_types=1);

namespace App\Wms;

use RuntimeException;

final class WmsDashboardService
{
    public function __construct(private readonly WmsRepository $repository)
    {
    }

    public function handle(string $tab, array $input): array
    {
        return match ($tab) {
            'paises_list' => ['ok' => true, 'paises' => $this->repository->countriesList()],
            'pais' => $this->repository->pais(
                trim((string) ($input['pais'] ?? '')),
                trim((string) ($input['order'] ?? 'management_avg desc')),
            ),
            'score' => $this->repository->score((int) ($input['topN'] ?? 20)),
            'g7brics' => $this->repository->g7Brics(),
            'primeiro_mundo' => $this->repository->primeiroMundo(),
            'regiao' => $this->repository->regiao(),
            'comparador' => $this->comparador($input),
            'colombia_compare' => $this->repository->colombiaCompare(),
            default => throw new RuntimeException('tab desconhecida.'),
        };
    }

    private function comparador(array $input): array
    {
        $paisA = trim((string) ($input['paisA'] ?? ''));
        $paisB = trim((string) ($input['paisB'] ?? ''));
        if ($paisA === '' || $paisB === '') {
            throw new RuntimeException('paisA e paisB requeridos.');
        }

        return $this->repository->comparador($paisA, $paisB);
    }
}
