<?php
declare(strict_types=1);

/**
 * Test otomatis CRUD API menu (Modul 11).
 *
 * Prasyarat: Laragon (Apache + MySQL) aktif, database sudah di-seed, dan akun admin sudah dibuat:
 *   php database/buat_admin.php "Admin A2" admin@a2coffee.test PasswordKuat123
 *
 * Jalankan (PowerShell):
 *   $env:ADMIN_EMAIL="admin@a2coffee.test"; $env:ADMIN_PASSWORD="PasswordKuat123"; php tests/api_test.php
 *
 * Opsional untuk uji 403 (akun role kasir):
 *   php database/buat_admin.php "Kasir A2" kasir@a2coffee.test PasswordKasir123 kasir
 *   $env:KASIR_EMAIL="kasir@a2coffee.test"; $env:KASIR_PASSWORD="PasswordKasir123"
 *
 * Argumen opsional: URL menu.php (default http://localhost/pemweb-obe/api/menu.php).
 * Data uji dibuat lalu dihapus kembali oleh skrip ini.
 */

$base = rtrim($argv[1] ?? 'http://localhost/pemweb-obe/api/menu.php', '/');
$health = preg_replace('#menu\.php$#', 'health.php', $base);
$auth = preg_replace('#menu\.php$#', 'auth.php', $base);

$adminEmail = getenv('ADMIN_EMAIL') ?: '';
$adminPass  = getenv('ADMIN_PASSWORD') ?: '';
if ($adminEmail === '' || $adminPass === '') {
    fwrite(STDERR, "Set ADMIN_EMAIL dan ADMIN_PASSWORD dulu (lihat petunjuk di bagian atas file ini).\n");
    exit(2);
}

$cookie = '';   // cookie sesi, meniru browser

function request(string $method, string $url, ?string $body = null, string $contentType = 'application/json'): array
{
    global $cookie;
    $headers = "Accept: application/json\r\n";
    if ($cookie !== '') {
        $headers .= "Cookie: {$cookie}\r\n";
    }
    if ($body !== null) {
        $headers .= "Content-Type: {$contentType}\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $body ?? '',
        'ignore_errors' => true, 'timeout' => 10,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || !isset($http_response_header)) {
        fwrite(STDERR, "Tidak dapat menghubungi {$url}. Pastikan Laragon (Apache + MySQL) aktif.\n");
        exit(2);
    }
    foreach ($http_response_header as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) {
            $cookie = trim(explode(';', substr($h, 11))[0]);
        }
    }
    preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m);
    return ['status' => (int) $m[1], 'raw' => $raw, 'json' => json_decode($raw, true)];
}

$hasil = [];
function cek(string $id, string $nama, bool $lulus, string $detail = ''): void
{
    global $hasil;
    $hasil[] = [$id, $nama, $lulus];
    printf("[%s] %-4s %s%s\n", $lulus ? 'PASS' : 'FAIL', $id, $nama, $lulus || $detail === '' ? '' : "  -> {$detail}");
}

function json(array $d): string { return json_encode($d, JSON_UNESCAPED_UNICODE); }

$valid = ['nama' => 'Uji CRUD Latte', 'kategori' => 'Minuman', 'harga' => 15000, 'stok' => 7, 'deskripsi' => 'Data uji otomatis'];

// ---------- AKSES (tanpa login) ----------
$cookie = '';
$r = request('GET', $base);
cek('A1', 'GET menu tanpa login tetap publik -> 200', $r['status'] === 200, "status {$r['status']}");

// Uji tanpa login sengaja TIDAK menyentuh data asli: body POST sengaja tidak valid dan id=999999
// tidak ada. Jika pengaman admin belum terpasang, hasilnya 422/404 (uji FAIL) tanpa mengubah data.
$total = (int) (request('GET', $base)['json']['meta']['total'] ?? -1);

$r = request('POST', $base, json(['nama' => '']));
cek('A2', 'POST tanpa login -> 401', $r['status'] === 401, "status {$r['status']}");
$r = request('PUT', "{$base}?id=999999", json($valid));
cek('A3', 'PUT tanpa login -> 401', $r['status'] === 401, "status {$r['status']}");
$r = request('PATCH', "{$base}?id=999999", json(['harga' => 1]));
cek('A4', 'PATCH tanpa login -> 401', $r['status'] === 401, "status {$r['status']}");
$r = request('DELETE', "{$base}?id=999999");
cek('A5', 'DELETE tanpa login -> 401', $r['status'] === 401, "status {$r['status']}");
$r = request('GET', $base);
cek('A6', 'Jumlah menu tidak berubah setelah percobaan tulis tanpa login', ($r['json']['meta']['total'] ?? -2) === $total, "total sebelum {$total}, sesudah " . ($r['json']['meta']['total'] ?? '?'));

