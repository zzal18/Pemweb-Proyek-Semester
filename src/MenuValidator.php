<?php
declare(strict_types=1);

namespace App;

final class MenuValidator
{
    public const MAX_DESKRIPSI = 255;

    public static function validate(array $payload, int $maxNama): array
    {
        $errors = [];

        $nama = is_string($payload['nama'] ?? null) ? trim($payload['nama']) : '';
        if (!is_string($payload['nama'] ?? null) || $nama === '') {
            $errors['nama'] = 'Nama wajib diisi (teks).';
        } elseif (mb_strlen($nama) > $maxNama) {
            $errors['nama'] = "Nama maksimal {$maxNama} karakter.";
        }

        $kategori = $payload['kategori'] ?? null;
        if (!is_string($kategori) || !in_array($kategori, MenuRepository::KATEGORI, true)) {
            $errors['kategori'] = 'Kategori harus salah satu dari: ' . implode(', ', MenuRepository::KATEGORI) . '.';
        }

        $harga = self::nonNegativeInt($payload['harga'] ?? null);
        if ($harga === null) {
            $errors['harga'] = 'Harga harus bilangan bulat >= 0.';
        }
        $stok = self::nonNegativeInt($payload['stok'] ?? null);
        if ($stok === null) {
            $errors['stok'] = 'Stok harus bilangan bulat >= 0.';
        }

        $deskripsi = $payload['deskripsi'] ?? '';
        if (!is_string($deskripsi)) {
            $errors['deskripsi'] = 'Deskripsi harus berupa teks.';
            $deskripsi = '';
        } elseif (mb_strlen(trim($deskripsi)) > self::MAX_DESKRIPSI) {
            $errors['deskripsi'] = 'Deskripsi maksimal ' . self::MAX_DESKRIPSI . ' karakter.';
        }

        return [
            'data' => [
                'nama' => $nama,
                'kategori' => $kategori,
                'harga' => $harga,
                'stok' => $stok,
                'deskripsi' => trim($deskripsi),
            ],
            'errors' => $errors,
        ];
    }

    private static function nonNegativeInt(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $value = (int) $value;
        }
        return is_int($value) && $value >= 0 ? $value : null;
    }
}