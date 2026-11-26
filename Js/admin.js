import {
    formatRupiah, simpanPreferensi, ambilPreferensi,
    panggilApi, ApiError
} from './utils.js';

// Halaman admin terpisah dari situs publik. Ini hanya tampilan; hak akses sebenarnya dijaga
// server: setiap POST/PUT/PATCH/DELETE memeriksa sesi dan role admin (401 / 403).
const ENDPOINT_MENU = 'api/menu.php';
const ENDPOINT_AUTH = 'api/auth.php';
const PER_HALAMAN = 10;
const FIELD_MENU = ['nama', 'kategori', 'harga', 'stok', 'deskripsi'];

const sesi = { user: null, csrf: null };   // csrf: token anti-CSRF dari server, hanya disimpan di memori (bukan localStorage)
const isAdmin = () => sesi.user?.role === 'admin';
const state = { kategori: 'Semua', kataKunci: '', page: 1 };

let daftarHalaman = [];     // menu pada halaman tabel yang sedang tampil
let idDiedit = null;        // id menu yang sedang diedit (null = mode tambah)
let permintaanTerakhir = 0;

// ===== DOM =====
const statusSesi = document.querySelector('#status-sesi');
const barSesi = document.querySelector('#bar-sesi');
const infoSesi = document.querySelector('#info-sesi');
const formLogin = document.querySelector('#form-login');
const tombolLogin = document.querySelector('#btn-login');
const tombolLogout = document.querySelector('#btn-logout');
const panelAdmin = document.querySelector('#panel-admin');

const formMenu = document.querySelector('#form-menu');
const judulFormMenu = document.querySelector('#judul-form-menu');
const statusMenu = document.querySelector('#status-menu');
const tombolSimpan = document.querySelector('#btn-simpan-menu');
const tombolBatalEdit = document.querySelector('#btn-batal-edit');

const inputCari = document.querySelector('#cari-admin');
const filterKategori = document.querySelector('#filter-admin');
const infoDaftar = document.querySelector('#info-daftar');
const statusAksi = document.querySelector('#status-aksi');
const isiTabel = document.querySelector('#isi-tabel');
const paginasi = document.querySelector('#paginasi-admin');
const tombolSebelumnya = document.querySelector('#btn-sebelumnya');
const tombolBerikutnya = document.querySelector('#btn-berikutnya');
const infoHalaman = document.querySelector('#info-halaman');
const tombolTema = document.querySelector('#theme-button');

function setStatus(elemen, pesan, kelas = '') {
    elemen.textContent = pesan;
    elemen.className = kelas;
}

// ===== TEMA (memakai preferensi yang sama dengan situs publik) =====
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

terapkanTema(ambilPreferensi('a2-theme', 'light') === 'dark' ? 'dark' : 'light');

// ===== SESI LOGIN =====
// Belum login -> form login; admin -> panel kelola; login non-admin -> hanya tombol Keluar.
function terapkanSesi(user, csrf = null) {
    const sebelumnyaAdmin = isAdmin();
    sesi.user = user;
    sesi.csrf = user === null ? null : csrf;

    formLogin.hidden = user !== null;
    barSesi.hidden = user === null;
    panelAdmin.hidden = !isAdmin();

    if (user !== null) {
        infoSesi.textContent = `Masuk sebagai ${user.nama} (${user.role}).`;
    }
    if (user !== null && !isAdmin()) {
        setStatus(statusSesi, 'Akun ini tidak memiliki akses admin. Masuk dengan akun admin.', 'status-error');
    }

    if (isAdmin() && !sebelumnyaAdmin) {
        muatMenu();
    } else if (!isAdmin() && sebelumnyaAdmin) {
        resetFormMenu();
        isiTabel.replaceChildren();
        daftarHalaman = [];
    }
}

async function muatSesi() {
    try {
        const hasil = await panggilApi(`${ENDPOINT_AUTH}?aksi=me`);
        terapkanSesi(hasil.data?.user ?? null, hasil.data?.csrf_token ?? null);
    } catch (error) {
        console.error(error);
        terapkanSesi(null);
    }
}

// Dipakai saat API menjawab 401/403: sinkronkan ulang sesi dan beri tahu pengguna.
async function periksaAkses(error) {
    if (!(error instanceof ApiError) || (error.status !== 401 && error.status !== 403)) return false;
    resetFormMenu();
    await muatSesi();
    setStatus(
        statusSesi,
        error.status === 401 ? 'Sesi berakhir. Silakan login kembali.' : error.message,
        'status-error'
    );
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return true;
}