$r = request('GET', "{$auth}?aksi=me");
cek('A7', 'me tanpa login -> 200, login=false', $r['status'] === 200 && ($r['json']['data']['login'] ?? null) === false, "status {$r['status']}");

$r = request('POST', "{$auth}?aksi=login", json(['email' => '', 'password' => '']));
cek('A8', 'Login field kosong -> 422', $r['status'] === 422 && isset($r['json']['errors']['email']), "status {$r['status']}");

$r = request('POST', "{$auth}?aksi=login", json(['email' => $adminEmail, 'password' => 'password-salah-xyz']));
cek('A9', 'Login password salah -> 401 pesan generik', $r['status'] === 401 && ($r['json']['message'] ?? '') === 'Email atau password salah.', "status {$r['status']}");

$r = request('POST', "{$auth}?aksi=login", json(['email' => 'tidak.ada@contoh.com', 'password' => 'password-salah-xyz']));
cek('A10', 'Login email tidak terdaftar -> 401 dengan pesan yang sama', $r['status'] === 401 && ($r['json']['message'] ?? '') === 'Email atau password salah.', "status {$r['status']}");

$r = request('POST', "{$auth}?aksi=login", 'email=x', 'application/x-www-form-urlencoded');
cek('A11', 'Login Content-Type salah -> 415', $r['status'] === 415, "status {$r['status']}");

// ---------- AKSES (role kasir, opsional) ----------
$kasirEmail = getenv('KASIR_EMAIL') ?: '';
$kasirPass  = getenv('KASIR_PASSWORD') ?: '';
if ($kasirEmail !== '' && $kasirPass !== '') {
    $cookie = '';
    $r = request('POST', "{$auth}?aksi=login", json(['email' => $kasirEmail, 'password' => $kasirPass]));
    cek('K1', 'Login kasir -> 200', $r['status'] === 200 && ($r['json']['data']['role'] ?? '') === 'kasir', "status {$r['status']}");
    $r = request('POST', $base, json(['nama' => '']));
    cek('K2', 'POST oleh kasir -> 403', $r['status'] === 403, "status {$r['status']}");
    $r = request('DELETE', "{$base}?id=999999");
    cek('K3', 'DELETE oleh kasir -> 403', $r['status'] === 403, "status {$r['status']}");
    $r = request('GET', $base);
    cek('K4', 'GET menu oleh kasir tetap 200', $r['status'] === 200, "status {$r['status']}");
} else {
    echo "[SKIP] K1-K4 uji role kasir dilewati (KASIR_EMAIL / KASIR_PASSWORD belum di-set)\n";
}

// ---------- LOGIN ADMIN ----------
$cookie = '';
$r = request('POST', "{$auth}?aksi=login", json(['email' => $adminEmail, 'password' => $adminPass]));
cek('A12', 'Login admin benar -> 200, role admin', $r['status'] === 200 && ($r['json']['data']['role'] ?? '') === 'admin' && !isset($r['json']['data']['password_hash']), "status {$r['status']}");
if ($r['status'] !== 200) {
    fwrite(STDERR, "Login admin gagal; uji CRUD dihentikan. Cek ADMIN_EMAIL/ADMIN_PASSWORD dan jalankan database/buat_admin.php.\n");
    exit(2);
}
$r = request('GET', "{$auth}?aksi=me");
cek('A13', 'me setelah login -> login=true', ($r['json']['data']['login'] ?? false) === true && ($r['json']['data']['user']['role'] ?? '') === 'admin');

// ---------- CREATE ----------
$r = request('POST', $base, json($valid));
cek('C1', 'POST valid -> 201 + data baru', $r['status'] === 201 && ($r['json']['data']['nama'] ?? '') === 'Uji CRUD Latte', "status {$r['status']}");
$id = (int) ($r['json']['data']['id'] ?? 0);

// ---------- KASIR: izin terbatas (hanya stok) ----------
if ($kasirEmail !== '' && $kasirPass !== '') {
    $cookieAdmin = $cookie;   // simpan sesi admin
    $cookie = '';
    request('POST', "{$auth}?aksi=login", json(['email' => $kasirEmail, 'password' => $kasirPass]));

    $r = request('PATCH', "{$base}?id={$id}", json(['stok' => 5]));
    cek('K5', 'PATCH stok oleh kasir -> 200', $r['status'] === 200 && ($r['json']['data']['stok'] ?? -1) === 5, "status {$r['status']}");

    $r = request('PATCH', "{$base}?id={$id}", json(['harga' => 1]));
    cek('K6', 'PATCH harga oleh kasir -> 403', $r['status'] === 403, "status {$r['status']}");

    $r = request('PUT', "{$base}?id={$id}", json($valid));
    cek('K7', 'PUT oleh kasir -> 403', $r['status'] === 403, "status {$r['status']}");

    $cookie = $cookieAdmin;   // kembali ke sesi admin
}

