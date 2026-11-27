# TEST_PLAN - A2 Coffee and Food

Pengembang: **ISI NAMA / NIM** | Tanggal uji: 27 November 2026 | Lingkungan lokal: Laragon, Apache PHP 8.4, `http://localhost/pemweb-proyek%20semester/` | Production/staging: **ISI URL**

## 1. Ruang lingkup dan risiko

Aplikasi: landing page UMKM + REST API menu (PHP/MySQL) + panel admin dengan login, role `admin`/`kasir`, CSRF, dan pembatasan login.

| Risiko | Dampak | Test case yang menutup |
|---|---|---|
| Akses tanpa hak (anonim/kasir mengubah data) | Data menu rusak/hilang | TC-03, TC-04, TC-14 |
| Input tidak valid / injeksi | Data kotor, SQL injection | TC-05, TC-06, TC-07, TC-11, TC-12, TC-18 |
| Brute force login | Akun admin diambil alih | TC-02, TC-13 |
| Dependency gagal (DB / API cuaca) | Halaman kosong atau error teknis bocor ke pengguna | TC-08, TC-09, TC-10 |
| Konfigurasi production salah | `.env`, kode sumber, atau stack trace terbuka | TC-09, TC-19, TC-20 |
| Layout/aksesibilitas | Pengguna ponsel & keyboard tidak bisa memakai | TC-16, TC-17 |

Cakupan wajib modul: happy path (TC-01), invalid input (TC-05, 06, 11, 12), unauthorized (TC-03, 04, 14), empty state (TC-08), error dependency (TC-09, 10).

## 2. Cara menjalankan

Dari folder proyek, dengan PHP 8.4 (Laragon):

```powershell
$U = "http://localhost/pemweb-proyek%20semester"
$env:ADMIN_EMAIL="admin@a2coffee.test"; $env:ADMIN_PASSWORD="PasswordKuat123"
$env:KASIR_EMAIL="kasir@a2coffee.test"; $env:KASIR_PASSWORD="PasswordKasir123"

php tests/unit_test.php                          # unit test tanpa DB
php tests/api_test.php "$U/api/menu.php"         # integration test API (CRUD, auth, role)
php tests/smoke_test.php "$U/"                   # regression check lokal / production
php tests/security_retest.php "$U/api/menu.php"  # CSRF, lockout, header, log (jalankan terakhir)
```

Setelah `security_retest.php`, jalankan `DELETE FROM login_attempts;` agar login admin tidak terkunci. Jangan jalankan `security_retest.php` di production yang dipakai pengguna sungguhan.

Kolom hasil: **Baseline** = hasil lokal 27 Nov 2026 pagi (kode backend belum disentuh Modul 14); **Regresi lokal** = setelah semua perbaikan Modul 14 diterapkan; **Prod** = setelah deploy. Sel `ISI` wajib diganti dengan hasil sebenarnya. Bukti (screenshot/output) dilampirkan di laporan dengan penomoran sesuai ID test case.

## 3. Test case

