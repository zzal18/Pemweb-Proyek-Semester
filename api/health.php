<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Env;
use App\Http;

Http::allowMethods('GET');

Http::success(
    [
        'status' => 'ok',
        'service' => 'A2 Coffee API',
        'php' => PHP_VERSION,
        'env' => Env::get('APP_ENV', 'production'),
        'time' => date('c'),
    ],
    message: 'API berjalan normal.'
);