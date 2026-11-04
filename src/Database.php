<?php
declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = self::required('DB_HOST');
        $name = self::required('DB_NAME');
        $user = self::required('DB_USER');


        if (!array_key_exists('DB_PASS', $_ENV) && getenv('DB_PASS') === false) {
            throw new RuntimeException('Konfigurasi DB_PASS belum didefinisikan pada .env');
        }
        $pass = Env::get('DB_PASS');

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    private static function required(string $key): string
    {
        $value = Env::get($key);
        if ($value === '') {
            throw new RuntimeException("Konfigurasi {$key} belum diisi pada .env");
        }
        return $value;
    }
}