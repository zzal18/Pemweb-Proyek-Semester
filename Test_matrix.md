# Test Matrix CRUD - Menu

Cara menjalankan uji otomatis (Laragon: Start All, database sudah di-seed):

```
cd C:\laragon\www\pemweb-obe
php database/buat_admin.php "Admin A2" admin@a2coffee.test PasswordKuat123
$env:ADMIN_EMAIL="admin@a2coffee.test"; $env:ADMIN_PASSWORD="PasswordKuat123"
php tests/api_test.php
```

Skrip membuat data uji sendiri, menghapusnya kembali, dan mencetak `PASS`/`FAIL` per baris. Kolom **Hasil** diisi setelah skrip dijalankan (salin hasil PASS/FAIL dan tanggalnya); baris bertanda *manual* diuji lewat Postman/Thunder Client atau browser.

## Autentikasi dan otorisasi (hanya admin yang boleh mengelola menu)

| ID | Skenario | Input | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| A1 | GET tanpa login | `GET menu.php` | 200 (publik) | |
| A2 | POST tanpa login | body valid | 401 | |
| A3 | PUT tanpa login | `?id=1` | 401 | |
| A4 | PATCH tanpa login | `?id=1` | 401 | |
| A5 | DELETE tanpa login | `?id=1` | 401 | |
| A6 | Data utuh setelah A5 | `GET ?id=1` | 200 | |
| A7 | Status sesi anonim | `auth.php?aksi=me` | 200, `login: false` | |
| A8 | Login field kosong | `email: ""` | 422 | |
| A9 | Login password salah | password salah | 401, pesan generik | |
| A10 | Login email tidak terdaftar | email acak | 401, pesan sama dengan A9 | |
| A11 | Login Content-Type salah | form-urlencoded | 415 | |
| A12 | Login admin benar | email + password admin | 200, role admin, tanpa `password_hash` | |
| A13 | Status sesi setelah login | `me` | `login: true` | |
| A14 | Logout | `auth.php?aksi=logout` | 200 | |
| A15 | POST setelah logout | body valid | 401 | |
| A16 | Status sesi setelah logout | `me` | `login: false` | |
| K1-K4 | Login kasir lalu POST, DELETE, GET | akun role kasir | login 200; POST dan DELETE 403; GET 200 (opsional) | |
| A17 | UI: halaman publik | buka `index.html` | tidak ada tautan/tombol admin; kartu menu hanya tombol Detail (*manual*) | |
| A18 | UI: login di `admin.html` | buka `admin.html`, isi form login | form Tambah/Ubah Menu dan tabel dengan tombol Edit/Hapus muncul (*manual*) | |
| A19 | UI: sesi berakhir | di `admin.html`, hapus cookie `a2sid` di DevTools, lalu coba Hapus | pesan "Sesi berakhir", UI kembali ke form login (*manual*) | |
| A20 | Bypass UI | kirim DELETE lewat Postman tanpa login | 401, data tetap ada (*manual*) | |
| A21 | Kunci login | salah password 5x berturut-turut | percobaan ke-6 -> 429 + `Retry-After` (*manual*) | |

## Create

| ID | Skenario | Input | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| C1 | Data valid | `{"nama":"Uji CRUD Latte","kategori":"Minuman","harga":15000,"stok":7}` | 201, `data.id` terisi | |
| C2 | Nama kosong | `nama: ""` | 422, `errors.nama` | |
| C3 | Harga negatif | `harga: -5` | 422, `errors.harga` | |
| C4 | Kategori tidak dikenal | `kategori: "Seafood"` | 422, `errors.kategori` | |
| C5 | JSON rusak | `{"nama": "rusak",` | 400 | |
| C6 | Content-Type salah | `application/x-www-form-urlencoded` | 415 | |
| C7 | SQL injection di nama | `x'); DROP TABLE menu;--` | 201, tersimpan sebagai teks | |
| C8 | Tabel masih ada | GET collection setelah C7 | 200 | |
| C9 | UI: kirim form valid | isi form di `admin.html` | baris baru muncul di tabel; menu baru tampil di `index.html` setelah muat ulang (*manual*) | |
| C10 | UI: error server | nama > 100 karakter lewat Postman | pesan error tampil di bawah field (*manual*) | |

