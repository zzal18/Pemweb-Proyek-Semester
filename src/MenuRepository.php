<?php
declare(strict_types=1);

namespace App;

final class MenuRepository
{
    public const KATEGORI = ['Minuman', 'Cemilan'];

    private const SELECT = "SELECT m.id, m.nama, k.nama AS kategori, m.harga, m.stok,
            IF(m.stok > 0, 'Tersedia', 'Habis') AS status, m.deskripsi, m.gambar
        FROM menu m
        JOIN kategori k ON k.id = m.kategori_id";

    public static function all(?string $kategori = null, ?string $kataKunci = null): array
    {
        $where  = [];
        $params = [];

        if ($kategori !== null && $kategori !== '') {
            $where[] = 'k.nama = :kategori';
            $params['kategori'] = $kategori;
        }
        if ($kataKunci !== null && trim($kataKunci) !== '') {
            $where[] = 'm.nama LIKE :kunci';
            $params['kunci'] = '%' . addcslashes(trim($kataKunci), '%_\\') . '%';
        }

        $sql = self::SELECT
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY m.id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::SELECT . ' WHERE m.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO menu (kategori_id, nama, deskripsi, harga, stok)
                VALUES ((SELECT id FROM kategori WHERE nama = :kategori), :nama, :deskripsi, :harga, :stok)'
        );
        $stmt->execute([
            'kategori'  => $data['kategori'],
            'nama'      => $data['nama'],
            'deskripsi' => $data['deskripsi'],
            'harga'     => $data['harga'],
            'stok'      => $data['stok'],
        ]);
        return (int) $pdo->lastInsertId();
    }
}