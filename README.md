# A2 Coffee and Food - Website Landing Page

## Fitur & Spesifikasi CSS

- CSS Custom Properties (Variables): Mengelola tema warna khas kopi (--primary-color, --secondary-color), spasi, border-radius, serta bayangan (--shadow) secara terpusat pada :root.
- Flexbox & Grid Layout: 
  - Flexbox: Digunakan pada elemen header, nav, article, dan form untuk perataan konten secara dinamis.
  - CSS Grid: Digunakan pada section #beranda (Hero) dan section #menu untuk tata letak kolom yang responsif.
- Aksesibilitas (:focus-visible): Navigasi keyboard terintegrasi dengan indikator fokus berwarna aksen (--accent-color) pada tautan, tombol, dan elemen formulir.
- Tipografi Fluid: Menggunakan fungsi clamp() pada judul utama #beranda h2 agar ukuran font menyesuaikan lebar layar secara halus.

---

## Komponen Reusable

1. article (Card Component)
   - Bertindak sebagai kartu produk menu yang dikelola dengan Flexbox vertikal.
   - Dilengkapi dengan hover effect (translateY) dan batas transisi yang halus.
2. button[type="submit"] (Button Component)
   - Komponen tombol interaktif untuk formulir dengan efek hover perubahan warna latar belakang.
3. section & #tentang (Section Layout)
   - Memiliki variasi latar belakang menggunakan variabel --secondary-color untuk pemisahan visual antar bagian secara teratur.

---

## Catatan Keputusan Desain

1. Penggunaan Tag Semantik Murni: Keputusan struktur dibuat dengan memaksimalkan fungsi elemen HTML5 (seperti header, nav, section, article, dan form) secara langsung tanpa bergantung pada pembungkus div tambahan.
2. Skema Warna (Theme): Warna hangat bertema kopi (#6f4e37 dan #f5eee6) dipilih untuk memperkuat identitas merek kuliner UMKM.
3. Penyelarasan Responsif:
   - Layout dasar menggunakan pendekatan tata letak Desktop multi-kolom.
   - Menggunakan Media Query untuk mereset grid menjadi 1 kolom pada layar tablet & mobile agar konten mudah dibaca.
4. Target Sentuh Mobile: Tombol kirim pesan dibuat berukuran full-width (100%) pada tampilan ponsel (breakpoint 500px) untuk memudahkan interaksi pada layar sentuh.

---

## Ringkasan Pengujian Responsif

- Desktop (> 800px): 
  - Header: Logo dan navigasi berdampingan secara horizontal.
  - Beranda (Hero): Grid 2 kolom (teks & gambar).
  - Menu: Grid 2 kolom berisi kartu menu (article).
- Tablet (<= 800px):
  - Header: Berubah menjadi mode vertikal di tengah.
  - Beranda & Menu: Otomatis menyesuaikan menjadi 1 kolom vertikal.
- Mobile (<= 500px):
  - Navigasi ringkas dengan spasi rapat.
  - Tombol pada formulir membesar memenuhi lebar layar (100%).