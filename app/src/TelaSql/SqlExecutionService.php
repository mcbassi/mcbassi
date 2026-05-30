<?php
declare(strict_types=1);

namespace App\TelaSql;

use App\Infra\Database;
use App\Infra\Env;
use PDO;
use RuntimeException;
use Throwable;

final class SqlExecutionService
{
    public function __construct(
        private readonly Database $database,
        private readonly SqlGuard $guard = new SqlGuard()
    ) {
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function execute(string $sql, array $params = [], string $mode = 'screen', int $limit = 200, int $offset = 0): array
    {
        $validation = $this->guard->validate($sql);
        if (!$validation['ok']) {
            throw new RuntimeException(implode(' | ', $validation['errors']));
        }
        if (!is_array($params)) {
            throw new RuntimeException('params deve ser objeto.');
        }

        $named = $this->guard->extractNamedParams($sql);
        foreach ($named as $name) {
            if (!array_key_exists($name, $params)) {
                throw new RuntimeException("Parâmetro ausente: :{$name}");
            }
        }

        $sqlToRun = $sql;
        $usedLimit = null;
        $usedOffset = null;
        $truncated = false;

        if ($mode === 'screen') {
            [$sqlToRun, , $usedLimit, $usedOffset] = $this->guard->applyScreenLimit($sql, $limit, $offset);
            $truncated = true;
        } elseif ($mode === 'full') {
            $truncated = false;
        } else {
            throw new RuntimeException('mode inválido (screen|full).');
        }

        $pdo = $this->targetPdo();
        $start = microtime(true);
        try {
            $stmt = $pdo->prepare($sqlToRun);
            foreach ($named as $name) {
                $value = $params[$name];
                if (is_int($value)) {
                    $stmt->bindValue(':' . $name, $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue(':' . $name, $value ? 1 : 0, PDO::PARAM_INT);
                } elseif ($value === null) {
                    $stmt->bindValue(':' . $name, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':' . $name, (string) $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            $columns = $rows !== [] ? array_keys($rows[0]) : [];

            return [
                'ok' => true,
                'columns' => $columns,
                'rows' => $rows,
                'row_count' => count($rows),
                'elapsed_ms' => $elapsed,
                'mode' => $mode,
                'limit' => $usedLimit,
                'offset' => $usedOffset,
                'truncated' => $truncated,
                'sql_run' => $sqlToRun,
            ];
        } catch (Throwable $exception) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            throw new RuntimeException('Erro ao executar: ' . $exception->getMessage() . ' | tempo=' . $elapsed . 'ms', 0, $exception);
        }
    }

    private function targetPdo(): PDO
    {
        $driver = Env::get('TELA_SQL_TARGET_DB_DRIVER', Env::get('DB_DRIVER', 'mysql'));
        if ($driver !== 'mysql') {
            return $this->database->pdo();
        }

        $host = Env::get('TELA_SQL_TARGET_DB_HOST', Env::get('DB_HOST', '127.0.0.1'));
        $port = Env::get('TELA_SQL_TARGET_DB_PORT', Env::get('DB_PORT', '3306'));
        $name = Env::get('TELA_SQL_TARGET_DB_NAME', Env::get('DB_NAME', 'form_app'));
        $user = Env::get('TELA_SQL_TARGET_DB_USER', Env::get('DB_USER', 'root'));
        $pass = Env::get('TELA_SQL_TARGET_DB_PASS', Env::get('DB_PASS', ''));
        $charset = Env::get('TELA_SQL_TARGET_DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $name, $charset);
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }
}
