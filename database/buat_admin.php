<?php
declare(strict_types=1);

/**
 * Buat / reset akun admin (atau kasir) dari terminal. Password disimpan sebagai hash (bcrypt).
 *
 *   php database/buat_admin.php "Nama Admin" email@contoh.com PasswordMinimal8 [admin|kasir]
 *
 * Jika email sudah ada, nama, password, dan role akun itu diperbarui.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../api/bootstrap.php';

use App\Database;

[$self, $nama, $email, $password] = array_pad($argv, 4, '');
$role = $argv[4] ?? 'admin';

$nama  = trim($nama);
$email = trim($email);

if ($nama === '' || mb_strlen($nama) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
    || strlen($password) < 8 || strlen($password) > 72
    || !in_array($role, ['admin', 'kasir'], true)) {
    fwrite(STDERR, "Pemakaian: php database/buat_admin.php \"Nama\" email@contoh.com Password(8-72 karakter) [admin|kasir]\n");
    exit(1);
}

$stmt = Database::connection()->prepare(
    'INSERT INTO users (nama, email, password_hash, role) VALUES (:nama, :email, :hash, :role)
    ON DUPLICATE KEY UPDATE nama = VALUES(nama), password_hash = VALUES(password_hash), role = VALUES(role)'
);
$stmt->execute([
    'nama'  => $nama,
    'email' => $email,
    'hash'  => password_hash($password, PASSWORD_DEFAULT),
    'role'  => $role,
]);

echo "Akun {$role} '{$email}' siap dipakai.\n";