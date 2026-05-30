<?php
declare(strict_types=1);

namespace App\Infra;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;
    private ?PDO $statisticsPdo = null;

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = Env::get('DB_DRIVER', 'mysql');

        try {
            if ($driver === 'sqlite') {
                $sqlitePath = Env::get('DB_SQLITE_PATH', 'storage/app/demo.sqlite');
                $absolute = str_starts_with($sqlitePath, '/') ? $sqlitePath : dirname(__DIR__, 3) . '/' . ltrim($sqlitePath, '/');
                $this->pdo = new PDO('sqlite:' . $absolute);
            } else {
                $this->pdo = $this->connectByPrefix('DB_');
            }

            $this->configurePdo($this->pdo);

            return $this->pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('Falha ao conectar no banco principal: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function statisticsPdo(): PDO
    {
        if ($this->statisticsPdo instanceof PDO) {
            return $this->statisticsPdo;
        }

        try {
            $driver = Env::get('STAT_DB_DRIVER', Env::get('DB_DRIVER', 'mysql'));

            if ($driver === 'sqlite') {
                $sqlitePath = Env::get('STAT_DB_SQLITE_PATH', Env::get('DB_SQLITE_PATH', 'storage/app/demo.sqlite'));
                $absolute = str_starts_with($sqlitePath, '/') ? $sqlitePath : dirname(__DIR__, 3) . '/' . ltrim($sqlitePath, '/');
                $this->statisticsPdo = new PDO('sqlite:' . $absolute);
            } else {
                $this->statisticsPdo = $this->connectByPrefix('STAT_DB_');
            }

            $this->configurePdo($this->statisticsPdo);

            return $this->statisticsPdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('Falha ao conectar no banco statistics: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function connectByPrefix(string $prefix): PDO
    {
        $isStatistics = $prefix === 'STAT_DB_';

        $driver = Env::get($prefix . 'DRIVER', Env::get('DB_DRIVER', 'mysql'));
        $host = Env::get($prefix . 'HOST', Env::get('DB_HOST', '127.0.0.1'));
        $port = Env::get($prefix . 'PORT', Env::get('DB_PORT', '3306'));
        $db = Env::get($prefix . 'NAME', $isStatistics ? 'statistics' : Env::get('DB_NAME', 'form_app'));
        $charset = Env::get($prefix . 'CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));
        $user = Env::get($prefix . 'USER', Env::get('DB_USER', 'root'));
        $pass = Env::get($prefix . 'PASS', Env::get('DB_PASS', ''));

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $db, $charset);
        return new PDO($dsn, $user, $pass);
    }

    private function configurePdo(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
}
