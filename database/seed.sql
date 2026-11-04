USE pemweb_obe_proyeksemester;

INSERT IGNORE INTO kategori (nama) VALUES ('Minuman'), ('Cemilan');

INSERT INTO menu (kategori_id, nama, deskripsi, harga, stok, gambar) VALUES
((SELECT id FROM kategori WHERE nama = 'Minuman'), 'A2 Coffee',
    'Kopi susu dengan perpaduan kopi dan susu yang cocok dinikmati kapan saja.', 12000, 25, 'Images/A2.png'),
((SELECT id FROM kategori WHERE nama = 'Cemilan'), 'Cireng A2',
    'Cemilan yang lumayan diminati oleh pembeli.', 8000, 15, 'Images/Cireng A2.jpg'),
((SELECT id FROM kategori WHERE nama = 'Minuman'), 'Es Teh Manis',
    'Teh manis dingin yang menyegarkan.', 5000, 0, NULL),
((SELECT id FROM kategori WHERE nama = 'Cemilan'), 'Roti Bakar',
    'Roti bakar hangat dengan topping pilihan.', 10000, 10, NULL);