USE pemweb_obe_proyeksemester;

INSERT IGNORE INTO kategori (nama) VALUES ('Minuman'), ('Cemilan');

INSERT INTO menu (kategori_id, nama, deskripsi, harga, stok, gambar) VALUES
    ((SELECT id FROM kategori WHERE nama = 'Minuman'), 'A2 Coffee','Kopi susu dengan perpaduan kopi dan susu yang cocok dinikmati kapan saja.', 12000, 25, 'Images/a2-coffee.png'),
    ((SELECT id FROM kategori WHERE nama = 'Cemilan'), 'Cireng A2','Cemilan yang lumayan diminati oleh pembeli.', 8000, 15, 'Images/cireng-a2.jpg'),
    ((SELECT id FROM kategori WHERE nama = 'Minuman'), 'Es Teh Manis','Teh manis dingin yang menyegarkan.', 5000, 0, 'Images/es-teh-manis.jpg'),
    ((SELECT id FROM kategori WHERE nama = 'Cemilan'), 'Roti Bakar','Roti bakar hangat dengan topping pilihan.', 10000, 10, 'Images/roti-bakar.jpg'),
    ((SELECT id FROM kategori WHERE nama = 'Minuman'), 'Kopi Hitam','Kopi hitam yang bisa dinimmati pada waktu kapan saja.', 9000, 12, 'Images/kopi-hitam.jpg');