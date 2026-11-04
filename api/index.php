<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Http;

Http::allowMethods('GET');

Http::success(
    [
        'name' => 'A2 Coffee and Food API',
        'version' => '1.0.0',
        'endpoints' => [
            ['method' => 'GET',  'path' => 'api/health.php', 'deskripsi' => 'Status API'],
            ['method' => 'GET',  'path' => 'api/menu.php',   'deskripsi' => 'Daftar menu (?kategori=, ?q=, ?id=)'],
            ['method' => 'POST', 'path' => 'api/menu.php',   'deskripsi' => 'Tambah menu (JSON, divalidasi)'],
        ],
        'dokumentasi' => 'docs/API.md',
    ],
    message: 'Selamat datang di A2 Coffee API.'
);