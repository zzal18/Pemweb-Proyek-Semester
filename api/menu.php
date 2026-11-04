<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Env;
use App\Http;
use App\MenuRepository;
use App\MenuValidator;

$method = Http::allowMethods('GET', 'POST');

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            Http::error(400, 'Parameter id harus bilangan bulat positif.');
        }
        $item = MenuRepository::find($id);
        if ($item === null) {
            Http::error(404, "Menu dengan id {$id} tidak ditemukan.");
        }
        Http::success($item);
    }

    $kategori = null;
    if (isset($_GET['kategori']) && $_GET['kategori'] !== '') {
        $kategori = is_string($_GET['kategori']) ? $_GET['kategori'] : '';
        if (!in_array($kategori, MenuRepository::KATEGORI, true)) {
            Http::error(422, 'Kategori tidak dikenal.', [
                'kategori' => 'Gunakan salah satu dari: ' . implode(', ', MenuRepository::KATEGORI) . '.',
            ]);
        }
    }

    $kunci = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : null;

    $items = MenuRepository::all($kategori, $kunci);
    Http::success($items, meta: ['total' => count($items)]);
}

// POST
Http::requireJsonContentType();      // 415
$payload = Http::readJsonObject();   // 400

$hasil = MenuValidator::validate($payload, Env::int('MAX_NAMA_MENU', 100));
if ($hasil['errors'] !== []) {       // 422
    Http::error(422, 'Data tidak memenuhi aturan validasi.', $hasil['errors']);
}

$id   = MenuRepository::create($hasil['data']);
$baru = MenuRepository::find($id);

Http::success($baru, 201, 'Menu berhasil disimpan.', ['Location' => 'menu.php?id=' . $id]);