/**
 * Mengisi kolom Inisial dari nama mitra sambil diketik.
 *
 * "PT Ravalia Inti Mandiri" menjadi RIM: bentuk badan usaha dilewati, lalu
 * diambil huruf pertama tiap kata sisanya. Isian tetap bisa diubah manual —
 * begitu pengguna menyunting sendiri, pengisian otomatis berhenti agar
 * ketikannya tidak ditimpa saat nama diperbaiki.
 */

// Bentuk badan usaha bukan bagian dari identitas mitra, jadi tidak ikut
// disingkat — kalau ikut, semua PT akan berawalan P.
const BENTUK_USAHA = new Set([
    'PT', 'CV', 'UD', 'PD', 'NV', 'FA', 'KOPERASI', 'KOP', 'TOKO', 'TB',
    'PERUM', 'PERSERO', 'TBK', 'YAYASAN',
]);

// Kata sambung tidak membawa arti pada singkatan.
const KATA_SAMBUNG = new Set(['DAN', 'DI', 'KE', 'DARI', 'THE', 'AND']);

const PANJANG_MAKSIMAL = 5;

export function inisialDari(nama) {
    const kata = String(nama || '')
        .toUpperCase()
        .replace(/[^A-Z0-9\s]/g, ' ')
        .split(/\s+/)
        .filter(Boolean)
        .filter((k) => ! BENTUK_USAHA.has(k) && ! KATA_SAMBUNG.has(k));

    if (kata.length === 0) {
        return '';
    }

    // Nama satu kata tidak punya huruf awal untuk dirangkai, jadi diambil tiga
    // huruf pertamanya: "Ravalia" menjadi RAV.
    if (kata.length === 1) {
        return kata[0].slice(0, 3);
    }

    return kata.map((k) => k[0]).join('').slice(0, PANJANG_MAKSIMAL);
}

export default function pasangInisialMitra() {
    document.addEventListener('DOMContentLoaded', () => {
        const sumber = document.querySelector('[data-sumber-inisial]');
        const tujuan = document.querySelector('input[name="initial"]');

        if (! sumber || ! tujuan) {
            return;
        }

        // Mitra yang sudah punya inisial tidak diganggu — mengubahnya diam-diam
        // saat nama disunting berarti menimpa keputusan yang sudah dibuat.
        let otomatis = tujuan.value.trim() === '';

        tujuan.addEventListener('input', () => {
            tujuan.value = tujuan.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

            // Mengosongkan kolomnya berarti minta saran otomatis kembali; kalau
            // tidak, sekali disentuh isian ini mati selamanya dan pengguna harus
            // memuat ulang halaman untuk mendapatkan sarannya lagi.
            otomatis = tujuan.value === '';
        });

        sumber.addEventListener('input', () => {
            if (otomatis) {
                tujuan.value = inisialDari(sumber.value);
            }
        });
    });
}
