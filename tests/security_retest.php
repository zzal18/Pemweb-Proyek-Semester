<?php
declare(strict_types=1);

/**
 * Retest keamanan lokal (Modul 13 - Security Review & Remediation).
 * Aman: hanya menyerang aplikasi lokal sendiri, email acak untuk uji brute force,
 * satu data uji dibuat lalu dihapus, tidak mencetak password.
 */

$base = rtrim($argv[1] ?? 'http://localhost/pemweb-obe/api/menu.php', '/');
$root = preg_replace('#api/menu\.php$#', '', $base);
$auth = preg_replace('#menu\.php$#', 'auth.php', $base);
$health = preg_replace('#menu\.php$#', 'health.php', $base);

$adminEmail = getenv('ADMIN_EMAIL') ?: '';
$adminPass  = getenv('ADMIN_PASSWORD') ?: '';
if ($adminEmail === '' || $adminPass === '') {
    fwrite(STDERR, "Set ADMIN_EMAIL dan ADMIN_PASSWORD dulu.\n");
    exit(2);
}

$cookie = '';
$hasil  = [];

function req(string $method, string $url, ?string $body = null, array $headers = [], bool $pakaiCookie = true): array
{
    global $cookie;
    $h = "Accept: application/json\r\n";
    if ($pakaiCookie && $cookie !== '') {
        $h .= "Cookie: {$cookie}\r\n";
    }
    foreach ($headers as $nama => $nilai) {
        $h .= "{$nama}: {$nilai}\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => $h, 'content' => $body ?? '',
        'ignore_errors' => true, 'timeout' => 20,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || !isset($http_response_header)) {
        fwrite(STDERR, "Tidak dapat menghubungi {$url}. Pastikan Laragon (Apache + MySQL) aktif.\n");
        exit(2);
    }

    $status = 0;
    $hdr = [];
    foreach ($http_response_header as $baris) {
        if (str_starts_with($baris, 'HTTP/')) {
            preg_match('#HTTP/\S+\s+(\d{3})#', $baris, $m);
            $status = (int) ($m[1] ?? 0);
            $hdr = [];
            continue;
        }
        if (str_contains($baris, ':')) {
            [$n, $v] = explode(':', $baris, 2);
            $n = strtolower(trim($n));
            $hdr[$n] = trim($v);
            if ($n === 'set-cookie' && $pakaiCookie) {
                $cookie = trim(explode(';', $v)[0]);
            }
        }
    }
    return ['status' => $status, 'headers' => $hdr, 'raw' => $raw, 'json' => json_decode($raw, true)];
}

function cek(string $id, string $nama, bool $lulus, string $detail = ''): void
{
    global $hasil;
    $hasil[] = $lulus;
    printf("[%s] %-6s %s%s\n", $lulus ? 'PASS' : 'FAIL', $id, $nama, $detail === '' ? '' : "  -> {$detail}");
}

function jsonBody(array $d): string
{
    return json_encode($d, JSON_UNESCAPED_UNICODE);
}

function bagian(string $judul): void
{
    echo "\n== {$judul} ==\n";
}

$commit = trim((string) @shell_exec('git rev-parse --short HEAD 2>&1'));
echo "Retest keamanan Modul 13\n";
echo 'Waktu  : ' . date('c') . "\n";
echo 'Commit : ' . ($commit !== '' ? $commit : '(git tidak tersedia)') . "\n";
echo "Target : {$root}\n";

$logFile = dirname(__DIR__) . '/storage/logs/security.log';
$logAwal = is_file($logFile) ? (int) filesize($logFile) : 0;