| ID | Jenis | Skenario & input | Expected result | Actual / Baseline lokal | Regresi lokal | Prod | Bukti |
|---|---|---|---|---|---|---|---|
| TC-01 | Happy path | Login admin dengan email + password benar | 200, `role: admin`, tanpa `password_hash` | 200, role admin, tanpa hash - PASS (A12) | ISI (ulang `api_test.php` dengan URL benar) | ISI | Lampiran TC-01 |
| TC-02 | Auth | Password salah / email tidak terdaftar | 401, pesan generik sama untuk keduanya | 401, pesan sama - PASS (A9, A10) | ISI | ISI | Lampiran TC-02 |
| TC-03 | Role | Login kasir lalu `DELETE`/`PUT` pada menu | 403 | 403 - PASS (`security_retest` T6.2, T6.3) | PASS 27 Nov 2026 (T6.2, T6.3); K1-K7: ISI | ISI | Lampiran TC-03 |
| TC-04 | Unauthorized | `POST/PUT/PATCH/DELETE menu.php` tanpa login | 401, data tidak berubah | 401 untuk keempatnya, jumlah menu tetap - PASS (A2-A6) | ISI | ISI | Lampiran TC-04 |
| TC-05 | Invalid input | `POST` dengan `nama: ""` | 422 + `errors.nama` | 422 - PASS (C2) | ISI | ISI | Lampiran TC-05 |
| TC-06 | Invalid input | `POST` dengan `harga: -5` | 422 + `errors.harga` | 422 - PASS (C3) | ISI | ISI | Lampiran TC-06 |
| TC-07 | Keamanan | `nama: "x'); DROP TABLE menu;--"` lalu `GET menu.php` | 201 tersimpan sebagai teks; tabel tetap ada (200) | 201 lalu 200 - PASS (C7, C8) | ISI | ISI | Lampiran TC-07 |
| TC-08 | Empty state | `GET menu.php?page=99999&per_page=2`; di UI cari kata yang tidak ada | 200 `data: []`; UI menampilkan "Menu tidak ditemukan." | API: 200 `[]` - PASS (R12; juga smoke S5). UI: ISI | smoke S5 PASS 27 Nov 2026; UI: ISI | ISI | Lampiran TC-08 |
| TC-09 | Error dependency (DB) | `APP_ENV=production`, `DB_PASS` sengaja salah, buka `menu.php`. Cek `display_errors=Off` (Latihan E.3) | 500, body hanya "Terjadi kesalahan pada server."; tanpa password/stack trace; detail hanya di log server | Pengamatan nyata: dengan `APP_ENV=local` dan `APP_DEBUG=true`, respons 500 memuat `errors.debug` (mode debug, hanya lokal). Mode production: ISI | ISI | ISI | Lampiran TC-09 |
| TC-10 | Error dependency (API cuaca) | DevTools > Network > blokir `api.open-meteo.com`, muat ulang `index.html` | Dashboard menampilkan pesan error ramah + tombol "Coba Lagi"; menu tetap tampil; Coba Lagi berhasil saat online | ISI | ISI | ISI | Lampiran TC-10 |
| TC-11 | Invalid input | `POST` dengan JSON rusak | 400 | 400 - PASS (C5) | ISI | ISI | Lampiran TC-11 |
| TC-12 | Invalid input | `POST` dengan `Content-Type: application/x-www-form-urlencoded` | 415 | 415 - PASS (C6) | ISI | ISI | Lampiran TC-12 |
| TC-13 | Brute force | 6x login salah berturut-turut | percobaan ke-6 -> 429 + header `Retry-After` | 401 x5, lalu 429; `Retry-After: 895` - PASS (T4.1-T4.3) | PASS 27 Nov 2026 (`Retry-After: 894`) | ISI | Lampiran TC-13 |
| TC-14 | CSRF | `POST/PATCH/DELETE` dengan sesi login tapi tanpa/salah token CSRF | 403, data tidak berubah | 403, data tetap - PASS (T5.4-T5.7) | PASS 27 Nov 2026 | ISI | Lampiran TC-14 |
| TC-15 | Integritas data | Hapus menu yang sudah dipakai `detail_pesanan` | 409, menu tetap ada | ISI (manual) | ISI | ISI | Lampiran TC-15 |
| TC-16 | Responsive | Buka `index.html` pada lebar 320 px | Tanpa horizontal scroll; teks hero dan "Tentang Kami" punya margin kiri-kanan | Sebelum: padding `#beranda`/`#tentang` = 0 px (teks menempel ke tepi). Sesudah: 16 px | ISI (verifikasi di Chrome DevTools, lebar 320) | ISI | Lampiran TC-16 |
| TC-17 | Keyboard/a11y | Muat `index.html`, tekan Tab sekali, lalu Enter | Fokus pertama = "Lewati ke konten utama"; Enter memindahkan fokus ke konten | Sebelum: Tab pertama = "Beranda", tidak ada skip link. Sesudah: ISI (verifikasi di browser Anda) | ISI | ISI | Lampiran TC-17 |
| TC-18 | Unit (negative test baru) | `php tests/unit_test.php`: harga desimal `"10.5"`, stok string negatif `"-1"`, nama array, kategori beda huruf besar, dll. | Semua input tidak valid ditolak dengan `errors.<field>`; batas nilai lolos | 21 dari 21 PASS (27 Nov 2026, PHP CLI 8.0.30) | ISI (ulang di PHP 8.4 Laragon) | n/a | Lampiran TC-18 |
| TC-19 | Config production | `/.env`, `/.git/config`, `/src/Auth.php`, `/database/schema.sql`, `/storage/logs/security.log`, `composer.json/lock`, `valid.json` tidak dapat diunduh | 403/404, tidak pernah 200 | 403/404 - PASS (smoke S9-S17; `security_retest` T1.1-T1.16) | PASS 27 Nov 2026 (smoke 20/20) | ISI | Lampiran TC-19 |
| TC-20 | Config production | Header `nosniff`, CSP, anti-clickjacking, HSTS; `http://` dialihkan ke `https://` | Semua ada; redirect 301 | nosniff, CSP, frame-ancestors - PASS (S18-S20). HSTS dan redirect (S21-S22): belum diuji (butuh HTTPS) | smoke S18-S20 PASS 27 Nov 2026 | ISI (S21-S22 wajib) | Lampiran TC-20 |

