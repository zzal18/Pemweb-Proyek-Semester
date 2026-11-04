<?php
declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

final class Env
{
    public static function load(string $dir): void
    {
        if (class_exists(Dotenv::class)) {
            Dotenv::createImmutable($dir)->safeLoad();
            return;
        }

        $file = $dir . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] ??= trim($value, " \t\"'");
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : (string) $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = filter_var(self::get($key), FILTER_VALIDATE_INT);
        return $value === false ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $value ?? $default;
    }
}