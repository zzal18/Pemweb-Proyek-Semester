import {
    ringkasMenu, cariMenuById, filterByKategori, formatRupiah,
    cariMenuByNama, simpanPreferensi, ambilPreferensi
} from './utils.js';

const daftarMenu = [
    { id: 1, nama: 'A2 Coffee',    kategori: 'Minuman', harga: 12000, stok: 25, status: 'Tersedia',
    deskripsi: 'Kopi susu dengan perpaduan kopi dan susu yang cocok dinikmati kapan saja.', gambar: 'Images/A2.png' },
    { id: 2, nama: 'Cireng A2',    kategori: 'Cemilan', harga: 8000,  stok: 15, status: 'Tersedia',
    deskripsi: 'Cemilan yang lumayan diminati oleh pembeli.', gambar: 'Images/Cireng A2.jpg' },
    { id: 3, nama: 'Es Teh Manis', kategori: 'Minuman', harga: 5000,  stok: 0,  status: 'Habis',
    deskripsi: 'Teh manis dingin yang menyegarkan.', gambar: '' },
    { id: 4, nama: 'Roti Bakar',   kategori: 'Cemilan', harga: 10000, stok: 10, status: 'Tersedia',
    deskripsi: 'Roti bakar hangat dengan topping pilihan.', gambar: '' }
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

// DOM & RENDER 
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
    menu.querySelectorAll('article').forEach(card => card.remove());

    for (const item of items) {
        menu.append(buatCard(item));
    }

    infoHasil.textContent = items.length === 0
        ? 'Menu tidak ditemukan.'
        : `Menampilkan ${items.length} dari ${daftarMenu.length} menu.`;
}

// STATE, FILTER & PENCARIAN
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

// EVENT DELEGATION: DETAIL 
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

// TEMA (WEB STORAGE)
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

//FORM KONTAK
// FORM KONTAK (MODUL 6)
const formKontak = document.querySelector('#form-kontak');
const statusForm = document.querySelector('#status-form');
const previewKontak = document.querySelector('#preview-kontak');
const previewKontakList = document.querySelector('#preview-kontak-list');
const hintPesan = document.querySelector('#hint-pesan');

const JENIS_PESAN = {
    saran: 'Saran',
    pertanyaan: 'Pertanyaan',
    pemesanan: 'Pemesanan',
    keluhan: 'Keluhan'
};
const BATAS_PESAN = 500;

function tampilkanStatus(pesan, sukses) {
    statusForm.textContent = pesan;
    statusForm.className = sukses ? 'status-ok' : 'status-error';
}

