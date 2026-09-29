// js/utils.js
// Kumpulan fungsi pengolahan data menu A2 Coffee and Food.
// Setiap fungsi kecil, punya satu tanggung jawab, dan divalidasi inputnya.

/**
 * Menghasilkan ringkasan statistik dari daftar menu.
 * @param {Array<Object>} data - array of objects menu
 * @returns {{jumlahMenu:number, totalStok:number, menuHabis:number, totalNilaiStok:number}}
 */
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

/**
 * Mencari satu menu berdasarkan id.
 * @param {Array<Object>} data
 * @param {number} id
 * @returns {Object} objek menu yang ditemukan
 */
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
/**
 * Mengambil semua menu pada satu kategori tertentu (mis. "Minuman"/"Cemilan").
 * @param {Array<Object>} data
 * @param {string} kategori
 * @returns {Array<Object>}
 */
export function filterByKategori(data, kategori) {
    if (!Array.isArray(data)) {
        throw new TypeError('Data menu harus berupa array');
    }

    return data.filter(item => item.kategori.toLowerCase() === kategori.toLowerCase());
}

/**
 * Memformat angka menjadi format Rupiah sederhana, mis. 12000 -> "Rp12.000".
 * @param {number} angka
 * @returns {string}
 */
export function formatRupiah(angka) {
    if (typeof angka !== 'number' || Number.isNaN(angka)) {
        throw new TypeError('Harga harus berupa angka');
    }

    return `Rp${angka.toLocaleString('id-ID')}`;
}
/**
 * Mencari menu berdasarkan potongan nama (tidak peka huruf besar/kecil).
 * @param {Array<Object>} data
 * @param {string} kataKunci
 * @returns {Array<Object>}
 */
export function cariMenuByNama(data, kataKunci) {
    if (!Array.isArray(data)) {
        throw new TypeError('Data menu harus berupa array');
    }

    const kunci = String(kataKunci).trim().toLowerCase();
    return data.filter(item => item.nama.toLowerCase().includes(kunci));
}

/**
 * Menyimpan preferensi NON-SENSITIF ke localStorage.
 */
export function simpanPreferensi(kunci, nilai) {
    try {
        localStorage.setItem(kunci, nilai);
    } catch (error) {
        console.warn('Preferensi tidak dapat disimpan:', error.message);
    }
}

/**
 * Mengambil preferensi dari localStorage, atau nilai bawaan jika tidak ada.
 */
export function ambilPreferensi(kunci, bawaan) {
    try {
        return localStorage.getItem(kunci) ?? bawaan;
    } catch (error) {
        return bawaan;
    }
}