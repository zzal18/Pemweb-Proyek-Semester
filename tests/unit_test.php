<?php
declare(strict_types=1);

/**
 * Unit test tanpa database (Modul 14): MenuValidator dan Env.
 * Aman dijalankan di CI maupun Laragon: tidak butuh Apache/MySQL.
 *
 * Jalankan:  php tests/unit_test.php
 * Exit code 0 = semua lulus, 1 = ada yang gagal.
 */

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Env;
use App\MenuValidator;

$hasil = [];

function cek(string $id, string $nama, bool $lulus, string $detail = ''): void
{
    global $hasil;
    $hasil[] = $lulus;
    printf("%-5s %-6s %s%s\n", $lulus ? 'PASS' : 'FAIL', $id, $nama, $lulus ? '' : "  -> {$detail}");
}

function validasi(array $payload, int $maxNama = 100): array
{
    return MenuValidator::validate($payload, $maxNama)['errors'];
}

$valid = ['nama' => 'Es Teh Manis', 'kategori' => 'Minuman', 'harga' => 5000, 'stok' => 10, 'deskripsi' => 'Segar'];

// ---------- Happy path ----------
cek('U1', 'Payload valid -> tanpa error', validasi($valid) === [], json_encode(validasi($valid)));

$h = MenuValidator::validate(['nama' => '  Kopi Susu  ', 'kategori' => 'Minuman', 'harga' => '15000', 'stok' => '3'], 100);
cek('U2', 'Nama di-trim dan angka berbentuk string dikonversi ke int',
    $h['errors'] === [] && $h['data']['nama'] === 'Kopi Susu' && $h['data']['harga'] === 15000 && $h['data']['stok'] === 3,
    json_encode($h));

// ---------- Invalid input (negative test) ----------
cek('U3', 'Nama kosong -> errors.nama', isset(validasi(['nama' => ''] + $valid)['nama']));
cek('U4', 'Nama hanya spasi -> errors.nama', isset(validasi(['nama' => '   '] + $valid)['nama']));
cek('U5', 'Nama melebihi batas -> errors.nama', isset(validasi(['nama' => str_repeat('a', 101)] + $valid, 100)['nama']));
cek('U6', 'Nama berupa array (bukan teks) -> errors.nama', isset(validasi(['nama' => ['x']] + $valid)['nama']));
cek('U7', 'Kategori tidak dikenal -> errors.kategori', isset(validasi(['kategori' => 'Seafood'] + $valid)['kategori']));
cek('U8', 'Kategori beda huruf besar/kecil ("minuman") ditolak', isset(validasi(['kategori' => 'minuman'] + $valid)['kategori']));
cek('U9', 'Harga negatif -> errors.harga', isset(validasi(['harga' => -5] + $valid)['harga']));
cek('U10', 'Harga desimal ("10.5") -> errors.harga', isset(validasi(['harga' => '10.5'] + $valid)['harga']));
cek('U11', 'Stok berupa teks "abc" -> errors.stok', isset(validasi(['stok' => 'abc'] + $valid)['stok']));
cek('U12', 'Stok string negatif ("-1") -> errors.stok', isset(validasi(['stok' => '-1'] + $valid)['stok']));
cek('U13', 'Deskripsi > 255 karakter -> errors.deskripsi', isset(validasi(['deskripsi' => str_repeat('d', 256)] + $valid)['deskripsi']));
cek('U14', 'Deskripsi bukan teks (array) -> errors.deskripsi', isset(validasi(['deskripsi' => ['x']] + $valid)['deskripsi']));

// ---------- Boundary ----------
cek('U15', 'Nama tepat di batas (100) lolos', !isset(validasi(['nama' => str_repeat('a', 100)] + $valid, 100)['nama']));
cek('U16', 'Harga 0 dan stok 0 lolos (menu habis / gratis)', validasi(['harga' => 0, 'stok' => 0] + $valid) === []);

// ---------- Input berbahaya diperlakukan sebagai teks ----------
$h = MenuValidator::validate(['nama' => "x'); DROP TABLE menu;--"] + $valid, 100);
cek('U17', 'Teks SQL injection lolos validasi sebagai teks biasa (pengamanan ada di prepared statement)',
    $h['errors'] === [] && $h['data']['nama'] === "x'); DROP TABLE menu;--");

// ---------- Env ----------
putenv('UJI_BOOL=true');
$_ENV['UJI_BOOL'] = 'true';
$_ENV['UJI_INT'] = 'abc';
$_ENV['UJI_INT_OK'] = '42';
cek('U18', 'Env::bool("true") -> true', Env::bool('UJI_BOOL') === true);
cek('U19', 'Env::bool kunci tidak ada -> default false', Env::bool('UJI_TIDAK_ADA') === false);
cek('U20', 'Env::int nilai bukan angka -> pakai default', Env::int('UJI_INT', 100) === 100);
cek('U21', 'Env::int nilai angka -> terbaca', Env::int('UJI_INT_OK', 100) === 42);

// ---------- Ringkasan ----------
$gagal = count(array_filter($hasil, fn($ok) => !$ok));
printf("\nTotal %d uji, %d lulus, %d gagal.\n", count($hasil), count($hasil) - $gagal, $gagal);
exit($gagal === 0 ? 0 : 1);