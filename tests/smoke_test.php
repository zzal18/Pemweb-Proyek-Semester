<?php
declare(strict_types=1);

/**
 * Smoke test / regression check pasca-deploy (Modul 14).
 * Hanya melakukan request GET read-only: aman dijalankan ke staging maupun production.
 *
 * Jalankan:
 *   php tests/smoke_test.php http://localhost/pemweb-obe/        (baseline Laragon)
 *   php tests/smoke_test.php https://domain-anda.example/        (staging/production)
 *
 * Exit code 0 = semua lulus, 1 = ada yang gagal, 2 = server tidak dapat dihubungi.
 */

$base = rtrim($argv[1] ?? 'http://localhost/pemweb-obe/', '/') . '/';
$https = str_starts_with($base, 'https://');
$hasil = [];

function ambil(string $url): array
{
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 15,
        'follow_location' => 0, 'header' => "Accept: */*\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || !isset($http_response_header)) {
        fwrite(STDERR, "Tidak dapat menghubungi {$url}\n");
        exit(2);
    }
    $status = 0;
    $h = [];
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
            $h = [];   // ikuti redirect terakhir saja
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $h[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $h, 'body' => $raw];
}

function cek(string $id, string $nama, bool $lulus, string $detail = ''): void
{
    global $hasil;
    $hasil[] = $lulus;
    printf("%-4s %-5s %s%s\n", $lulus ? 'PASS' : 'FAIL', $id, $nama, $lulus ? '' : "  -> {$detail}");
}

echo "Target: {$base}\n\n";

// ---------- Halaman dan API utama berfungsi ----------
$r = ambil($base . 'index.html');
cek('S1', 'index.html -> 200', $r['status'] === 200, "status {$r['status']}");
$indexHeaders = $r['headers'];

$r = ambil($base . 'admin.html');
cek('S2', 'admin.html -> 200', $r['status'] === 200, "status {$r['status']}");

$r = ambil($base . 'api/health.php');
$j = json_decode($r['body'], true);
cek('S3', 'api/health.php -> 200 dan status "success"', $r['status'] === 200 && ($j['status'] ?? '') === 'success', "status {$r['status']}");

$r = ambil($base . 'api/menu.php');
$j = json_decode($r['body'], true);
cek('S4', 'GET api/menu.php -> 200, data berupa array', $r['status'] === 200 && is_array($j['data'] ?? null), "status {$r['status']}");

$r = ambil($base . 'api/menu.php?page=99999&per_page=2');
$j = json_decode($r['body'], true);
cek('S5', 'Empty state: halaman di luar jangkauan -> 200 dan data kosong', $r['status'] === 200 && ($j['data'] ?? null) === [], "status {$r['status']}");

$r = ambil($base . 'api/menu.php?id=abc');
cek('S6', 'Input tidak valid (id=abc) -> 400', $r['status'] === 400, "status {$r['status']}");
cek('S7', 'Pesan error tidak membocorkan stack trace / SQL / path server',
    !preg_match('/Stack trace|SQLSTATE|PDOException|Fatal error|Warning:|C:\\\\|\/var\/www|\/home\//i', $r['body']),
    substr($r['body'], 0, 200));

$r = ambil($base . 'api/menu.php?id=1');
$r2 = ambil($base . 'api/auth.php?aksi=me');
$j = json_decode($r2['body'], true);
cek('S8', 'Sesi anonim: auth.php?aksi=me -> login=false', $r2['status'] === 200 && ($j['data']['login'] ?? null) === false, "status {$r2['status']}");

// ---------- Berkas dan folder sensitif tidak boleh bisa diunduh ----------
$sensitif = ['.env', '.git/config', 'src/Auth.php', 'database/schema.sql', 'storage/logs/security.log',
    'composer.json', 'composer.lock', 'tests/api_test.php', 'valid.json'];
foreach ($sensitif as $i => $path) {
    $r = ambil($base . $path);
    cek('S' . (9 + $i), "/{$path} tidak dapat diakses", in_array($r['status'], [301, 302, 400, 403, 404], true), "status {$r['status']}");
}

// ---------- Header keamanan ----------
$h = $indexHeaders;
cek('S18', 'Header X-Content-Type-Options = nosniff', strtolower($h['x-content-type-options'] ?? '') === 'nosniff');
cek('S19', 'Content-Security-Policy membatasi script', str_contains($h['content-security-policy'] ?? '', "script-src 'self'"));
cek('S20', 'Anti-clickjacking (X-Frame-Options atau frame-ancestors)', isset($h['x-frame-options']) || str_contains($h['content-security-policy'] ?? '', 'frame-ancestors'));

// ---------- HTTPS (hanya bermakna di staging/production) ----------
if ($https) {
    cek('S21', 'Strict-Transport-Security (HSTS) aktif di HTTPS', isset($h['strict-transport-security']));
    $http = 'http://' . substr($base, 8);
    $r = ambil($http . 'index.html');
    cek('S22', 'Akses HTTP dialihkan ke HTTPS (301/302)', in_array($r['status'], [301, 302, 308], true)
        && str_starts_with($r['headers']['location'] ?? '', 'https://'), "status {$r['status']}");
} else {
    echo "SKIP S21-S22 (HTTPS) karena target bukan https://\n";
}

$gagal = count(array_filter($hasil, fn($ok) => !$ok));
printf("\nTotal %d uji, %d lulus, %d gagal.\n", count($hasil), count($hasil) - $gagal, $gagal);
exit($gagal === 0 ? 0 : 1);