<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Http;

$aksi = isset($_GET['aksi']) && is_string($_GET['aksi']) ? $_GET['aksi'] : '';

switch ($aksi) {
    case 'me':
        Http::allowMethods('GET');
        $user = Auth::currentUser();
        Http::success([
            'login'      => $user !== null,
            'user'       => $user,
            'csrf_token' => $user !== null ? Auth::csrfToken() : null,
        ]);

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
        $ref  = AuditLog::ref($email);
        $sisa = Auth::sisaKunci($email);
        if ($sisa > 0) {
            AuditLog::catat('login_terkunci', ['email_ref' => $ref, 'sisa_detik' => $sisa]);
            Http::error(429, "Terlalu banyak percobaan login. Coba lagi dalam {$sisa} detik.", headers: ['Retry-After' => (string) $sisa]);
        }

        $user = Auth::login($email, $password);
        if ($user === null) {
            Auth::catatGagal($email);
            AuditLog::catat('login_gagal', ['email_ref' => $ref]);
            usleep(300000);
            Http::error(401, 'Email atau password salah.');
        }
        Auth::hapusGagal($email);
        AuditLog::catat('login_sukses', ['user_id' => $user['id'], 'role' => $user['role']]);
        Http::success($user + ['csrf_token' => Auth::csrfToken()], 200, 'Login berhasil.');

    case 'logout':
        Http::allowMethods('POST');
        $user = Auth::currentUser();
        if ($user !== null) {
            Auth::requireCsrf();
            AuditLog::catat('logout', ['user_id' => $user['id']]);
            Auth::logout();
        }
        Http::success(null, 200, 'Anda sudah keluar.');

    default:
        Http::error(404, 'Aksi tidak dikenal. Gunakan aksi=me, login, atau logout.');
}