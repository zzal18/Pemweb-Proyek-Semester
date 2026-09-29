import {
    ringkasMenu, cariMenuById, filterByKategori, formatRupiah,
    cariMenuByNama, simpanPreferensi, ambilPreferensi
} from './utils.js';

const daftarMenu = [
    { id: 1, nama: 'A2 Coffee',    kategori: 'Minuman', harga: 12000, stok: 25, status: 'Tersedia',
      deskripsi: 'Kopi susu dengan perpaduan kopi dan susu yang cocok dinikmati kapan saja.',
      gambar: 'Images/A2.png' },
    { id: 2, nama: 'Cireng A2',    kategori: 'Cemilan', harga: 8000,  stok: 15, status: 'Tersedia',
      deskripsi: 'Cemilan yang lumayan diminati oleh pembeli.',
      gambar: 'Images/Cireng A2.jpg' },
    { id: 3, nama: 'Es Teh Manis', kategori: 'Minuman', harga: 5000,  stok: 0,  status: 'Habis',
      deskripsi: 'Teh manis dingin yang menyegarkan.',
      gambar: '' },
    { id: 4, nama: 'Roti Bakar',   kategori: 'Cemilan', harga: 10000, stok: 10, status: 'Tersedia',
      deskripsi: 'Roti bakar hangat dengan topping pilihan.',
      gambar: '' }
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

// ===================== DOM & RENDER =====================
const menu = document.querySelector('#menu');
const infoHasil = document.querySelector('#info-hasil');

function buatCard(item) {
    const card = document.createElement('article');
    card.dataset.id = item.id;

    const judul = document.createElement('h3');
    judul.textContent = item.nama;
    card.append(judul);

    if (item.gambar) {
        const gambar = document.createElement('img');
        gambar.src = item.gambar;
        gambar.alt = item.nama;
        card.append(gambar);
    }

    const badge = document.createElement('small');
    badge.className = item.status === 'Tersedia' ? 'badge badge-ok' : 'badge badge-habis';
    badge.textContent = item.status;

    const info = document.createElement('p');
    info.textContent = `${item.kategori} - ${formatRupiah(item.harga)}`;

    const detail = document.createElement('p');
    detail.className = 'detail';
    detail.hidden = true;

    const tombolDetail = document.createElement('button');
    tombolDetail.type = 'button';
    tombolDetail.className = 'btn-detail';
    tombolDetail.dataset.aksi = 'detail';
    tombolDetail.textContent = 'Detail';
    tombolDetail.setAttribute('aria-expanded', 'false');

    card.append(badge, info, detail, tombolDetail);
    return card;
}

function renderMenu(items) {
    // Hapus hanya kartu lama; judul, filter, dan input pencarian tetap ada
    menu.querySelectorAll('article').forEach(card => card.remove());

    for (const item of items) {
        menu.append(buatCard(item));
    }

    infoHasil.textContent = items.length === 0
        ? 'Menu tidak ditemukan.'
        : `Menampilkan ${items.length} dari ${daftarMenu.length} menu.`;
}

// ===================== STATE, FILTER & PENCARIAN =====================
const tombolFilter = document.querySelectorAll('[data-filter]');
const inputCari = document.querySelector('#cari-menu');

const state = {
    kategori: 'Semua',
    kataKunci: ''
};

function terapkanFilter() {
    const berdasarkanKategori = state.kategori === 'Semua'
        ? daftarMenu
        : filterByKategori(daftarMenu, state.kategori);

    renderMenu(cariMenuByNama(berdasarkanKategori, state.kataKunci));
}

function tandaiFilterAktif(kategori) {
    tombolFilter.forEach(tombol => {
        const aktif = tombol.dataset.filter === kategori;
        tombol.classList.toggle('active', aktif);
        tombol.setAttribute('aria-pressed', String(aktif));
    });
}

// Interaksi 1: klik filter kategori
tombolFilter.forEach(tombol => {
    tombol.addEventListener('click', () => {
        state.kategori = tombol.dataset.filter;
        tandaiFilterAktif(state.kategori);
        simpanPreferensi('a2-kategori', state.kategori);
        terapkanFilter();
    });
});

// Interaksi 2: ketik untuk mencari
inputCari.addEventListener('input', () => {
    state.kataKunci = inputCari.value;
    terapkanFilter();
});

// ===================== EVENT DELEGATION: DETAIL =====================
// Satu listener di #menu; berlaku juga untuk kartu yang dibuat ulang saat render.
menu.addEventListener('click', (event) => {
    const tombol = event.target.closest('[data-aksi="detail"]');
    if (!tombol) return;

    const card = tombol.closest('article');
    const detail = card.querySelector('.detail');
    const sedangTertutup = detail.hidden;

    if (sedangTertutup) {
        try {
            const item = cariMenuById(daftarMenu, Number(card.dataset.id));
            detail.textContent = `${item.deskripsi} Stok tersisa: ${item.stok}.`;
        } catch (error) {
            detail.textContent = error.message;
        }
    }

    detail.hidden = !sedangTertutup;
    tombol.textContent = sedangTertutup ? 'Tutup' : 'Detail';
    tombol.setAttribute('aria-expanded', String(sedangTertutup));
});

// ===================== TEMA (WEB STORAGE) =====================
const tombolTema = document.querySelector('#theme-button');

function terapkanTema(tema) {
    document.documentElement.dataset.theme = tema;
    tombolTema.textContent = tema === 'dark' ? 'Tema Terang' : 'Tema Gelap';
    tombolTema.setAttribute('aria-pressed', String(tema === 'dark'));
}

tombolTema.addEventListener('click', () => {
    const temaBaru = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    terapkanTema(temaBaru);
    simpanPreferensi('a2-theme', temaBaru);
});

// ===================== FORM KONTAK =====================
const formKontak = document.querySelector('#kontak form');
const statusForm = document.querySelector('#status-form');

function tampilkanStatus(pesan, sukses) {
    statusForm.textContent = pesan;               // textContent = aman dari XSS
    statusForm.className = sukses ? 'status-ok' : 'status-error';
}

formKontak.addEventListener('submit', (event) => {
    event.preventDefault();

    const nama = formKontak.elements.nama.value.trim();
    const pesan = formKontak.elements.pesan.value.trim();

    if (nama.length < 3) {
        tampilkanStatus('Nama minimal 3 karakter.', false);
        return;
    }
    if (pesan.length < 10) {
        tampilkanStatus('Pesan minimal 10 karakter.', false);
        return;
    }

    tampilkanStatus(`Terima kasih, ${nama}! Pesan Anda sudah kami terima.`, true);
    formKontak.reset();
});

// ===================== INISIALISASI =====================
const temaTersimpan = ambilPreferensi('a2-theme', 'light');
terapkanTema(temaTersimpan === 'dark' ? 'dark' : 'light');

const kategoriTersimpan = ambilPreferensi('a2-kategori', 'Semua');
const kategoriValid = ['Semua', 'Minuman', 'Cemilan'].includes(kategoriTersimpan);
state.kategori = kategoriValid ? kategoriTersimpan : 'Semua';
tandaiFilterAktif(state.kategori);

terapkanFilter();

// ===================== LOG PENGUJIAN (Modul 4) =====================
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