formLogin.addEventListener('submit', async (event) => {
    event.preventDefault();

    const data = new FormData(formLogin);
    const body = {
        email: String(data.get('email') ?? '').trim(),
        password: String(data.get('password') ?? '')
    };

    tombolLogin.disabled = true;
    setStatus(statusSesi, 'Memeriksa akun...', 'status-loading');

    try {
        const hasil = await panggilApi(`${ENDPOINT_AUTH}?aksi=login`, { method: 'POST', body });
        formLogin.reset();
        setStatus(statusSesi, '');
        const { csrf_token: csrf, ...user } = hasil.data;
        terapkanSesi(user, csrf ?? null);
    } catch (error) {
        if (!(error instanceof ApiError)) console.error(error);
        formLogin.elements.password.value = '';
        setStatus(
            statusSesi,
            error instanceof ApiError ? error.message : 'Login belum dapat diproses. Coba lagi.',
            'status-error'
        );
    } finally {
        tombolLogin.disabled = false;
    }
});

tombolLogout.addEventListener('click', async () => {
    try {
        await panggilApi(`${ENDPOINT_AUTH}?aksi=logout`, { method: 'POST', csrf: sesi.csrf });
        setStatus(statusMenu, '');
        setStatus(statusAksi, '');
        terapkanSesi(null);
        setStatus(statusSesi, 'Anda sudah keluar.', 'status-ok');
    } catch (error) {
        console.error(error);
        setStatus(statusSesi, 'Logout gagal. Coba lagi.', 'status-error');
    }
});

// ===== DAFTAR MENU (tabel + cari + filter + pagination) =====
function buatTombolAksi(aksi, teks, label, kelas) {
    const tombol = document.createElement('button');
    tombol.type = 'button';
    tombol.className = kelas;
    tombol.dataset.aksi = aksi;
    tombol.textContent = teks;
    tombol.setAttribute('aria-label', label);
    return tombol;
}

function buatBaris(item) {
    const tr = document.createElement('tr');
    tr.dataset.id = item.id;

    const sel = (teks, kelas = '') => {
        const td = document.createElement('td');
        td.textContent = teks;
        if (kelas) td.className = kelas;
        return td;
    };

    const badge = document.createElement('small');
    badge.className = item.status === 'Tersedia' ? 'badge badge-ok' : 'badge badge-habis';
    badge.textContent = item.status;
    const tdStatus = document.createElement('td');
    tdStatus.append(badge);

    const wadah = document.createElement('div');
    wadah.className = 'aksi-tabel';
    wadah.append(
        buatTombolAksi('edit', 'Edit', `Edit ${item.nama}`, 'btn-detail'),
        buatTombolAksi('hapus', 'Hapus', `Hapus ${item.nama}`, 'btn-detail btn-hapus')
    );
    const tdAksi = document.createElement('td');
    tdAksi.append(wadah);

    tr.append(
        sel(item.nama), sel(item.kategori),
        sel(formatRupiah(item.harga), 'angka'), sel(String(item.stok), 'angka'),
        tdStatus, tdAksi
    );
    return tr;
}

function renderTabel(items, meta) {
    isiTabel.replaceChildren();

    if (items.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.textContent = 'Menu tidak ditemukan.';
        tr.append(td);
        isiTabel.append(tr);
    } else {
        isiTabel.append(...items.map(buatBaris));
    }

    const total = meta?.total ?? items.length;
    infoDaftar.textContent = items.length === 0
        ? 'Tidak ada menu yang cocok.'
        : `Menampilkan ${items.length} dari ${total} menu.`;

    const totalHalaman = meta?.total_pages ?? 1;
    paginasi.hidden = totalHalaman <= 1;
    infoHalaman.textContent = `Halaman ${state.page} dari ${totalHalaman}`;
    tombolSebelumnya.disabled = state.page <= 1;
    tombolBerikutnya.disabled = state.page >= totalHalaman;
}

async function muatMenu() {
    const nomor = ++permintaanTerakhir;   // abaikan respons usang jika ada request lebih baru
    infoDaftar.textContent = 'Memuat menu...';

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

        // Halaman sudah kosong (mis. item terakhir dihapus) -> mundur ke halaman terakhir yang valid
        if (hasil.data.length === 0 && state.page > 1) {
            state.page = hasil.meta.total_pages;
            return muatMenu();
        }

        daftarHalaman = hasil.data;
        renderTabel(daftarHalaman, hasil.meta);
    } catch (error) {
        if (nomor !== permintaanTerakhir) return;
        console.error(error);
        daftarHalaman = [];
        renderTabel([], null);
        infoDaftar.textContent = 'Menu belum dapat dimuat. Coba muat ulang halaman.';
    }
}

function terapkanFilter() {
    state.page = 1;
    muatMenu();
}

let timerCari = null;
inputCari.addEventListener('input', () => {
    state.kataKunci = inputCari.value;
    clearTimeout(timerCari);
    timerCari = setTimeout(terapkanFilter, 300);   // debounce agar tidak memanggil API tiap ketikan
});

