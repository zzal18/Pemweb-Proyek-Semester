<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Env;
use App\Http;
use App\MenuRepository;
use App\MenuValidator;

const PER_HALAMAN_BAWAAN = 10;
const PER_HALAMAN_MAKS   = 50;
const HALAMAN_MAKS       = 1000000;

function idWajib(): int
{
    if (!isset($_GET['id'])) {
        Http::error(400, 'Parameter id wajib diisi.');
    }
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        Http::error(400, 'Parameter id harus bilangan bulat positif.');
    }
    return $id;
}

function queryInt(string $nama, int $bawaan, int $maks): int
{
    if (!isset($_GET[$nama])) {
        return $bawaan;
    }
    $nilai = filter_var($_GET[$nama], FILTER_VALIDATE_INT);
    if ($nilai === false || $nilai < 1 || $nilai > $maks) {
        Http::error(400, "Parameter {$nama} harus bilangan bulat 1 sampai {$maks}.");
    }
    return $nilai;
}

$method = Http::allowMethods('GET', 'POST', 'PUT', 'PATCH', 'DELETE');

// Otorisasi: GET publik; tulis butuh login (401) dan role admin/kasir (403).
$user = null;
if ($method !== 'GET') {
    $user = Auth::requireRole(['admin', 'kasir']);
    if ($user['role'] === 'kasir' && $method !== 'PATCH') {
        Http::error(403, 'Kasir hanya boleh memperbarui stok.');
    }
}

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id   = idWajib();
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
            Http::error(400, 'Kategori tidak dikenal.', [
                'kategori' => 'Gunakan salah satu dari: ' . implode(', ', MenuRepository::KATEGORI) . '.',
            ]);
        }
    }

    $kunci = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : null;

    if (isset($_GET['page']) || isset($_GET['per_page'])) {
        $page    = queryInt('page', 1, HALAMAN_MAKS);
        $perPage = queryInt('per_page', PER_HALAMAN_BAWAAN, PER_HALAMAN_MAKS);

        $total = MenuRepository::count($kategori, $kunci);
        $items = MenuRepository::all($kategori, $kunci, $perPage, ($page - 1) * $perPage);

        Http::success($items, meta: [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    $items = MenuRepository::all($kategori, $kunci);
    Http::success($items, meta: ['total' => count($items)]);
}

if ($method === 'POST') {
    Http::requireJsonContentType();
    $payload = Http::readJsonObject();

    $hasil = MenuValidator::validate($payload, Env::int('MAX_NAMA_MENU', 100));
    if ($hasil['errors'] !== []) {
        Http::error(422, 'Data tidak memenuhi aturan validasi.', $hasil['errors']);
    }

    $id   = MenuRepository::create($hasil['data']);
    $baru = MenuRepository::find($id);

    Http::success($baru, 201, 'Menu berhasil disimpan.', ['Location' => 'menu.php?id=' . $id]);
}

if ($method === 'DELETE') {
    $id = idWajib();

    try {
        $terhapus = MenuRepository::delete($id);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            Http::error(409, 'Menu tidak dapat dihapus karena sudah dipakai pada pesanan.');
        }
        throw $e;
    }

    if (!$terhapus) {
        Http::error(404, "Menu dengan id {$id} tidak ditemukan.");
    }
    Http::noContent();
}

// PUT / PATCH
$id = idWajib();
Http::requireJsonContentType();
$payload = Http::readJsonObject();

// Kasir hanya boleh mengirim field "stok" saja.
if ($user['role'] === 'kasir' && array_keys($payload) !== ['stok']) {
    Http::error(403, 'Kasir hanya boleh mengubah field stok.');
}

$maxNama = Env::int('MAX_NAMA_MENU', 100);
$hasil = Database::transaction(static function () use ($method, $id, $payload, $maxNama): array {
    $saatIni = MenuRepository::find($id, true);
    if ($saatIni === null) {
        return ['status' => 404];
    }

    $input = $method === 'PATCH'
        ? array_merge(MenuRepository::editable($saatIni), $payload)
        : $payload;

    $validasi = MenuValidator::validate($input, $maxNama);
    if ($validasi['errors'] !== []) {
        return ['status' => 422, 'errors' => $validasi['errors']];
    }

    MenuRepository::update($id, $validasi['data']);
    return ['status' => 200, 'item' => MenuRepository::find($id)];
});

if ($hasil['status'] === 404) {
    Http::error(404, "Menu dengan id {$id} tidak ditemukan.");
}
if ($hasil['status'] === 422) {
    Http::error(422, 'Data tidak memenuhi aturan validasi.', $hasil['errors']);
}
Http::success($hasil['item'], 200, 'Menu berhasil diperbarui.');