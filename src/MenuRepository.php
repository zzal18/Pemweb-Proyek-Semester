<?php
declare(strict_types=1);

namespace App;

use PDO;

final class MenuRepository
{
    public const KATEGORI = ['Minuman', 'Cemilan'];

    private const SELECT = "SELECT m.id, m.nama, k.nama AS kategori, m.harga, m.stok,
            IF(m.stok > 0, 'Tersedia', 'Habis') AS status, m.deskripsi, m.gambar
        FROM menu m
        JOIN kategori k ON k.id = m.kategori_id";

    private static function filter(?string $kategori, ?string $kataKunci): array
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

        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public static function all(
        ?string $kategori = null,
        ?string $kataKunci = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        [$where, $params] = self::filter($kategori, $kataKunci);

        $sql = self::SELECT . $where . ' ORDER BY m.id';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $nama => $nilai) {
            $stmt->bindValue($nama, $nilai);
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function count(?string $kategori = null, ?string $kataKunci = null): int
    {
        [$where, $params] = self::filter($kategori, $kataKunci);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM menu m JOIN kategori k ON k.id = m.kategori_id' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function find(int $id, bool $forUpdate = false): ?array
    {
        $sql = self::SELECT . ' WHERE m.id = :id' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function editable(array $row): array
    {
        return [
            'nama'      => $row['nama'],
            'kategori'  => $row['kategori'],
            'harga'     => $row['harga'],
            'stok'      => $row['stok'],
            'deskripsi' => $row['deskripsi'],
        ];
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

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE menu
                SET kategori_id = (SELECT id FROM kategori WHERE nama = :kategori),
                    nama = :nama, deskripsi = :deskripsi, harga = :harga, stok = :stok
            WHERE id = :id'
        );
        $stmt->execute([
            'kategori'  => $data['kategori'],
            'nama'      => $data['nama'],
            'deskripsi' => $data['deskripsi'],
            'harga'     => $data['harga'],
            'stok'      => $data['stok'],
            'id'        => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM menu WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}