// ============ T1 ============
bagian('T1 - Berkas/folder sensitif tidak terekspos (OWASP A02/A01)');
$jalur = [
    '.env', '.env.example', '.git/config', '.git/HEAD', '.gitignore',
    'composer.json', 'composer.lock', 'database/schema.sql', 'database/buat_admin.php',
    'src/Auth.php', 'vendor/autoload.php', 'tests/api_test.php', 'Api.md', 'README.md', 'valid.json',
];
foreach ($jalur as $i => $j) {
    $r = req('GET', $root . $j, null, [], false);
    cek('T1.' . ($i + 1), "GET /{$j} tidak 200", !in_array($r['status'], [200, 301, 302], true) && $r['status'] !== 500, "status {$r['status']}");
}
$r = req('GET', $root . 'Images/', null, [], false);
cek('T1.' . (count($jalur) + 1), 'Directory listing /Images/ dimatikan', !($r['status'] === 200 && stripos($r['raw'], 'Index of') !== false), "status {$r['status']}");

// ============ T2 ============
bagian('T2 - Header keamanan HTTP (OWASP A02)');
foreach (['index.html', 'admin.html'] as $halaman) {
    $r = req('GET', $root . $halaman, null, [], false);
    $h = $r['headers'];
    cek("T2 {$halaman}", 'X-Content-Type-Options = nosniff', strtolower($h['x-content-type-options'] ?? '') === 'nosniff');
    cek("T2 {$halaman}", 'Anti-clickjacking (X-Frame-Options / frame-ancestors)', isset($h['x-frame-options']) || str_contains($h['content-security-policy'] ?? '', 'frame-ancestors'));
    cek("T2 {$halaman}", 'Content-Security-Policy ada dan membatasi script', str_contains($h['content-security-policy'] ?? '', "script-src 'self'"));
    cek("T2 {$halaman}", 'Referrer-Policy ada', isset($h['referrer-policy']));
}

// ============ T3 ============
bagian('T3 - Kebocoran informasi & penanganan error (OWASP A02/A10)');
$r = req('GET', $health, null, [], false);
$d = $r['json']['data'] ?? [];
cek('T3.1', 'health.php tidak membuka versi PHP / mode aplikasi', $r['status'] === 200 && !isset($d['php']) && !isset($d['env']), 'field: ' . implode(',', array_keys($d)));
$r = req('GET', "{$base}?id=" . rawurlencode("1' OR '1'='1"), null, [], false);
cek('T3.2', 'Input injeksi pada id -> 400 tanpa detail SQL/stack trace', $r['status'] === 400 && !preg_match('/SQLSTATE|Stack trace|PDOException|\.php on line/i', $r['raw']), "status {$r['status']}");

// ============ T4 ============
bagian('T4 - Pembatasan percobaan login (OWASP A07)');
$emailAcak = 'uji-bf-' . bin2hex(random_bytes(4)) . '@contoh.test';
$kode = [];
$retryAfter = null;
for ($i = 1; $i <= 7; $i++) {
    $r = req('POST', "{$auth}?aksi=login", jsonBody(['email' => $emailAcak, 'password' => 'salah-' . $i]), ['Content-Type' => 'application/json'], false);
    $kode[] = $r['status'];
    if ($r['status'] === 429) {
        $retryAfter = $r['headers']['retry-after'] ?? null;
    }
}
cek('T4.1', '5 percobaan gagal pertama -> 401', array_slice($kode, 0, 5) === [401, 401, 401, 401, 401], 'kode: ' . implode(' ', $kode));
cek('T4.2', 'Percobaan ke-6 dan ke-7 tanpa cookie tetap -> 429', ($kode[5] ?? 0) === 429 && ($kode[6] ?? 0) === 429, 'kode: ' . implode(' ', $kode));
cek('T4.3', 'Respons 429 membawa header Retry-After', $retryAfter !== null && ctype_digit($retryAfter), 'Retry-After: ' . ($retryAfter ?? '(tidak ada)'));