// Mengembalikan object errors; kosong jika semua data valid
function validateForm(data) {
    const errors = {};

    const nama = String(data.get('nama') ?? '').trim();
    const email = String(data.get('email') ?? '').trim();
    const telepon = String(data.get('telepon') ?? '').trim().replace(/[\s-]/g, '');
    const jenis = String(data.get('jenis') ?? '');
    const pesan = String(data.get('pesan') ?? '').trim();

    // Nama: kosong, terlalu pendek, terlalu panjang, karakter tidak valid
    if (nama === '') {
        errors.nama = 'Nama wajib diisi.';
    } else if (nama.length < 3) {
        errors.nama = 'Nama minimal 3 karakter.';
    } else if (nama.length > 60) {
        errors.nama = 'Nama maksimal 60 karakter.';
    } else if (!/^[\p{L}\s.'-]+$/u.test(nama)) {
        errors.nama = "Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.";
    }

    // Email: kosong vs format tidak valid
    if (email === '') {
        errors.email = 'Email wajib diisi.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        errors.email = 'Format email tidak valid. Contoh: nama@contoh.com.';
    }

    // Telepon: opsional, tapi jika diisi harus berformat nomor Indonesia
    if (telepon !== '' && !/^(\+62|62|0)8\d{8,11}$/.test(telepon)) {
        errors.telepon = 'Nomor tidak valid. Gunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx (10-13 digit).';
    }

    // Jenis pesan: wajib dan harus dari pilihan yang tersedia
    if (jenis === '') {
        errors.jenis = 'Pilih jenis pesan.';
    } else if (!Object.hasOwn(JENIS_PESAN, jenis)) {
        errors.jenis = 'Jenis pesan tidak tersedia. Pilih dari daftar.';
    }

    // Pesan: kosong, terlalu pendek, terlalu panjang
    if (pesan === '') {
        errors.pesan = 'Pesan wajib diisi.';
    } else if (pesan.length < 10) {
        errors.pesan = `Pesan minimal 10 karakter (baru ${pesan.length}).`;
    } else if (pesan.length > BATAS_PESAN) {
        errors.pesan = `Pesan maksimal ${BATAS_PESAN} karakter (sekarang ${pesan.length}).`;
    }

    return errors;
}

function bersihkanError() {
    formKontak.querySelectorAll('.error').forEach(el => (el.textContent = ''));
    formKontak.querySelectorAll('[aria-invalid="true"]')
        .forEach(el => el.removeAttribute('aria-invalid'));
}

function perbaruiHitungan() {
    const panjang = formKontak.elements.pesan.value.length;
    hintPesan.textContent = `${panjang}/${BATAS_PESAN} karakter`;
}

// Preview memakai textContent (bukan innerHTML) sebagai sanitasi awal
function tampilkanPreview(data) {
    const baris = [
        ['Nama', String(data.get('nama')).trim()],
        ['Email', String(data.get('email')).trim()],
        ['No. WhatsApp', String(data.get('telepon')).trim() || '-'],
        ['Jenis pesan', JENIS_PESAN[data.get('jenis')]],
        ['Pesan', String(data.get('pesan')).trim()]
    ];

    previewKontakList.replaceChildren();
    for (const [label, isi] of baris) {
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = isi;
        previewKontakList.append(dt, dd);
    }
    previewKontak.hidden = false;
}

formKontak.addEventListener('submit', (event) => {
    event.preventDefault();

    const data = new FormData(formKontak);
    const errors = validateForm(data);

    bersihkanError();
    previewKontak.hidden = true;

    if (Object.keys(errors).length > 0) {
        for (const [field, pesanError] of Object.entries(errors)) {
            document.querySelector(`#error-${field}`).textContent = pesanError;
            formKontak.elements[field]?.setAttribute('aria-invalid', 'true');
        }
        // Fokus ke field error pertama (urutan sesuai urutan di form)
        const urutan = ['nama', 'email', 'telepon', 'jenis', 'pesan'];
        const pertama = urutan.find(field => field in errors);
        formKontak.elements[pertama]?.focus();

        const jumlah = Object.keys(errors).length;
        tampilkanStatus(`Terdapat ${jumlah} isian yang perlu diperbaiki.`, false);
        return;
    }

    const nama = String(data.get('nama')).trim();
    tampilkanStatus(`Terima kasih, ${nama}! Data valid. Pesan belum dikirim ke server (simulasi).`, true);
    tampilkanPreview(data);
    formKontak.reset();
    perbaruiHitungan();
});

// Hapus error sebuah field begitu pengguna mulai memperbaikinya
formKontak.addEventListener('input', (event) => {
    const field = event.target;
    const errorEl = document.querySelector(`#error-${field.name}`);
    if (errorEl) errorEl.textContent = '';
    field.removeAttribute('aria-invalid');

    if (field.name === 'pesan') perbaruiHitungan();
});
//INISIALISASI
const temaTersimpan = ambilPreferensi('a2-theme', 'light');
terapkanTema(temaTersimpan === 'dark' ? 'dark' : 'light');

const kategoriTersimpan = ambilPreferensi('a2-kategori', 'Semua');
const kategoriValid = ['Semua', 'Minuman', 'Cemilan'].includes(kategoriTersimpan);
state.kategori = kategoriValid ? kategoriTersimpan : 'Semua';
tandaiFilterAktif(state.kategori);

terapkanFilter();

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