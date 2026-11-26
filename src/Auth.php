<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    public const COOKIE = 'a2sid';

    // Pembatasan login (Modul 13): dihitung di DATABASE per IP, bukan per session.
    private const JENDELA_DETIK = 900;   // 15 menit
    private const MAX_GAGAL_AKUN = 5;    // gagal per kombinasi IP + email
    private const MAX_GAGAL_IP = 30;     // gagal per IP untuk semua email

    private static ?string $dummyHash = null;
    private static ?string $csrf = null;

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

    private static function cookiePath(): string
    {
        $dir = dirname(dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/api/x.php')));
        if ($dir === '' || $dir === '.' || $dir === '/') {
            return '/';
        }
        $segments = array_map('rawurlencode', explode('/', $dir));
        return implode('/', $segments);
    }

    /** Alamat IP klien. Hanya REMOTE_ADDR: X-Forwarded-For bisa dipalsukan klien. */
    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    /** User yang sedang login, atau null. Data dibaca ulang dari database. */
    public static function currentUser(): ?array
    {
        self::$csrf = null;
        if (!isset($_COOKIE[self::COOKIE])) {
            return null;
        }
        self::mulai();
        $id   = $_SESSION['user_id'] ?? null;
        $csrf = $_SESSION['csrf'] ?? null;
        session_write_close();
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
        self::$csrf = is_string($csrf) && $csrf !== '' ? $csrf : null;
        return $row;
    }

    public static function csrfToken(): ?string
    {
        return self::$csrf;
    }

    /** Guard CSRF: header X-CSRF-Token harus sama dengan token session. Panggil SETELAH requireLogin/requireRole. */
    public static function requireCsrf(): void
    {
        $dikirim = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($dikirim) || self::$csrf === null || !hash_equals(self::$csrf, $dikirim)) {
            AuditLog::catat('csrf_ditolak', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'path'   => $_SERVER['SCRIPT_NAME'] ?? '',
                'token'  => $dikirim === '' ? 'tidak_ada' : 'tidak_cocok',
            ]);
            Http::error(403, 'Token CSRF tidak valid. Muat ulang halaman atau login kembali.');
        }
    }

    public static function requireLogin(): array
    {
        $user = self::currentUser();
        if ($user === null) {
            AuditLog::catat('akses_ditolak', [
                'status' => 401,
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'path'   => $_SERVER['SCRIPT_NAME'] ?? '',
            ]);
            Http::error(401, 'Anda harus login terlebih dahulu.');
        }
        return $user;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::requireLogin();
        if (!in_array($user['role'], $roles, true)) {
            AuditLog::catat('akses_ditolak', [
                'status'  => 403,
                'user_id' => $user['id'],
                'role'    => $user['role'],
                'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
                'path'    => $_SERVER['SCRIPT_NAME'] ?? '',
            ]);
            Http::error(403, 'Akses ditolak. Role Anda tidak diizinkan untuk aksi ini.');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        return self::requireRole(['admin']);
    }

    // ---------- Pembatasan percobaan login (berbasis database) ----------

    private static function hashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /** Sisa detik login dikunci untuk IP + email ini (0 = tidak terkunci). */
    public static function sisaKunci(string $email): int
    {
        $pdo     = Database::connection();
        $jendela = self::JENDELA_DETIK;   // konstanta integer, bukan input pengguna
        $sisaSql = "GREATEST(0, {$jendela} - (UNIX_TIMESTAMP() - MIN(UNIX_TIMESTAMP(created_at))))";
        $syarat  = "created_at > (NOW() - INTERVAL {$jendela} SECOND)";

        $sisa = 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) AS n, {$sisaSql} AS sisa FROM login_attempts WHERE ip = :ip AND email_hash = :hash AND {$syarat}");
        $stmt->execute(['ip' => self::ip(), 'hash' => self::hashEmail($email)]);
        $a = $stmt->fetch();
        if ((int) ($a['n'] ?? 0) >= self::MAX_GAGAL_AKUN) {
            $sisa = max($sisa, (int) $a['sisa'], 1);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) AS n, {$sisaSql} AS sisa FROM login_attempts WHERE ip = :ip AND {$syarat}");
        $stmt->execute(['ip' => self::ip()]);
        $b = $stmt->fetch();
        if ((int) ($b['n'] ?? 0) >= self::MAX_GAGAL_IP) {
            $sisa = max($sisa, (int) $b['sisa'], 1);
        }

        return $sisa;
    }

    public static function catatGagal(string $email): void
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO login_attempts (ip, email_hash) VALUES (:ip, :hash)')
            ->execute(['ip' => self::ip(), 'hash' => self::hashEmail($email)]);
        $pdo->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }

    public static function hapusGagal(string $email): void
    {
        Database::connection()
            ->prepare('DELETE FROM login_attempts WHERE ip = :ip AND email_hash = :hash')
            ->execute(['ip' => self::ip(), 'hash' => self::hashEmail($email)]);
    }

    public static function login(string $email, string $password): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nama, email, password_hash, role FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        self::$dummyHash ??= password_hash('tidak-dipakai', PASSWORD_DEFAULT);
        $cocok = password_verify($password, $row['password_hash'] ?? self::$dummyHash);
        if ($row === false || !$cocok) {
            return null;
        }

        session_regenerate_id(true);
        self::$csrf = bin2hex(random_bytes(32));
        $_SESSION = ['user_id' => (int) $row['id'], 'csrf' => self::$csrf];

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
        }
    }
}