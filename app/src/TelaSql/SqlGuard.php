<?php
declare(strict_types=1);

namespace App\TelaSql;

final class SqlGuard
{
    public function stripComments(string $sql): string
    {
        $sql = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
        $sql = preg_replace('/--[^\r\n]*[\r\n]/', ' ', $sql . "\n") ?? $sql;
        $sql = preg_replace('/#[^\r\n]*[\r\n]/', ' ', $sql . "\n") ?? $sql;
        return $sql;
    }

    /**
     * @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>}
     */
    public function validate(string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['ok' => false, 'errors' => ['SQL vazia'], 'warnings' => []];
        }

        if (strpos($sql, ';') !== false) {
            return ['ok' => false, 'errors' => ['Não é permitido usar ";" (apenas 1 statement).'], 'warnings' => []];
        }

        $clean = ltrim($this->stripComments($sql));
        if (!preg_match('/^SELECT\b/i', $clean)) {
            return ['ok' => false, 'errors' => ['A query deve iniciar com SELECT (CTE/WITH não permitido).'], 'warnings' => []];
        }

        $upper = strtoupper($clean);
        $blocked = [
            ' INTO OUTFILE' => 'INTO OUTFILE',
            ' INTO DUMPFILE' => 'INTO DUMPFILE',
            'LOAD_FILE(' => 'LOAD_FILE(',
            'SLEEP(' => 'SLEEP(',
            'BENCHMARK(' => 'BENCHMARK(',
            'GET_LOCK(' => 'GET_LOCK(',
            'RELEASE_LOCK(' => 'RELEASE_LOCK(',
        ];
        foreach ($blocked as $needle => $label) {
            if (strpos($upper, strtoupper($needle)) !== false) {
                return ['ok' => false, 'errors' => ["Padrão bloqueado detectado: {$label}"], 'warnings' => []];
            }
        }

        foreach (['INFORMATION_SCHEMA', 'MYSQL', 'PERFORMANCE_SCHEMA', 'SYS'] as $schema) {
            if (preg_match('/\b' . preg_quote($schema, '/') . '\s*\./i', $clean) ||
                preg_match('/`' . preg_quote(strtolower($schema), '/') . '`\s*\./i', strtolower($clean)) ||
                preg_match('/`' . preg_quote($schema, '/') . '`\s*\./i', $clean)) {
                return ['ok' => false, 'errors' => ["Acesso ao schema {$schema} é bloqueado."], 'warnings' => []];
            }
        }

        $warnings = [];
        if (!preg_match('/\bLIMIT\b/i', $clean)) {
            $warnings[] = 'Sem LIMIT (na tela o sistema aplica LIMIT automaticamente).';
        }

        return ['ok' => true, 'errors' => [], 'warnings' => $warnings];
    }

    /**
     * @return array<int,string>
     */
    public function extractNamedParams(string $sql): array
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)\b/', $sql, $matches);
        $names = $matches[1] ?? [];
        return array_values(array_unique(array_map('strval', $names)));
    }

    /**
     * @return array{0:string,1:bool,2:int,3:int}
     */
    public function applyScreenLimit(string $sql, int $limit, int $offset = 0): array
    {
        $limit = max(1, min($limit, 5000));
        $offset = max(0, $offset);

        if (preg_match('/\bLIMIT\b/i', $sql)) {
            $final = "SELECT * FROM (\n{$sql}\n) AS _dsq\nLIMIT {$limit}" . ($offset > 0 ? " OFFSET {$offset}" : '');
            return [$final, true, $limit, $offset];
        }

        $final = $sql . "\nLIMIT {$limit}" . ($offset > 0 ? " OFFSET {$offset}" : '');
        return [$final, true, $limit, $offset];
    }
}
