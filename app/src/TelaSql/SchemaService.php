<?php
declare(strict_types=1);

namespace App\TelaSql;

use App\Infra\Database;
use App\Infra\Env;
use PDO;

final class SchemaService
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function inspect(): array
    {
        $pdo = $this->targetPdo();
        $dbName = Env::get('TELA_SQL_TARGET_DB_NAME', Env::get('DB_NAME', 'form_app')) ?? 'form_app';
        $dbHost = Env::get('TELA_SQL_TARGET_DB_HOST', Env::get('DB_HOST', '127.0.0.1')) ?? '127.0.0.1';
        $dbUser = Env::get('TELA_SQL_TARGET_DB_USER', Env::get('DB_USER', 'root')) ?? 'root';

        $tablesStmt = $pdo->prepare("\n            SELECT TABLE_NAME\n            FROM INFORMATION_SCHEMA.TABLES\n            WHERE TABLE_SCHEMA = ?\n            ORDER BY TABLE_NAME\n        ");
        $tablesStmt->execute([$dbName]);
        $tables = array_map(static fn(array $row): string => (string) ($row['TABLE_NAME'] ?? ''), $tablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $colsStmt = $pdo->prepare("\n            SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_TYPE\n            FROM INFORMATION_SCHEMA.COLUMNS\n            WHERE TABLE_SCHEMA = ?\n            ORDER BY TABLE_NAME, ORDINAL_POSITION\n        ");
        $colsStmt->execute([$dbName]);
        $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byTable = [];
        foreach ($cols as $col) {
            $table = (string) ($col['TABLE_NAME'] ?? '');
            if ($table === '') {
                continue;
            }
            $byTable[$table] ??= [];
            $byTable[$table][] = [
                'name' => (string) ($col['COLUMN_NAME'] ?? ''),
                'data_type' => (string) ($col['DATA_TYPE'] ?? ''),
                'column_type' => (string) ($col['COLUMN_TYPE'] ?? ''),
                'nullable' => (string) ($col['IS_NULLABLE'] ?? '') === 'YES',
                'key' => (string) ($col['COLUMN_KEY'] ?? ''),
            ];
        }

        $fkStmt = $pdo->prepare("\n            SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME\n            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE\n            WHERE TABLE_SCHEMA = ?\n              AND REFERENCED_TABLE_NAME IS NOT NULL\n            ORDER BY TABLE_NAME, COLUMN_NAME\n        ");
        $fkStmt->execute([$dbName]);
        $fks = $fkStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'ok' => true,
            'target_db' => [
                'db_name' => $dbName,
                'db_host' => $dbHost,
                'db_user' => $dbUser,
            ],
            'tables' => array_values(array_filter($tables)),
            'columns' => $byTable,
            'foreign_keys' => $fks,
        ];
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
