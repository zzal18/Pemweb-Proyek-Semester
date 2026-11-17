<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    public const COOKIE = 'a2sid';
    private const MAX_GAGAL = 5;      // percobaan login gagal berturut-turut
    private const KUNCI_DETIK = 60;   // lama login dikunci setelah MAX_GAGAL

    private static ?string $dummyHash = null;

    /** Mulai session dengan cookie yang aman (HttpOnly, SameSite=Lax, dibatasi ke folder aplikasi). */
    public static function mulai(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        session_name(self::COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::cookiePath(),
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** /pemweb-obe/api/auth.php -> cookie hanya dikirim ke /pemweb-obe/... */
    private static function cookiePath(): string
    {
        $dir = dirname(dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/api/x.php')));
        if ($dir === '' || $dir === '.' || $dir === '/') {
            return '/';
        }
        // setcookie() menolak spasi/karakter kontrol di path (PHP 8.x melempar ValueError),
        // jadi encode tiap segmen: "Pemweb-Proyek Semester" -> "Pemweb-Proyek%20Semester".
        // Browser mencocokkan path cookie dengan path request yang sudah ter-encode.
        $segments = array_map('rawurlencode', explode('/', $dir));
        return implode('/', $segments);
    }

    /**
     * User yang sedang login, atau null. Data selalu dibaca ulang dari database
     * sehingga user yang dihapus / diturunkan rolenya langsung kehilangan akses.
     */
    public static function currentUser(): ?array
    {
        if (!isset($_COOKIE[self::COOKIE])) {
            return null;   // pengunjung anonim tidak dibuatkan session
        }
        self::mulai();
        $id = $_SESSION['user_id'] ?? null;
        session_write_close();   // lepas kunci file session agar request paralel tidak antre
        if (!is_int($id)) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT id, nama, email, role FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    /** Guard endpoint admin: 401 jika belum login, 403 jika bukan admin. */
    public static function requireAdmin(): array
    {
        $user = self::currentUser();
        if ($user === null) {
            Http::error(401, 'Anda harus login terlebih dahulu.');
        }
        if ($user['role'] !== 'admin') {
            Http::error(403, 'Akses ditolak. Hanya admin yang boleh mengelola menu.');
        }
        return $user;
    }

    /** Sisa detik login dikunci (0 = tidak terkunci). Session harus sudah dimulai. */
    public static function sisaKunci(): int
    {
        return max(0, (int) ($_SESSION['kunci_sampai'] ?? 0) - time());
    }

    public static function catatGagal(): void
    {
        $n = (int) ($_SESSION['gagal'] ?? 0) + 1;
        if ($n >= self::MAX_GAGAL) {
            $_SESSION['kunci_sampai'] = time() + self::KUNCI_DETIK;
            $n = 0;
        }
        $_SESSION['gagal'] = $n;
    }

    /** Verifikasi kredensial. Return data user publik, atau null jika salah. Session harus sudah dimulai. */
    public static function login(string $email, string $password): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nama, email, password_hash, role FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        // Selalu jalankan password_verify agar waktu respons sama baik email ada maupun tidak
        self::$dummyHash ??= password_hash('tidak-dipakai', PASSWORD_DEFAULT);
        $cocok = password_verify($password, $row['password_hash'] ?? self::$dummyHash);
        if ($row === false || !$cocok) {
            return null;
        }

        session_regenerate_id(true);   // cegah session fixation
        $_SESSION = ['user_id' => (int) $row['id']];

        return ['id' => (int) $row['id'], 'nama' => $row['nama'], 'email' => $row['email'], 'role' => $row['role']];
    }

    public static function logout(): void
    {
        self::mulai();
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(self::COOKIE, '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        try {
            session_destroy();
        } catch (\ErrorException) {
            // Di sebagian server (mis. Windows) file session belum bisa dihapus saat itu juga.
            // Data sudah dikosongkan dan cookie dihapus; sisa file dibersihkan garbage collector PHP.
        }
    }
}