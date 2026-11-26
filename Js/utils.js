export function ringkasMenu(data) {
    if (!Array.isArray(data)) {
        throw new TypeError('Data menu harus berupa array');
    }

    return {
        jumlahMenu: data.length,
        totalStok: data.reduce((total, item) => total + item.stok, 0),
        menuHabis: data.filter(item => item.status !== 'Tersedia').length,
        totalNilaiStok: data.reduce((total, item) => total + item.harga * item.stok, 0)
    };
}

export function cariMenuById(data, id) {
    if (!Array.isArray(data)) {
        throw new TypeError('Data menu harus berupa array');
    }

    const menu = data.find(item => item.id === id);

    if (!menu) {
        throw new Error(`Menu dengan id ${id} tidak ditemukan`);
    }

    return menu;
}

export function filterByKategori(data, kategori) {
    if (!Array.isArray(data)) {
        throw new TypeError('Data menu harus berupa array');
    }

    return data.filter(item => item.kategori.toLowerCase() === kategori.toLowerCase());
}

export function formatRupiah(angka) {
    if (typeof angka !== 'number' || Number.isNaN(angka)) {
        throw new TypeError('Harga harus berupa angka');
    }

    return `Rp${angka.toLocaleString('id-ID')}`;
}

export function cariMenuByNama(data, kataKunci) {
    if (!Array.isArray(data)) {
        throw new TypeError('Data menu harus berupa array');
    }

    const kunci = String(kataKunci).trim().toLowerCase();
    return data.filter(item => item.nama.toLowerCase().includes(kunci));
}

export function simpanPreferensi(kunci, nilai) {
    try {
        localStorage.setItem(kunci, nilai);
    } catch (error) {
        console.warn('Preferensi tidak dapat disimpan:', error.message);
    }
}

export function ambilPreferensi(kunci, bawaan) {
    try {
        return localStorage.getItem(kunci) ?? bawaan;
    } catch (error) {
        return bawaan;
    }
}

export async function ambilJson(url) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

export function terjemahkanKodeCuaca(kode) {
    if (kode >= 95) return 'Badai petir';
    if (kode >= 80) return 'Hujan deras sesaat';
    if (kode >= 61) return 'Hujan';
    if (kode >= 51) return 'Gerimis';
    if (kode >= 45) return 'Berkabut';
    if (kode >= 1) return 'Berawan';
    return 'Cerah';
}

export function rekomendasiMenu(suhu, kode) {
    if (kode >= 51) {
        return 'Cuaca basah, cocok dinikmati dengan A2 Coffee dan Roti Bakar hangat.';
    }
    if (suhu >= 31) {
        return 'Cuaca panas, cocok dengan Es Teh Manis atau A2 Coffee dingin.';
    }
    return 'Cuaca nyaman, semua menu cocok dinikmati.';
}

export class ApiError extends Error {
    constructor(message, status = 0, errors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

// csrf: token dari server (login / aksi=me); wajib untuk request yang mengubah data (Modul 13).
export async function panggilApi(url, { method = 'GET', body, csrf } = {}) {
    const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (csrf) {
        options.headers['X-CSRF-Token'] = csrf;
    }
    if (body !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch {
        throw new ApiError('Tidak dapat terhubung ke server.');
    }

    if (response.status === 204) return null;

    const payload = await response.json().catch(() => null);
    if (!response.ok) {
        throw new ApiError(
            payload?.message ?? `HTTP ${response.status}`,
            response.status,
            payload?.errors ?? {}
        );
    }
    return payload;
}