filterKategori.addEventListener('change', () => {
    state.kategori = filterKategori.value;
    terapkanFilter();
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

// ===== FORM TAMBAH / UBAH =====
function bersihkanErrorMenu() {
    formMenu.querySelectorAll('.error').forEach(el => (el.textContent = ''));
    formMenu.querySelectorAll('[aria-invalid="true"]')
        .forEach(el => el.removeAttribute('aria-invalid'));
}

function tampilkanErrorField(errors) {
    let pertama = null;
    for (const field of FIELD_MENU) {
        if (!(field in errors)) continue;
        document.querySelector(`#error-menu-${field}`).textContent = errors[field];
        formMenu.elements[field].setAttribute('aria-invalid', 'true');
        pertama ??= formMenu.elements[field];
    }
    pertama?.focus();
}

function resetFormMenu() {
    idDiedit = null;
    formMenu.reset();
    bersihkanErrorMenu();
    judulFormMenu.textContent = 'Tambah Menu Baru';
    tombolSimpan.textContent = 'Simpan Menu';
    tombolBatalEdit.hidden = true;
}

function mulaiEdit(item) {
    resetFormMenu();
    idDiedit = item.id;
    for (const field of FIELD_MENU) {
        formMenu.elements[field].value = item[field] ?? '';
    }
    judulFormMenu.textContent = `Edit Menu: ${item.nama}`;
    tombolSimpan.textContent = 'Simpan Perubahan';
    tombolBatalEdit.hidden = false;
    setStatus(statusMenu, '');
    document.querySelector('#kelola').scrollIntoView({ behavior: 'smooth' });
    formMenu.elements.nama.focus({ preventScroll: true });
}

tombolBatalEdit.addEventListener('click', () => {
    resetFormMenu();
    setStatus(statusMenu, 'Edit dibatalkan.', 'status-loading');
});

formMenu.addEventListener('input', (event) => {
    const field = event.target;
    const errorEl = document.querySelector(`#error-menu-${field.name}`);
    if (errorEl) errorEl.textContent = '';
    field.removeAttribute('aria-invalid');
});

formMenu.addEventListener('submit', async (event) => {
    event.preventDefault();
    bersihkanErrorMenu();

    const data = new FormData(formMenu);
    const body = Object.fromEntries(FIELD_MENU.map(f => [f, String(data.get(f) ?? '').trim()]));
    const mengedit = idDiedit !== null;

    tombolSimpan.disabled = true;
    setStatus(statusMenu, mengedit ? 'Menyimpan perubahan...' : 'Menyimpan menu...', 'status-loading');

    try {
        const url = mengedit ? `${ENDPOINT_MENU}?id=${encodeURIComponent(idDiedit)}` : ENDPOINT_MENU;
        const hasil = await panggilApi(url, { method: mengedit ? 'PUT' : 'POST', body, csrf: sesi.csrf });

        resetFormMenu();
        setStatus(statusMenu, hasil.message, 'status-ok');
        await muatMenu();
    } catch (error) {
        if (await periksaAkses(error)) return;
        if (!(error instanceof ApiError)) {
            console.error(error);
            setStatus(statusMenu, 'Terjadi kesalahan. Coba lagi.', 'status-error');
            return;
        }
        setStatus(statusMenu, error.message, 'status-error');
        tampilkanErrorField(error.errors);
        if (error.status === 404) {   // menu sudah tidak ada (mis. dihapus dari tab lain)
            resetFormMenu();
            setStatus(statusMenu, error.message, 'status-error');
            await muatMenu();
        }
    } finally {
        tombolSimpan.disabled = false;
    }
});

// ===== HAPUS =====
async function hapusMenu(item) {
    if (!confirm(`Hapus menu "${item.nama}"?`)) return;

    setStatus(statusAksi, 'Menghapus menu...', 'status-loading');
    try {
        await panggilApi(`${ENDPOINT_MENU}?id=${encodeURIComponent(item.id)}`, { method: 'DELETE', csrf: sesi.csrf });
        if (idDiedit === item.id) resetFormMenu();
        setStatus(statusAksi, `Menu "${item.nama}" berhasil dihapus.`, 'status-ok');
        await muatMenu();
    } catch (error) {
        if (await periksaAkses(error)) {
            setStatus(statusAksi, error.message, 'status-error');
            return;
        }
        if (!(error instanceof ApiError)) console.error(error);
        setStatus(
            statusAksi,
            error instanceof ApiError ? error.message : 'Menu belum dapat dihapus. Coba lagi.',
            'status-error'
        );
        if (error instanceof ApiError && error.status === 404) await muatMenu();
    }
}

isiTabel.addEventListener('click', (event) => {
    const tombol = event.target.closest('[data-aksi]');
    if (!tombol) return;

    const id = Number(tombol.closest('tr').dataset.id);
    const item = daftarHalaman.find(m => m.id === id);
    if (!item) return;

    if (tombol.dataset.aksi === 'edit') mulaiEdit(item);
    if (tombol.dataset.aksi === 'hapus') hapusMenu(item);
});

muatSesi();