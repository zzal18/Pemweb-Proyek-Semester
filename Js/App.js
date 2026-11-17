import {
    ringkasMenu, cariMenuById, formatRupiah,
    simpanPreferensi, ambilPreferensi,
    ambilJson, panggilApi,
    terjemahkanKodeCuaca, rekomendasiMenu
} from './utils.js';

let daftarMenu = [];   // semua menu (untuk ringkasan dashboard)
let halamanMenu = [];  // menu pada halaman yang sedang tampil

// DOM & RENDER 
const menu = document.querySelector('#menu');
const infoHasil = document.querySelector('#info-hasil');
const ENDPOINT_MENU = 'api/menu.php';
const tombolRetryMenu = document.querySelector('#btn-retry-menu');
const paginasi = document.querySelector('#paginasi-menu');
const tombolSebelumnya = document.querySelector('#btn-sebelumnya');
const tombolBerikutnya = document.querySelector('#btn-berikutnya');
const infoHalaman = document.querySelector('#info-halaman');
const PER_HALAMAN = 6;

function buatPlaceholder(nama) {
    const kosong = document.createElement('div');
    kosong.className = 'gambar-kosong';
    kosong.setAttribute('role', 'img');
    kosong.setAttribute('aria-label', `Foto ${nama} belum tersedia`);
    kosong.textContent = nama.charAt(0).toUpperCase();
    return kosong;
}

function buatGambar(item) {
    if (!item.gambar) return buatPlaceholder(item.nama);

    const gambar = document.createElement('img');
    gambar.src = item.gambar;
    gambar.alt = item.nama;
    gambar.loading = 'lazy';
    gambar.addEventListener(
        'error',
        () => gambar.replaceWith(buatPlaceholder(item.nama)),
        { once: true }
    );
    return gambar;
}

function buatCard(item) {
    const card = document.createElement('article');
    card.dataset.id = item.id;

    const judul = document.createElement('h3');
    judul.textContent = item.nama;
    card.append(judul);

    card.append(buatGambar(item));

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

    const aksi = document.createElement('div');
    aksi.className = 'aksi-kartu';
    aksi.append(tombolDetail);

    card.append(badge, info, detail, aksi);
    return card;
}

function renderPaginasi(meta) {
    const totalHalaman = meta?.total_pages ?? 1;
    paginasi.hidden = totalHalaman <= 1;
    infoHalaman.textContent = `Halaman ${state.page} dari ${totalHalaman}`;
    tombolSebelumnya.disabled = state.page <= 1;
    tombolBerikutnya.disabled = state.page >= totalHalaman;
}

function renderMenu(items, meta = null) {
    menu.querySelectorAll('article').forEach(card => card.remove());

    for (const item of items) {
        paginasi.before(buatCard(item));
    }

    const total = meta?.total ?? items.length;
    infoHasil.textContent = items.length === 0
        ? 'Menu tidak ditemukan.'
        : `Menampilkan ${items.length} dari ${total} menu.`;
    renderPaginasi(meta);
}

    const tombolFilter = document.querySelectorAll('[data-filter]');
    const inputCari = document.querySelector('#cari-menu');

const state = {
    kategori: 'Semua',
    kataKunci: '',
    page: 1
};

// Filter/pencarian dikerjakan server (?kategori=&q=&page=); ganti filter -> kembali ke halaman 1.
function terapkanFilter() {
    state.page = 1;
    muatMenu();
}

function tandaiFilterAktif(kategori) {
    tombolFilter.forEach(tombol => {
        const aktif = tombol.dataset.filter === kategori;
        tombol.classList.toggle('active', aktif);
        tombol.setAttribute('aria-pressed', String(aktif));
    });
}

    tombolFilter.forEach(tombol => {
    tombol.addEventListener('click', () => {
        state.kategori = tombol.dataset.filter;
        tandaiFilterAktif(state.kategori);
        simpanPreferensi('a2-kategori', state.kategori);
        terapkanFilter();
    });
});

let timerCari = null;
inputCari.addEventListener('input', () => {
    state.kataKunci = inputCari.value;
    clearTimeout(timerCari);
    timerCari = setTimeout(terapkanFilter, 300);   // debounce agar tidak memanggil API tiap ketikan
});

tombolSebelumnya.addEventListener('click', () => {
    if (state.page > 1) {
        state.page -= 1;
        muatMenu();
    }
});

