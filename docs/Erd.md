# ERD dan Data Dictionary - A2 Coffee and Food

```mermaid
erDiagram
    kategori ||--o{ menu : "mengelompokkan"
    menu ||--o{ detail_pesanan : "dipesan dalam"
    pesanan ||--|{ detail_pesanan : "berisi"
    users |o--o{ pesanan : "memproses"

    users {
        BIGINT_UNSIGNED id PK
        VARCHAR nama
        VARCHAR email UK
        VARCHAR password_hash
        ENUM role
        TIMESTAMP created_at
    }
    kategori {
        SMALLINT_UNSIGNED id PK
        VARCHAR nama UK
    }
    menu {
        BIGINT_UNSIGNED id PK
        SMALLINT_UNSIGNED kategori_id FK
        VARCHAR nama
        VARCHAR deskripsi
        INT_UNSIGNED harga
        INT_UNSIGNED stok
        VARCHAR gambar
        TIMESTAMP created_at
        TIMESTAMP updated_at    
    }
    pesanan {
        BIGINT_UNSIGNED id PK
        BIGINT_UNSIGNED user_id FK
        VARCHAR nama_pelanggan
        ENUM status
        TIMESTAMP created_at
    }
    detail_pesanan {
        BIGINT_UNSIGNED id PK
        BIGINT_UNSIGNED pesanan_id FK
        BIGINT_UNSIGNED menu_id FK
        INT_UNSIGNED jumlah
        INT_UNSIGNED harga_satuan
    }
```

## Data dictionary ringkas

| Tabel | Fungsi | Key dan constraint penting |
|---|---|---|
| users | Akun admin dan kasir | PK id; UNIQUE email; password disimpan sebagai hash |
| kategori | Pengelompokan menu (Minuman, Cemilan) | PK id; UNIQUE nama |
| menu | Daftar produk | PK id; FK kategori_id ke kategori(id); harga dan stok UNSIGNED; status diturunkan dari stok |
| pesanan | Header pesanan | PK id; FK user_id ke users(id) ON DELETE SET NULL; ENUM status |
| detail_pesanan | Item per pesanan | PK id; FK pesanan_id (ON DELETE CASCADE); FK menu_id; UNIQUE (pesanan_id, menu_id); harga_satuan menyimpan harga saat transaksi |

Alasan desain: kategori dipisah agar tidak ada teks berulang, total pesanan dihitung dengan SUM (tidak disimpan), dan harga_satuan disimpan agar riwayat tidak berubah saat harga menu diubah.