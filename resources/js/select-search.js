import TomSelect from 'tom-select';

/**
 * Menjadikan setiap <select> bisa dicari dengan mengetik.
 *
 * Daftar produk, customer, dan akun COA tumbuh terus; memilihnya dengan
 * menggulir daftar panjang tidak praktis begitu isinya lewat beberapa puluh
 * baris. TomSelect dipakai karena Tabler sudah menyediakan temanya
 * (scss/vendor/_tom-select.scss), jadi tampilannya menyatu tanpa gaya tambahan.
 *
 * Yang perlu diperhatikan di aplikasi ini:
 *
 * - Menu harus digantung ke <body>. Banyak select berada di dalam tabel yang
 *   dibungkus .table-responsive ber-overflow:auto; tanpa ini menunya terpotong
 *   persis seperti dropdown "Aksi" dulu.
 * - Baris item dokumen ditambah oleh Alpine setelah halaman dimuat, jadi select
 *   baru harus ikut disiapkan — ditangani MutationObserver di bawah.
 * - Nilai tetap disimpan pada <select> aslinya, sehingga name, required, dan
 *   pengiriman form berjalan seperti biasa. TomSelect juga melepas event
 *   'change' pada elemen itu, jadi x-model Alpine tetap tersinkron.
 * - Semua select diperlakukan sama, termasuk yang pilihannya masih sedikit.
 *   Daftar customer dan produk tumbuh seiring pemakaian, dan kolom yang bisa
 *   diketik hari ini tapi tidak besok membuat orang ragu apakah fiturnya ada.
 *   Select yang memang tidak cocok dapat dikecualikan dengan atribut
 *   data-no-search pada elemennya atau pada pembungkusnya.
 */

const TEKS = {
    kosong: 'Tidak ada pilihan yang cocok',
    memuat: 'Memuat…',
};

const bolehDilewati = (select) => select.multiple
    || select.dataset.noSearch !== undefined
    || select.closest('[data-no-search]') !== null;

/**
 * Placeholder diambil dari <option value=""> bila ada, supaya teks yang sudah
 * ditulis di Blade ("— Pilih produk —", "Semua", dst.) tetap terpakai apa adanya.
 */
const placeholderDari = (select) => {
    const kosong = select.querySelector('option[value=""]');

    return kosong?.textContent.trim() || '— Pilih —';
};

export const siapkanSelect = (select) => {
    if (select.tomselect || bolehDilewati(select)) {
        return;
    }

    const ts = new TomSelect(select, {
        // Menu digantung ke body agar tidak terpotong pembungkus ber-overflow.
        dropdownParent: 'body',
        placeholder: placeholderDari(select),
        allowEmptyOption: true,
        maxOptions: null,
        // Label opsi sudah memuat SKU sekaligus nama ("KRT-004 — Acrylic MC
        // Clear …"), jadi mencari teksnya saja sudah mencakup keduanya.
        searchField: ['text'],
        render: {
            no_results: () => `<div class="no-results">${TEKS.kosong}</div>`,
            loading: () => `<div class="spinner"></div>${TEKS.memuat}`,
        },
    });

    // Opsi kosong tetap ada di daftar supaya pilihan bisa dikembalikan ke
    // "— Pilih —" atau "Semua". Yang dilepas hanya penampilannya sebagai isian
    // terpilih: bila ditampilkan, teks itu berdampingan dengan kotak ketik dan
    // kotaknya melar jadi dua baris begitu pengguna mulai mengetik.
    if (select.value === '') {
        ts.clear(true);
    }
};

export const siapkanSemuaSelect = (akar = document) => {
    akar.querySelectorAll?.('select').forEach(siapkanSelect);
};

export default function pasangSelectPencarian() {
    document.addEventListener('DOMContentLoaded', () => {
        siapkanSemuaSelect();

        // Baris item dokumen (dan isi modal) muncul setelah halaman dimuat.
        // Menyiapkannya lewat pengamat DOM lebih tahan banting daripada
        // menebak-nebak kapan Alpine selesai merender.
        new MutationObserver((daftar) => {
            for (const perubahan of daftar) {
                for (const simpul of perubahan.addedNodes) {
                    if (simpul.nodeType !== Node.ELEMENT_NODE) {
                        continue;
                    }

                    // Ditunda satu frame: saat simpulnya baru disisipkan,
                    // Alpine sering belum selesai merender <option> di dalamnya
                    // (x-for), dan select yang disiapkan terlalu dini akan
                    // kehilangan seluruh pilihannya.
                    requestAnimationFrame(() => {
                        if (simpul.tagName === 'SELECT') {
                            siapkanSelect(simpul);
                        } else {
                            siapkanSemuaSelect(simpul);
                        }
                    });
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    });
}