$r = request('POST', $base, json(['nama' => '', 'kategori' => 'Minuman', 'harga' => 1, 'stok' => 1]));
cek('C2', 'POST nama kosong -> 422 + errors.nama', $r['status'] === 422 && isset($r['json']['errors']['nama']), "status {$r['status']}");

$r = request('POST', $base, json(['nama' => 'X', 'kategori' => 'Minuman', 'harga' => -5, 'stok' => 1]));
cek('C3', 'POST harga negatif -> 422 + errors.harga', $r['status'] === 422 && isset($r['json']['errors']['harga']), "status {$r['status']}");

$r = request('POST', $base, json(['nama' => 'X', 'kategori' => 'Seafood', 'harga' => 1, 'stok' => 1]));
cek('C4', 'POST kategori tidak dikenal -> 422', $r['status'] === 422 && isset($r['json']['errors']['kategori']), "status {$r['status']}");

$r = request('POST', $base, '{"nama": "rusak",');
cek('C5', 'POST JSON rusak -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('POST', $base, 'nama=x', 'application/x-www-form-urlencoded');
cek('C6', 'POST Content-Type salah -> 415', $r['status'] === 415, "status {$r['status']}");

$injeksi = "x'); DROP TABLE menu;--";
$r = request('POST', $base, json(['nama' => $injeksi] + $valid));
$idInj = (int) ($r['json']['data']['id'] ?? 0);
cek('C7', 'POST string SQL injection tersimpan sebagai teks biasa', $r['status'] === 201 && ($r['json']['data']['nama'] ?? '') === $injeksi, "status {$r['status']}");
if ($idInj > 0) { request('DELETE', "{$base}?id={$idInj}"); }
$r = request('GET', $base);
cek('C8', 'Tabel menu masih ada setelah uji injection', $r['status'] === 200);

// ---------- READ ----------
$r = request('GET', $base);
cek('R1', 'GET collection -> 200 + array + meta.total', $r['status'] === 200 && is_array($r['json']['data'] ?? null) && isset($r['json']['meta']['total']), "status {$r['status']}");

$r = request('GET', "{$base}?id={$id}");
cek('R2', 'GET by id -> 200', $r['status'] === 200 && ($r['json']['data']['id'] ?? 0) === $id, "status {$r['status']}");

$r = request('GET', "{$base}?id=999999");
cek('R3', 'GET id tidak ada -> 404', $r['status'] === 404 && ($r['json']['status'] ?? '') === 'error', "status {$r['status']}");

$r = request('GET', "{$base}?id=abc");
cek('R4', 'GET id bukan angka -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('GET', "{$base}?kategori=Minuman");
$semuaMinuman = $r['status'] === 200 && array_reduce($r['json']['data'], fn($ok, $i) => $ok && $i['kategori'] === 'Minuman', true);
cek('R5', 'GET filter kategori=Minuman -> semua item Minuman', $semuaMinuman);

$r = request('GET', "{$base}?kategori=Seafood");
cek('R6', 'GET kategori tidak dikenal -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('GET', "{$base}?q=" . rawurlencode('Uji CRUD'));
cek('R7', 'GET pencarian q -> menemukan data uji', $r['status'] === 200 && count($r['json']['data']) >= 1, "status {$r['status']}");

$r = request('GET', "{$base}?q=" . rawurlencode("' OR 1=1 --"));
cek('R8', 'GET q berisi injection -> 200 dan 0 hasil', $r['status'] === 200 && count($r['json']['data']) === 0);

$r = request('GET', "{$base}?page=1&per_page=2");
$m = $r['json']['meta'] ?? [];
cek('R9', 'GET pagination page=1&per_page=2 -> maks 2 item + meta lengkap',
    $r['status'] === 200 && count($r['json']['data']) <= 2 && isset($m['total'], $m['page'], $m['per_page'], $m['total_pages']) && $m['per_page'] === 2);

$r = request('GET', "{$base}?page=1&per_page=0");
cek('R10', 'GET per_page=0 -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('GET', "{$base}?page=abc");
cek('R11', 'GET page=abc -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('GET', "{$base}?page=99999&per_page=2");
cek('R12', 'GET page melebihi total -> 200 dan data kosong', $r['status'] === 200 && $r['json']['data'] === []);

// ---------- UPDATE ----------
$ubah = ['nama' => 'Uji CRUD Latte Besar', 'kategori' => 'Cemilan', 'harga' => 18000, 'stok' => 0, 'deskripsi' => 'Diubah'];
$r = request('PUT', "{$base}?id={$id}", json($ubah));
cek('U1', 'PUT valid -> 200 + data berubah', $r['status'] === 200 && ($r['json']['data']['nama'] ?? '') === 'Uji CRUD Latte Besar' && ($r['json']['data']['kategori'] ?? '') === 'Cemilan' && ($r['json']['data']['status'] ?? '') === 'Habis', "status {$r['status']}");

$r = request('PATCH', "{$base}?id={$id}", json(['harga' => 20000]));
cek('U2', 'PATCH sebagian -> 200, hanya harga berubah', $r['status'] === 200 && ($r['json']['data']['harga'] ?? 0) === 20000 && ($r['json']['data']['nama'] ?? '') === 'Uji CRUD Latte Besar', "status {$r['status']}");

$r = request('PUT', "{$base}?id={$id}", json(['nama' => '', 'kategori' => 'Minuman', 'harga' => 'abc', 'stok' => 1]));
cek('U3', 'PUT tidak valid -> 422 + errors per field', $r['status'] === 422 && isset($r['json']['errors']['nama'], $r['json']['errors']['harga']), "status {$r['status']}");

$r = request('GET', "{$base}?id={$id}");
cek('U4', 'Data tidak berubah setelah update gagal', ($r['json']['data']['harga'] ?? 0) === 20000);

$r = request('PUT', "{$base}?id=999999", json($ubah));
cek('U5', 'PUT id tidak ada -> 404', $r['status'] === 404, "status {$r['status']}");

$r = request('PATCH', "{$base}?id={$id}", '{}');
cek('U6', 'PATCH body kosong -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('PUT', $base, json($ubah));
cek('U7', 'PUT tanpa id -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('PATCH', "{$base}?id={$id}", json(['stok' => -1]));
cek('U8', 'PATCH stok negatif -> 422', $r['status'] === 422 && isset($r['json']['errors']['stok']), "status {$r['status']}");

// ---------- DELETE ----------
$r = request('DELETE', "{$base}?id={$id}");
cek('D1', 'DELETE -> 204 tanpa body', $r['status'] === 204 && $r['raw'] === '', "status {$r['status']}");

$r = request('GET', "{$base}?id={$id}");
cek('D2', 'GET setelah DELETE -> 404', $r['status'] === 404, "status {$r['status']}");

$r = request('DELETE', "{$base}?id={$id}");
cek('D3', 'DELETE ulang (id sudah hilang) -> 404', $r['status'] === 404, "status {$r['status']}");

$r = request('DELETE', $base);
cek('D4', 'DELETE tanpa id -> 400', $r['status'] === 400, "status {$r['status']}");

$r = request('DELETE', "{$base}?id=abc");
cek('D5', 'DELETE id bukan angka -> 400', $r['status'] === 400, "status {$r['status']}");

// ---------- UMUM ----------
$r = request('DELETE', $health);
cek('E1', 'Method tidak diizinkan (DELETE health.php) -> 405', $r['status'] === 405, "status {$r['status']}");

$jsonSemua = true;
foreach ([request('GET', "{$base}?id=999999"), request('POST', $base, '{"x":')] as $e) {
    $jsonSemua = $jsonSemua && ($e['json']['status'] ?? '') === 'error' && isset($e['json']['message']);
}
cek('E2', 'Semua respons error berformat {status:error, message}', $jsonSemua);

// ---------- LOGOUT ----------
$r = request('POST', "{$auth}?aksi=logout");
cek('A14', 'Logout -> 200', $r['status'] === 200, "status {$r['status']} " . substr($r['raw'], 0, 300));
$r = request('POST', $base, json(['nama' => '']));
cek('A15', 'POST setelah logout -> 401', $r['status'] === 401, "status {$r['status']}");
$r = request('GET', "{$auth}?aksi=me");
cek('A16', 'me setelah logout -> login=false', ($r['json']['data']['login'] ?? true) === false);

// ---------- RINGKASAN ----------
$gagal = count(array_filter($hasil, fn($h) => !$h[2]));
printf("\nTotal %d uji, %d lulus, %d gagal.\n", count($hasil), count($hasil) - $gagal, $gagal);
printf("Catatan: uji 409 (hapus menu yang dipakai pesanan) dan uji 500 generik dilakukan manual, lihat docs/test-matrix.md.\n");
exit($gagal === 0 ? 0 : 1); 