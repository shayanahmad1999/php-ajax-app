<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database {
    private PDO $pdo;
    private string $driver;

    public function __construct() {
        $this->driver = getenv('DB_DRIVER') ?: 'pgsql';

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: ($this->driver === 'pgsql' ? '5432' : '3306');
        $db   = getenv('DB_NAME') ?: 'php_ajax';
        $user = getenv('DB_USER') ?: 'postgres';
        $pass = getenv('DB_PASS') ?: '1013';

        if ($this->driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public function pdo(): PDO {
        return $this->pdo;
    }

    public function driver(): string {
        return $this->driver;
    }
}