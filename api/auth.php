<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Http;

$aksi = isset($_GET['aksi']) && is_string($_GET['aksi']) ? $_GET['aksi'] : '';

switch ($aksi) {
    // Status sesi. Selalu 200 agar halaman publik tidak memunculkan error 401 di console.
    case 'me':
        Http::allowMethods('GET');
        $user = Auth::currentUser();
        Http::success(['login' => $user !== null, 'user' => $user]);

    case 'login':
        Http::allowMethods('POST');
        Http::requireJsonContentType();
        $payload = Http::readJsonObject();

        $email    = is_string($payload['email'] ?? null) ? trim($payload['email']) : '';
        $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';

        $errors = [];
        if ($email === '' || mb_strlen($email) > 190) {
            $errors['email'] = 'Email wajib diisi (maks 190 karakter).';
        }
        if ($password === '' || strlen($password) > 200) {
            $errors['password'] = 'Password wajib diisi (maks 200 karakter).';
        }
        if ($errors !== []) {
            Http::error(422, 'Data login tidak lengkap.', $errors);
        }

        Auth::mulai();
        $sisa = Auth::sisaKunci();
        if ($sisa > 0) {
            Http::error(429, "Terlalu banyak percobaan login. Coba lagi dalam {$sisa} detik.", headers: ['Retry-After' => (string) $sisa]);
        }

        $user = Auth::login($email, $password);
        if ($user === null) {
            Auth::catatGagal();
            usleep(300000);   // perlambat tebak-tebakan password
            Http::error(401, 'Email atau password salah.');   // pesan sama untuk email salah / password salah
        }
        Http::success($user, 200, 'Login berhasil.');

    case 'logout':
        Http::allowMethods('POST');
        Auth::logout();
        Http::success(null, 200, 'Anda sudah keluar.');

    default:
        Http::error(404, 'Aksi tidak dikenal. Gunakan aksi=me, login, atau logout.');
}