// ============ Login admin ============
$cookie = '';
$r = req('POST', "{$auth}?aksi=login", jsonBody(['email' => $adminEmail, 'password' => $adminPass]), ['Content-Type' => 'application/json']);
if ($r['status'] !== 200) {
    fwrite(STDERR, "Login admin gagal (status {$r['status']}). Cek ADMIN_EMAIL/ADMIN_PASSWORD, atau tunggu jika terkunci.\n");
    exit(2);
}
$token = (string) ($r['json']['data']['csrf_token'] ?? '');
$valid = ['nama' => 'Uji Keamanan Modul13', 'kategori' => 'Minuman', 'harga' => 1000, 'stok' => 1, 'deskripsi' => 'Data uji keamanan, otomatis dihapus'];
$J = ['Content-Type' => 'application/json'];
$T = $token !== '' ? ['X-CSRF-Token' => $token] : [];
$bersihkan = [];

// ============ T5 ============
bagian('T5 - Perlindungan CSRF (OWASP A01/A06)');
cek('T5.1', 'Login mengeluarkan token CSRF', strlen($token) >= 32, 'panjang token: ' . strlen($token));
$r = req('GET', "{$auth}?aksi=me");
cek('T5.2', 'aksi=me untuk sesi login juga mengeluarkan token', strlen((string) ($r['json']['data']['csrf_token'] ?? '')) >= 32);

$r = req('POST', $base, jsonBody($valid), $J + $T);
$idUji = (int) ($r['json']['data']['id'] ?? 0);
if ($idUji > 0) { $bersihkan[] = $idUji; }
cek('T5.3', 'KONTROL: POST sah dengan token -> 201', $r['status'] === 201, "status {$r['status']}");

$r = req('POST', $base, jsonBody($valid), $J);
if ($r['status'] === 201) { $bersihkan[] = (int) ($r['json']['data']['id'] ?? 0); }
cek('T5.4', 'POST tanpa token CSRF -> 403', $r['status'] === 403, "status {$r['status']}");

$r = req('POST', $base, jsonBody($valid), $J + ['X-CSRF-Token' => str_repeat('a', 64)]);
if ($r['status'] === 201) { $bersihkan[] = (int) ($r['json']['data']['id'] ?? 0); }
cek('T5.5', 'POST dengan token salah -> 403', $r['status'] === 403, "status {$r['status']}");

$r = req('PATCH', "{$base}?id={$idUji}", jsonBody(['harga' => 2000]), $J);
cek('T5.6', 'PATCH tanpa token -> 403', $r['status'] === 403, "status {$r['status']}");

$r = req('DELETE', "{$base}?id={$idUji}");
$ada = req('GET', "{$base}?id={$idUji}", null, [], false)['status'];
cek('T5.7', 'DELETE tanpa token -> 403 dan data tetap ada', $r['status'] === 403 && $ada === 200, "DELETE {$r['status']}, GET sesudahnya {$ada}");

// ============ T6 ============
bagian('T6 - Access control (OWASP A01)');
if ($idUji === 0 || $ada !== 200) {
    $r = req('POST', $base, jsonBody($valid), $J + $T);
    $idUji = (int) ($r['json']['data']['id'] ?? 0);
    if ($idUji > 0) { $bersihkan[] = $idUji; }
}
$r = req('DELETE', "{$base}?id={$idUji}", null, [], false);
$ada = req('GET', "{$base}?id={$idUji}", null, [], false)['status'];
cek('T6.1', 'DELETE manual oleh anonim -> 401 dan data tetap ada', $r['status'] === 401 && $ada === 200, "DELETE {$r['status']}, GET sesudahnya {$ada}");

