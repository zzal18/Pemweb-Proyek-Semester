<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Http;

Http::allowMethods('GET');

Http::success(
    [
        'status' => 'ok',
        'service' => 'A2 Coffee API',
        'time' => date('c'),
    ],
    message: 'API berjalan normal.'
);