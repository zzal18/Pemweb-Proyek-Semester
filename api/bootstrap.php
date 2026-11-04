<?php
declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';
        if (str_starts_with($class, 'App\\') && is_file($file)) {
            require $file;
        }
    });
}

use App\Env;
use App\Http;

Env::load($root);

$debug = Env::bool('APP_DEBUG') && Env::get('APP_ENV', 'production') === 'local';
ini_set('display_errors', '0');
error_reporting(E_ALL);

Http::jsonHeaders();

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    $errors = $debug ? ['debug' => $e::class . ': ' . $e->getMessage()] : [];
    if (!headers_sent()) {
        Http::error(500, 'Terjadi kesalahan pada server.', $errors);
    }
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});