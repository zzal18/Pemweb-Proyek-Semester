<?php
declare(strict_types=1);

namespace App;

/**
 * Log audit keamanan (Modul 13, OWASP A09). Satu baris JSON per peristiwa di storage/logs/security.log.
 * Tidak pernah dicatat: password, token CSRF, cookie, email mentah (hanya sidik jarinya).
 */
final class AuditLog
{
    private const MAKS_PANJANG = 200;

    public static function catat(string $peristiwa, array $konteks = []): void
    {
        try {
            $dir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }

            $bersih = [];
            foreach ($konteks as $kunci => $nilai) {
                $bersih[(string) $kunci] = is_string($nilai) ? mb_substr($nilai, 0, self::MAKS_PANJANG) : $nilai;
            }

            $baris = json_encode(
                ['waktu' => date('c'), 'peristiwa' => $peristiwa, 'ip' => Auth::ip(), 'konteks' => $bersih],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
            file_put_contents($dir . '/security.log', $baris . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            error_log('AuditLog gagal: ' . $e->getMessage());
        }
    }

    /** Sidik jari email untuk log. */
    public static function ref(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 12);
    }
}