## Read (termasuk search / filter / pagination)

| ID | Skenario | Input | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| R1 | Daftar menu | `GET menu.php` | 200, `data` array, `meta.total` | |
| R2 | Detail by id | `?id={id}` | 200 | |
| R3 | Id tidak ada | `?id=999999` | 404 | |
| R4 | Id bukan angka | `?id=abc` | 400 | |
| R5 | Filter kategori | `?kategori=Minuman` | 200, semua item Minuman | |
| R6 | Kategori tidak dikenal | `?kategori=Seafood` | 400 | |
| R7 | Pencarian | `?q=Uji CRUD` | 200, minimal 1 hasil | |
| R8 | Pencarian berisi injection | `?q=' OR 1=1 --` | 200, 0 hasil | |
| R9 | Pagination | `?page=1&per_page=2` | maks 2 item, meta lengkap | |
| R10 | per_page tidak valid | `?per_page=0` | 400 | |
| R11 | page tidak valid | `?page=abc` | 400 | |
| R12 | Halaman di luar jangkauan | `?page=99999&per_page=2` | 200, `data: []` | |
| R13 | UI: filter + cari + pagination | di `index.html` dan di tabel `admin.html`: pilih kategori, ketik kata kunci, tombol Sebelumnya/Berikutnya | daftar dan info "Halaman X dari Y" ikut berubah (*manual*) | |

## Update

| ID | Skenario | Input | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| U1 | PUT valid | semua field baru | 200, data berubah | |
| U2 | PATCH sebagian | `{"harga":20000}` | 200, hanya harga berubah | |
| U3 | PUT tidak valid | nama kosong, harga `abc` | 422, `errors` per field | |
| U4 | Data utuh setelah gagal | GET setelah U3 | nilai lama tetap | |
| U5 | Id tidak ada | `PUT ?id=999999` | 404 | |
| U6 | PATCH body kosong | `{}` | 400 | |
| U7 | PUT tanpa id | `PUT menu.php` | 400 | |
| U8 | PATCH stok negatif | `{"stok":-1}` | 422 | |
| U9 | UI: Edit lalu simpan | di tabel `admin.html` klik Edit, ubah, Simpan Perubahan | baris tabel berubah tanpa reload (*manual*) | |

## Delete

| ID | Skenario | Input | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| D1 | Hapus data ada | `DELETE ?id={id}` | 204, tanpa body | |
| D2 | GET setelah hapus | `?id={id}` | 404 | |
| D3 | Hapus ulang | `DELETE ?id={id}` | 404 | |
| D4 | Tanpa id | `DELETE menu.php` | 400 | |
| D5 | Id bukan angka | `?id=abc` | 400 | |
| D6 | Menu dipakai pesanan | insert baris `pesanan` + `detail_pesanan` untuk menu itu, lalu DELETE | 409, data tetap ada (*manual*) | |
| D7 | UI: konfirmasi hapus | klik Hapus lalu Batal / OK | Batal: tidak ada request; OK: baris hilang dari tabel (*manual*) | |

## Error handling umum

| ID | Skenario | Cara uji | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| E1 | Method tidak diizinkan | `DELETE health.php` | 405 + header `Allow` | |
| E2 | Format error konsisten | respons 400/404/422 | selalu `{status:"error", message, errors?}` | |
| E3 | Error DB tidak bocor | set `APP_ENV=production`, ubah `DB_PASS` salah, buka `menu.php` | 500, body hanya "Terjadi kesalahan pada server."; tanpa password/stack trace; detail ada di log Apache/PHP (*manual*) | |