<?php
declare(strict_types=1);

namespace App\Wms;

use App\Infra\Database;
use PDO;

final class WmsRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function countriesList(): array
    {
        $rows = $this->statisticsPdo()->query('SELECT pais FROM wmsdata_paises ORDER BY pais')->fetchAll(PDO::FETCH_ASSOC);
        return array_values(array_map(static fn(array $row): string => (string) ($row['pais'] ?? ''), $rows));
    }

    public function pais(string $pais, string $order): array
    {
        $allowed = [
            'management_avg desc', 'management_avg asc',
            'score_management_5d desc', 'score_management_5d asc',
        ];
        if (!in_array($order, $allowed, true)) {
            $order = 'management_avg desc';
        }

        $sql = "
            SELECT
              m.*,
              s.score_management_5d
            FROM v_wms_medias_por_pais m
            LEFT JOIN v_wms_score_management_por_pais s ON s.pais = m.pais
            WHERE (:p1 = '' OR m.pais LIKE CONCAT('%', :p2, '%'))
            ORDER BY {$order}
            LIMIT 500
        ";

        return $this->fetchTable($sql, [':p1' => $pais, ':p2' => $pais]);
    }

    public function score(int $topN): array
    {
        if ($topN <= 0 || $topN > 200) {
            $topN = 20;
        }
        return $this->fetchTable("SELECT * FROM v_wms_score_management_por_pais ORDER BY score_management_5d DESC LIMIT {$topN}");
    }

    public function g7Brics(): array
    {
        return $this->fetchTable('SELECT * FROM v_wms_medias_g7_brics ORDER BY grupo');
    }

    public function primeiroMundo(): array
    {
        return $this->fetchTable('SELECT * FROM v_wms_medias_primeiro_mundo_vs_demais ORDER BY grupo');
    }

    public function regiao(): array
    {
        return $this->fetchTable('SELECT * FROM v_wms_medias_por_regiao ORDER BY regiao');
    }

    public function comparador(string $paisA, string $paisB): array
    {
        $sql = "
            SELECT
              m.pais,
              m.qt_registros,
              m.soma_N,
              m.management_avg,
              m.operations_avg,
              m.monitor_avg,
              m.target_avg,
              m.people_avg,
              s.score_management_5d
            FROM v_wms_medias_por_pais m
            LEFT JOIN v_wms_score_management_por_pais s ON s.pais = m.pais
            WHERE m.pais = :a OR m.pais = :b
            ORDER BY m.pais
        ";

        return $this->fetchTable($sql, [':a' => $paisA, ':b' => $paisB]);
    }

    public function colombiaCompare(): array
    {
        return $this->fetchTable(<<<'SQL'
            SELECT
                country, management, operations, monitor, target, people,
                lean1, lean2,
                perf1, perf2, perf3, perf4, perf5, perf6, perf7, perf8, perf9, perf10,
                talent1, talent2, talent3, talent4, talent5, talent6,
                sic2, N
            FROM v_wms_compare_all_colombia
            ORDER BY FIELD(country,'Colombia','United States','LATAM','Mundo')
        SQL);
    }

    /**
     * @return array{ok: true, columns: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function fetchTable(string $sql, array $params = []): array
    {
        $statement = $this->statisticsPdo()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $columns = $rows !== [] ? array_keys($rows[0]) : [];

        return [
            'ok' => true,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function statisticsPdo(): PDO
    {
        return $this->database->statisticsPdo();
    }
}