$adminCookie = $cookie;
$kasirEmail = getenv('KASIR_EMAIL') ?: '';
$kasirPass  = getenv('KASIR_PASSWORD') ?: '';
if ($kasirEmail !== '' && $kasirPass !== '') {
    $cookie = '';
    $rk = req('POST', "{$auth}?aksi=login", jsonBody(['email' => $kasirEmail, 'password' => $kasirPass]), $J);
    $tk = (string) ($rk['json']['data']['csrf_token'] ?? '');
    $TK = $tk !== '' ? ['X-CSRF-Token' => $tk] : [];
    $r = req('DELETE', "{$base}?id={$idUji}", null, $TK);
    $ada = req('GET', "{$base}?id={$idUji}", null, [], false)['status'];
    cek('T6.2', 'DELETE oleh kasir (role terbatas) -> 403 dan data tetap ada', $r['status'] === 403 && $ada === 200, "DELETE {$r['status']}, GET sesudahnya {$ada}");
    $r = req('PUT', "{$base}?id={$idUji}", jsonBody($valid), $J + $TK);
    cek('T6.3', 'PUT oleh kasir -> 403', $r['status'] === 403, "status {$r['status']}");
    $cookie = $adminCookie;
} else {
    echo "[SKIP] T6.2-T6.3 (set KASIR_EMAIL dan KASIR_PASSWORD)\n";
}

// ============ T7 ============
bagian('T7 - Body raksasa ditolak (OWASP A10)');
$besar = jsonBody(['nama' => str_repeat('A', 70000)] + $valid);
$r = req('POST', $base, $besar, $J + $T);
if ($r['status'] === 201) { $bersihkan[] = (int) ($r['json']['data']['id'] ?? 0); }
cek('T7.1', 'Body > 64 KB -> 413', $r['status'] === 413, "status {$r['status']}");

// ============ Pembersihan ============
foreach (array_unique(array_filter($bersihkan)) as $id) {
    req('DELETE', "{$base}?id={$id}", null, $T);
}
$sisa = 0;
foreach (array_unique(array_filter($bersihkan)) as $id) {
    $sisa += req('GET', "{$base}?id={$id}", null, [], false)['status'] === 200 ? 1 : 0;
}
cek('T8.1', 'Semua data uji sudah dibersihkan', $sisa === 0, "sisa {$sisa}");

// ============ T9 ============
bagian('T9 - Logout dilindungi CSRF (OWASP A01)');
$r = req('POST', "{$auth}?aksi=logout", null, []);
$me = req('GET', "{$auth}?aksi=me");
cek('T9.1', 'Logout tanpa token -> 403 dan sesi tetap login', $r['status'] === 403 && ($me['json']['data']['login'] ?? false) === true, "logout {$r['status']}, login=" . var_export($me['json']['data']['login'] ?? null, true));
$r = req('POST', "{$auth}?aksi=logout", null, $T);
cek('T9.2', 'KONTROL: logout dengan token -> 200', $r['status'] === 200, "status {$r['status']}");

// ============ T10 ============
bagian('T10 - Log audit keamanan (OWASP A09)');
$baru = '';
if (is_file($logFile)) {
    $fh = fopen($logFile, 'rb');
    if ($fh !== false) {
        fseek($fh, $logAwal);
        $baru = (string) stream_get_contents($fh);
        fclose($fh);
    }
}
cek('T10.1', 'storage/logs/security.log terbentuk dan berisi peristiwa baru', $baru !== '', $baru === '' ? 'berkas tidak ada / kosong' : strlen($baru) . ' byte baru');
foreach (['login_gagal', 'login_terkunci', 'csrf_ditolak', 'akses_ditolak', 'menu_dibuat', 'menu_dihapus'] as $p) {
    cek("T10 {$p}", "Peristiwa {$p} tercatat", str_contains($baru, '"peristiwa":"' . $p . '"'));
}
cek('T10.2', 'Log tidak memuat email acak, password, atau token', !str_contains($baru, $emailAcak) && !str_contains($baru, $adminPass) && ($token === '' || !str_contains($baru, $token)));

// ============ Ringkasan ============
$gagal = count(array_filter($hasil, static fn(bool $x): bool => !$x));
printf("\nTotal %d uji, %d lulus, %d gagal.\n", count($hasil), count($hasil) - $gagal, $gagal);
echo "Catatan: bila perlu login admin segera setelah uji brute force, jalankan:  DELETE FROM login_attempts;\n";
exit($gagal === 0 ? 0 : 1);