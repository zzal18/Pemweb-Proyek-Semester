# Dokumentasi API - A2 Coffee and Food

Base URL (Laragon): `http://localhost/pemweb-obe/api/`
Resource utama: **menu**. Semua respons berformat JSON UTF-8, kecuali `204 No Content` (tanpa body).

## Format respons

Sukses:

```json
{ "status": "success", "message": "OK", "data": { }, "meta": { } }
```

`meta` hanya muncul pada GET collection.

Error (selalu sama untuk semua status):

```json
{ "status": "error", "message": "Penjelasan singkat", "errors": { "nama": "Nama wajib diisi (teks)." } }
```

`errors` (pesan per field) hanya muncul pada validasi / parameter yang salah. Kesalahan internal (database, bug) dicatat di log server dan klien hanya menerima pesan generik:
`{"status":"error","message":"Terjadi kesalahan pada server."}` (status 500). Field `errors.debug` hanya ada jika `APP_ENV=local` **dan** `APP_DEBUG=true`.

## Hak akses

### Role / Authorization Matrix

| Aksi | Anonim | Kasir | Admin |
|---|---|---|---|
| GET menu (daftar, detail, cari, filter) | 200 | 200 | 200 |
| POST menu | 401 | 403 | 201 |
| PUT menu | 401 | 403 | 200 |
| PATCH menu, hanya field `stok` | 401 | 200 | 200 |
| PATCH menu, field lain | 401 | 403 | 200 |
| DELETE menu | 401 | 403 | 204 |

401 berarti belum login (identitas tidak diketahui). 403 berarti sudah login tetapi role tidak diizinkan.
Pemeriksaan dilakukan di server lewat `Auth::requireRole()`, dan role dibaca ulang dari database pada setiap request.
Menyembunyikan tombol di UI hanya untuk kenyamanan, bukan pengaman.

### Justifikasi desain akses

Aplikasi ini adalah sistem internal UMKM (A2 Coffee and Food), bukan layanan dengan pendaftar publik.
Karena itu tidak ada fitur register: akun admin dan kasir dibuat oleh pemilik lewat
`php database/buat_admin.php`. Register publik akan memungkinkan orang asing membuat akun
dan memperbesar permukaan serangan.

Dua level akses dipakai karena admin (pemilik) mengelola seluruh data menu, sedangkan kasir hanya
perlu memperbarui stok dalam pekerjaan harian. Pembagian ini menerapkan prinsip least privilege.

## Endpoint menu

| Method | Path | Akses | Tujuan | Parameter / body | Sukses | Gagal |
|---|---|---|---|---|---|---|
| GET | `menu.php` | Publik | Daftar menu | Query opsional: `kategori`, `q`, `page`, `per_page` | 200 | 400 parameter tidak valid |
| GET | `menu.php?id={id}` | Publik | Detail satu menu | Query `id` (integer >= 1) | 200 | 400 id tidak valid, 404 tidak ditemukan |
| POST | `menu.php` | Admin | Tambah menu | JSON: `nama`, `kategori`, `harga`, `stok`, `deskripsi` (opsional) | 201 + header `Location` | 401, 403, 400 JSON rusak/kosong, 415 Content-Type bukan JSON, 422 validasi gagal |
| PUT | `menu.php?id={id}` | Admin | Ganti semua field menu | Query `id`; JSON lengkap seperti POST | 200 | 401, 403, 400, 404, 415, 422 |
| PATCH | `menu.php?id={id}` | Admin; kasir hanya field `stok` | Ubah sebagian field | Query `id`; JSON berisi field yang diubah | 200 | 401, 403, 400, 404, 415, 422 |
| DELETE | `menu.php?id={id}` | Admin | Hapus menu | Query `id` | 204 (tanpa body) | 401, 403, 400 id tidak valid, 404 tidak ditemukan, 409 menu dipakai di pesanan |
| GET | `health.php` | Publik | Cek status API | - | 200 | 405 |

## Endpoint autentikasi (`auth.php`)

| Method | Path | Tujuan | Body | Sukses | Gagal |
|---|---|---|---|---|---|
| POST | `auth.php?aksi=login` | Login, membuat sesi (cookie `a2sid`) | JSON: `email`, `password` | 200 + `data` {id, nama, email, role} | 422 field kosong, 401 email/password salah, 415, 429 terlalu banyak percobaan |
| GET | `auth.php?aksi=me` | Status sesi saat ini | - | 200 + `data` {login: true/false, user} | - |
| POST | `auth.php?aksi=logout` | Keluar, sesi dihancurkan | - | 200 | - |

Keamanan login: password disimpan sebagai hash (`password_hash`), pesan gagal login sama untuk email salah maupun password salah, ID sesi diganti setelah login (`session_regenerate_id`), cookie `HttpOnly` + `SameSite=Lax`, dan 5 percobaan gagal berturut-turut mengunci login 60 detik (429 + header `Retry-After`).

