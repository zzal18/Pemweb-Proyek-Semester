<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Http;

Http::allowMethods('GET');

Http::success(
    [
        'name' => 'A2 Coffee and Food API',
        'version' => '1.1.0',
        'endpoints' => [
            ['method' => 'GET',  'path' => 'api/health.php', 'deskripsi' => 'Status API'],
            ['method' => 'GET',    'path' => 'api/menu.php',         'deskripsi' => 'Daftar menu (?kategori=, ?q=, ?page=, ?per_page=)'],
            ['method' => 'GET',    'path' => 'api/menu.php?id={id}', 'deskripsi' => 'Detail satu menu'],
            ['method' => 'POST',   'path' => 'api/menu.php',         'deskripsi' => 'Tambah menu (JSON, divalidasi)'],
            ['method' => 'PUT',    'path' => 'api/menu.php?id={id}', 'deskripsi' => 'Ganti semua field menu'],
            ['method' => 'PATCH',  'path' => 'api/menu.php?id={id}', 'deskripsi' => 'Ubah sebagian field menu'],
            ['method' => 'DELETE', 'path' => 'api/menu.php?id={id}', 'deskripsi' => 'Hapus menu'],
        ],
        'dokumentasi' => 'docs/api.md',
    ],
    message: 'Selamat datang di A2 Coffee API.'
);