tombolBerikutnya.addEventListener('click', () => {
    state.page += 1;
    muatMenu();
});

function ubahDetail(tombol, card) {
    const detail = card.querySelector('.detail');
    const sedangTertutup = detail.hidden;

    if (sedangTertutup) {
        try {
            const item = cariMenuById(halamanMenu, Number(card.dataset.id));
            detail.textContent = `${item.deskripsi} Stok tersisa: ${item.stok}.`;
        } catch (error) {
            detail.textContent = error.message;
        }
    }

    detail.hidden = !sedangTertutup;
    tombol.textContent = sedangTertutup ? 'Tutup' : 'Detail';
    tombol.setAttribute('aria-expanded', String(sedangTertutup));
}

menu.addEventListener('click', (event) => {
    const tombol = event.target.closest('[data-aksi]');
    if (!tombol) return;

    if (tombol.dataset.aksi === 'detail') ubahDetail(tombol, tombol.closest('article'));
});

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

    function validateForm(data) {
    const errors = {};

    const nama = String(data.get('nama') ?? '').trim();
    const email = String(data.get('email') ?? '').trim();
    const telepon = String(data.get('telepon') ?? '').trim().replace(/[\s-]/g, '');
    const jenis = String(data.get('jenis') ?? '');
    const pesan = String(data.get('pesan') ?? '').trim();

    if (nama === '') {
        errors.nama = 'Nama wajib diisi.';
    } else if (nama.length < 3) {
        errors.nama = 'Nama minimal 3 karakter.';
    } else if (nama.length > 60) {
        errors.nama = 'Nama maksimal 60 karakter.';
    } else if (!/^[\p{L}\s.'-]+$/u.test(nama)) {
        errors.nama = "Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.";
    }

        if (email === '') {
        errors.email = 'Email wajib diisi.';
    }   else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        errors.email = 'Format email tidak valid. Contoh: nama@contoh.com.';
    }

        if (telepon !== '' && !/^(\+62|62|0)8\d{8,11}$/.test(telepon)) {
        errors.telepon = 'Nomor tidak valid. Gunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx (10-13 digit).';
    }

        if (jenis === '') {
        errors.jenis = 'Pilih jenis pesan.';
    } else if (!Object.hasOwn(JENIS_PESAN, jenis)) {
        errors.jenis = 'Jenis pesan tidak tersedia. Pilih dari daftar.';
    }

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

formKontak.addEventListener('input', (event) => {
    const field = event.target;
    const errorEl = document.querySelector(`#error-${field.name}`);
    if (errorEl) errorEl.textContent = '';
    field.removeAttribute('aria-invalid');
    
    if (field.name === 'pesan') perbaruiHitungan();
});

// DASHBOARD & REST API
const USE_LOCAL_DATA = false;
const ENDPOINT_CUACA = USE_LOCAL_DATA
    ? './data/cuaca.json'
    : 'https://api.open-meteo.com/v1/forecast?latitude=3.3274&longitude=117.5786&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=Asia%2FMakassar';

const ringkasanMenuEl = document.querySelector('#ringkasan-menu');
const statusCuaca = document.querySelector('#status-cuaca');
const hasilCuaca = document.querySelector('#hasil-cuaca');
const cuacaList = document.querySelector('#cuaca-list');
const rekomendasiCuaca = document.querySelector('#rekomendasi-cuaca');
const tombolRetry = document.querySelector('#btn-retry-cuaca');

function buatKartu(label, nilai) {
    const kartu = document.createElement('div');
    kartu.className = 'kartu';

    const angka = document.createElement('strong');
    angka.className = 'kartu-nilai';
    angka.textContent = nilai;

    const teks = document.createElement('span');
    teks.textContent = label;

    kartu.append(angka, teks);
    return kartu;
}

function renderRingkasanMenu() {
    const ringkasan = ringkasMenu(daftarMenu);

    const baris = [
        ['Jumlah Menu', ringkasan.jumlahMenu],
        ['Total Stok', ringkasan.totalStok],
        ['Menu Habis', ringkasan.menuHabis],
        ['Nilai Stok', formatRupiah(ringkasan.totalNilaiStok)]
    ];

    ringkasanMenuEl.replaceChildren();
    for (const [label, isi] of baris) {
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = isi;
        ringkasanMenuEl.append(dt, dd);
    }
}
function tampilkanStatusCuaca(pesan, kelas) {
    statusCuaca.textContent = pesan;
    statusCuaca.className = kelas;
}

function renderCuaca(current, satuan) {
    const baris = [
        ['Waktu', current.time.replace('T', ' ')],
        ['Kondisi', terjemahkanKodeCuaca(current.weather_code)],
        ['Suhu', `${current.temperature_2m} ${satuan.temperature_2m}`],
        ['Kelembapan', `${current.relative_humidity_2m} ${satuan.relative_humidity_2m}`],
        ['Kecepatan angin', `${current.wind_speed_10m} ${satuan.wind_speed_10m}`]
    ];

    cuacaList.replaceChildren();
    for (const [label, isi] of baris) {
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = isi;
        cuacaList.append(dt, dd);
    }

    rekomendasiCuaca.textContent = rekomendasiMenu(current.temperature_2m, current.weather_code);
    hasilCuaca.hidden = false;
}

async function muatCuaca() {
    hasilCuaca.hidden = true;
    tombolRetry.hidden = true;
    tampilkanStatusCuaca('Memuat data cuaca...', 'status-loading');

    try {
        const data = await ambilJson(ENDPOINT_CUACA);
        if (!data.current) {
            tampilkanStatusCuaca('Data cuaca belum tersedia.', 'status-empty');
            return;
        }

        renderCuaca(data.current, data.current_units);
        tampilkanStatusCuaca('Data cuaca berhasil dimuat.', 'status-ok');
    } catch (error) {
        console.error(error);
        tampilkanStatusCuaca('Data cuaca belum dapat dimuat. Coba kembali.', 'status-error');
        tombolRetry.hidden = false;
    }
}

tombolRetry.addEventListener('click', muatCuaca);

const temaTersimpan = ambilPreferensi('a2-theme', 'light');
terapkanTema(temaTersimpan === 'dark' ? 'dark' : 'light');

const kategoriTersimpan = ambilPreferensi('a2-kategori', 'Semua');
const kategoriValid = ['Semua', 'Minuman', 'Cemilan'].includes(kategoriTersimpan);
state.kategori = kategoriValid ? kategoriTersimpan : 'Semua';
tandaiFilterAktif(state.kategori);

// ===== MEMUAT MENU DARI API (publik, hanya baca) =====
let permintaanTerakhir = 0;

async function muatMenu() {
    const nomor = ++permintaanTerakhir;   // abaikan respons usang jika ada request lebih baru
    tombolRetryMenu.hidden = true;
    infoHasil.textContent = 'Memuat menu...';

    const params = new URLSearchParams({ page: String(state.page), per_page: String(PER_HALAMAN) });
    if (state.kategori !== 'Semua') params.set('kategori', state.kategori);
    const kunci = state.kataKunci.trim();
    if (kunci !== '') params.set('q', kunci);

    try {
        const hasil = await panggilApi(`${ENDPOINT_MENU}?${params}`);
        if (nomor !== permintaanTerakhir) return;
        if (!Array.isArray(hasil?.data) || !hasil.meta) {
            throw new Error('Format respons menu tidak dikenali');
        }

        // Halaman kosong (mis. data berkurang) -> mundur ke halaman terakhir yang valid
        if (hasil.data.length === 0 && state.page > 1) {
            state.page = hasil.meta.total_pages;
            return muatMenu();
        }

        halamanMenu = hasil.data;
        renderMenu(halamanMenu, hasil.meta);
    } catch (error) {
        if (nomor !== permintaanTerakhir) return;
        console.error(error);
        halamanMenu = [];
        renderMenu([]);
        infoHasil.textContent = 'Menu belum dapat dimuat. Coba kembali.';
        tombolRetryMenu.hidden = false;
    }
}

async function muatRingkasan() {
    try {
        const hasil = await panggilApi(ENDPOINT_MENU);
        daftarMenu = Array.isArray(hasil?.data) ? hasil.data : [];
        renderRingkasanMenu();
    } catch (error) {
        console.error(error);
    }
}

function segarkanData() {
    return Promise.all([muatMenu(), muatRingkasan()]);
}

tombolRetryMenu.addEventListener('click', muatMenu);

segarkanData();
muatCuaca();