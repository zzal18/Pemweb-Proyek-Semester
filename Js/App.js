import { ringkasMenu, cariMenuById, filterByKategori, formatRupiah } from './utils.js';

const daftarMenu = [
    { id: 1, nama: 'A2 Coffee',    kategori: 'Minuman', harga: 12000, stok: 25, status: 'Tersedia' },
    { id: 2, nama: 'Cireng A2',    kategori: 'Cemilan', harga: 8000,  stok: 15, status: 'Tersedia' },
    { id: 3, nama: 'Es Teh Manis', kategori: 'Minuman', harga: 5000,  stok: 0,  status: 'Habis' },
    { id: 4, nama: 'Roti Bakar',   kategori: 'Cemilan', harga: 10000, stok: 10, status: 'Tersedia' }
];

const menuTersedia = daftarMenu.filter(item => item.status === 'Tersedia');

const namaMenu = daftarMenu.map(item => item.nama);

const totalStokMinuman = daftarMenu
    .filter(item => item.kategori === 'Minuman')
    .reduce((total, item) => total + item.stok, 0);

function buatLabelMenu({ nama, harga, stok }) {
    const hargaFormatted = formatRupiah(harga);
    return `${nama} - ${hargaFormatted} (stok: ${stok})`;
}

try {
    console.log('--- Menu Tersedia ---');
    console.table(menuTersedia);

    console.log('--- Semua Nama Menu ---');
    console.log(namaMenu);

    console.log('--- Total Stok Minuman ---');
    console.log(totalStokMinuman);

    console.log('--- Ringkasan Menu (dari utils.js) ---');
    console.log(ringkasMenu(daftarMenu));

    console.log('--- Menu Kategori Cemilan (dari utils.js) ---');
    console.log(filterByKategori(daftarMenu, 'Cemilan'));

    console.log('--- Label Menu ---');
    daftarMenu.forEach(item => console.log(buatLabelMenu(item)));

    console.log('--- Cari Menu id=2 (dari utils.js) ---');
    console.log(cariMenuById(daftarMenu, 2));

    console.log('--- Uji Error Handling: cari id=99 ---');
    console.log(cariMenuById(daftarMenu, 99));

} catch (error) {
    console.error('Terjadi masalah saat memproses data menu:', error.message);
}