Akun admin dibuat dari terminal: `php database/buat_admin.php "Nama" email@contoh.com Password [admin|kasir]`.

Semua endpoint: method lain -> `405` + header `Allow`. Kesalahan server -> `500` generik.

## Aturan validasi (POST, PUT, PATCH)

| Field | Aturan |
|---|---|
| `nama` | wajib, teks, tidak kosong setelah di-trim, maks `MAX_NAMA_MENU` karakter (default 100) |
| `kategori` | wajib, salah satu: `Minuman`, `Cemilan` |
| `harga` | wajib, bilangan bulat >= 0 (angka atau string digit) |
| `stok` | wajib, bilangan bulat >= 0 |
| `deskripsi` | opsional, teks, maks 255 karakter |

PUT mewajibkan semua field wajib. PATCH menggabungkan body dengan data yang tersimpan, lalu seluruh hasil gabungan divalidasi dengan aturan yang sama. Kolom `gambar` tidak dapat diubah lewat API ini.

## Pencarian, filter, dan pagination (GET collection)

| Parameter | Arti | Aturan |
|---|---|---|
| `kategori` | Filter kategori | `Minuman` atau `Cemilan`, selain itu 400 |
| `q` | Cari pada nama menu (LIKE, karakter `%` `_` di-escape) | teks bebas |
| `page` | Nomor halaman | integer 1 - 1000000, default 1 |
| `per_page` | Item per halaman | integer 1 - 50, default 10 |

Pagination aktif bila `page` atau `per_page` dikirim. Jika keduanya tidak ada, seluruh data dikembalikan (dipakai ringkasan dashboard). Halaman di luar jangkauan mengembalikan `200` dengan `data: []`.

## Contoh

### GET `menu.php?kategori=Minuman&q=kopi&page=1&per_page=2`

```json
{
  "status": "success",
  "message": "OK",
  "data": [
    { "id": 1, "nama": "A2 Coffee", "kategori": "Minuman", "harga": 12000, "stok": 25,
      "status": "Tersedia", "deskripsi": "Kopi susu ...", "gambar": "Images/a2-coffee.png" }
  ],
  "meta": { "total": 2, "page": 1, "per_page": 2, "total_pages": 1 }
}
```

### POST `menu.php`

```http
POST /pemweb-obe/api/menu.php
Content-Type: application/json

{"nama":"Kopi Hitam","kategori":"Minuman","harga":9000,"stok":12}
```

Respons `201 Created`, header `Location: menu.php?id=6`:

```json
{ "status": "success", "message": "Menu berhasil disimpan.",
  "data": { "id": 6, "nama": "Kopi Hitam", "kategori": "Minuman", "harga": 9000, "stok": 12,
            "status": "Tersedia", "deskripsi": "", "gambar": null } }
```

### PATCH `menu.php?id=6`

```http
PATCH /pemweb-obe/api/menu.php?id=6
Content-Type: application/json

{"harga": 10000}
```

Respons `200 OK` dengan data menu terbaru (`message`: "Menu berhasil diperbarui.").

### DELETE `menu.php?id=6`

Respons `204 No Content` (tanpa body).

## Contoh respons error

`422` validasi gagal:

```json
{ "status": "error", "message": "Data tidak memenuhi aturan validasi.",
  "errors": { "nama": "Nama wajib diisi (teks).", "harga": "Harga harus bilangan bulat >= 0." } }
```

`401` belum login:

```json
{ "status": "error", "message": "Anda harus login terlebih dahulu." }
```

`403` role tidak diizinkan:

```json
{ "status": "error", "message": "Akses ditolak. Role Anda tidak diizinkan untuk aksi ini." }
```

`404` tidak ditemukan:

```json
{ "status": "error", "message": "Menu dengan id 999 tidak ditemukan." }
```

`409` menu dipakai pesanan:

```json
{ "status": "error", "message": "Menu tidak dapat dihapus karena sudah dipakai pada pesanan." }
```

`500` kesalahan internal (production, tanpa detail):

```json
{ "status": "error", "message": "Terjadi kesalahan pada server." }
```

## Catatan keamanan

- Semua query memakai prepared statement (`PDO::ATTR_EMULATE_PREPARES = false`), termasuk `LIMIT`/`OFFSET` yang di-bind sebagai integer.
- Operasi PUT/PATCH berjalan dalam transaksi (`beginTransaction` -> `commit`, `rollBack` bila error) dengan `SELECT ... FOR UPDATE`.
- Hak akses ditegakkan di server (`Auth::requireRole()`), bukan hanya di UI.
- Permintaan tulis wajib `Content-Type: application/json` (atau `DELETE`), yang tidak dapat dikirim lintas situs oleh form biasa; ditambah cookie `SameSite=Lax`, sehingga risiko CSRF rendah untuk skala proyek ini.
- Batas login gagal dihitung per sesi, jadi hanya menghambat penebak awam. Untuk produksi, tambahkan pembatasan per IP dan HTTPS.