Total: 20 test case (rubrik meminta minimal 12). `Test_matrix.md` (matriks CRUD lengkap) dilampirkan sebagai bukti tambahan.

## 4. Catatan eksekusi

- Hasil baseline `api_test.php` sebelumnya (21 Nov 2026) tercatat di `Test_matrix.md`. Hasil terakhir yang sah untuk `api_test.php` perlu dijalankan ulang dengan URL yang benar (`"$U/api/menu.php"`).
- Ringkasan hasil terbaru: smoke 20/20 PASS; security retest 51/51 PASS (termasuk uji role kasir); unit test 21/21 PASS.
- PHP CLI di mesin pengembang semula 8.0.30 (bukan 8.4), sehingga `database/buat_admin.php` yang memakai `vendor/` gagal di terminal biasa. Dijalankan ulang dengan binary PHP 8.4 dari Laragon.

## 5. Temuan dan tindak lanjut

| ID test | Temuan | Perbaikan | Status uji ulang |
|---|---|---|---|
| (sesi uji) | `DB_USER` kosong di `.env` menyebabkan `api/menu.php` 500 ("Access denied", `using password: NO`) | Mengisi `DB_USER`/`DB_PASS` dengan user `a2coffee_app` | PASS (API 200) |
| (sesi uji) | Skrip uji memakai URL bawaan `pemweb-obe` sehingga banyak 404 (salah target, bukan bug aplikasi) | Menjalankan dengan URL proyek yang benar | PASS |
| (sesi uji) | PHP CLI 8.0.30 tidak memenuhi syarat `>=8.4` | Memakai PHP 8.4 Laragon untuk CLI | ISI |
| TC-16 | Padding samping `#beranda` dan `#tentang` bernilai 0 px di bawah ±1100 px karena `calc()` negatif | `max(5%, calc(...))` di `Css/StyleSheet.css` | ISI |
| TC-17 | Tidak ada skip link; fokus pertama langsung ke menu | Skip link + `<main tabindex="-1">` | ISI |
| (audit) | Kontras huruf placeholder gambar menu 2,18 : 1 | `color: var(--primary-color)` (6,47 : 1) | ISI |
| (log) | Waktu di audit log dan output tes memakai zona UTC+1 (tanpa `date.timezone`), selisih 7 jam dari WITA | ISI (opsional: `date_default_timezone_set('Asia/Makassar')`) | ISI |
| ISI | ISI temuan lain dari eksekusi nyata | ISI | ISI |