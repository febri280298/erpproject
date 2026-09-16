/**
 * Memilih kolom mana yang tampil di setiap tabel daftar.
 *
 * Tabel di sistem ini lebar-lebar: daftar produk punya sepuluh kolom, faktur
 * sembilan. Tidak semua orang memerlukan semuanya — bagian gudang jarang
 * melihat harga, bagian penjualan jarang melihat stok — dan kolom yang tidak
 * dipakai memaksa tabelnya digulir mendatar untuk mencapai yang dipakai.
 *
 * Dipasang sendiri ke setiap tabel daftar, bukan dipanggil satu per satu dari
 * Blade. Ada 31 halaman daftar; menambahkan komponen di masing-masing berarti
 * 31 suntingan yang nyaris sama, dan halaman baru akan terlewat. Pola ini sama
 * dengan select-search.js yang juga memasang dirinya ke seluruh <select>.
 *
 * Yang perlu diperhatikan:
 *
 * - Kolom disembunyikan lewat posisinya, bukan lewat penanda di tiap sel. Itu
 *   sebabnya baris ber-colspan (baris "belum ada data") dilewati: jumlah selnya
 *   tidak sama dengan jumlah kolom, dan menyembunyikan sel ke-n di situ akan
 *   menggeser isinya.
 *
 * - Kolom tanpa judul tidak ikut ditawarkan. Itu kolom tombol Aksi, dan tabel
 *   tanpa tombol aksi tidak ada gunanya.
 *
 * - Pilihannya disimpan di peramban masing-masing, bukan di basis data. Ini
 *   kenyamanan perorangan, bukan pengaturan perusahaan — dan menyimpannya di
 *   server berarti satu tabel pengaturan baru untuk sesuatu yang tidak pernah
 *   perlu dibagikan.
 *
 * Tabel yang tidak ingin dipasangi cukup diberi atribut data-no-kolom.
 */

const KUNCI = 'erp-kolom';
const TEKS = {
    tombol: 'Kolom',
    semua: 'Tampilkan semua',
};

/** Judul kolom; kosong berarti kolom tombol aksi. */
const judulKolom = (th) => th.textContent.trim();

/**
 * Kunci penyimpanan per tabel.
 *
 * Memakai jalur halaman, bukan seluruh URL: daftar yang sama dengan penyaring
 * berbeda (?status=draft) tetap satu tabel yang sama bagi pemakainya, dan
 * pilihannya tidak masuk akal ikut berganti-ganti.
 */
const kunciTabel = (indeks) => `${KUNCI}:${window.location.pathname}:${indeks}`;

const bacaTersembunyi = (kunci) => {
    try {
        const isi = JSON.parse(window.localStorage.getItem(kunci) || '[]');

        return Array.isArray(isi) ? isi.map(Number) : [];
    } catch {
        return [];
    }
};

const simpanTersembunyi = (kunci, daftar) => {
    try {
        daftar.length
            ? window.localStorage.setItem(kunci, JSON.stringify(daftar))
            : window.localStorage.removeItem(kunci);
    } catch {
        // Peramban dengan penyimpanan dimatikan tetap boleh memakai tabelnya;
        // yang hilang hanya ingatan pilihannya antar kunjungan.
    }
};

/**
 * Menyembunyikan atau menampilkan kolom ke-i pada seluruh baris.
 *
 * Baris yang jumlah selnya tidak sama dengan jumlah kolom dilewati — lihat
 * catatan colspan di atas.
 */
const terapkan = (tabel, jumlahKolom, tersembunyi) => {
    const baris = [
        ...tabel.querySelectorAll(':scope > thead > tr'),
        ...tabel.querySelectorAll(':scope > tbody > tr'),
        ...tabel.querySelectorAll(':scope > tfoot > tr'),
    ];

    baris.forEach((tr) => {
        const sel = tr.children;

        if (sel.length !== jumlahKolom) {
            return;
        }

        [...sel].forEach((td, i) => {
            td.hidden = tersembunyi.includes(i);
        });
    });
};

/**
 * Tempat tombolnya diletakkan, diurutkan dari yang paling wajar.
 *
 * Halaman daftar di sistem ini hampir semuanya memakai <x-card flush> tanpa
 * judul, jadi .card-header tidak pernah dirender dan tidak bisa diandalkan.
 * Yang hampir selalu ada justru baris penyaring — dan di situ pula orang
 * mencari ketika ingin mengubah tampilan daftarnya.
 */
const tempatTombol = (tabel) => {
    const kartu = tabel.closest('.card');

    if (! kartu) {
        return null;
    }

    // 1. Kepala kartu, bila kartunya memang berjudul.
    const kepala = kartu.querySelector(':scope > .card-header');

    if (kepala) {
        let aksi = kepala.querySelector(':scope > .card-actions');

        if (! aksi) {
            aksi = document.createElement('div');
            aksi.className = 'card-actions';
            kepala.append(aksi);
        }

        return aksi;
    }

    // 2. Ujung kanan baris penyaring.
    const penyaring = kartu.querySelector('.filter-bar');

    if (penyaring) {
        const kolom = document.createElement('div');
        kolom.className = 'col-auto ms-auto';
        penyaring.append(kolom);

        return kolom;
    }

    // 3. Tidak ada keduanya: bilah tipis tersendiri tepat di atas tabelnya.
    const pembungkus = tabel.closest('.table-responsive') ?? tabel;
    const bilah = document.createElement('div');
    bilah.className = 'card-body border-bottom py-2 text-end';
    pembungkus.before(bilah);

    return bilah;
};

const buatMenu = (kolom, tersembunyi, onUbah) => {
    const bungkus = document.createElement('div');
    bungkus.className = 'dropdown';

    const tombol = document.createElement('button');
    tombol.type = 'button';
    tombol.className = 'btn btn-sm dropdown-toggle';
    tombol.setAttribute('data-bs-toggle', 'dropdown');
    tombol.setAttribute('aria-label', 'Pilih kolom yang ditampilkan');
    tombol.innerHTML = `<i class="ti ti-columns me-1"></i>${TEKS.tombol}`;

    const menu = document.createElement('div');
    menu.className = 'dropdown-menu dropdown-menu-end';

    // Mengeklik isian tidak boleh menutup menunya: orang biasanya mengubah
    // beberapa kolom sekaligus, dan menu yang tertutup tiap klik memaksa
    // membukanya berulang kali.
    menu.addEventListener('click', (e) => e.stopPropagation());

    const kotak = kolom.map(({ indeks, judul }) => {
        const label = document.createElement('label');
        label.className = 'dropdown-item d-flex align-items-center gap-2 mb-0';

        const centang = document.createElement('input');
        centang.type = 'checkbox';
        centang.className = 'form-check-input m-0';
        centang.checked = ! tersembunyi.includes(indeks);
        centang.addEventListener('change', () => onUbah(indeks, centang.checked));

        label.append(centang, document.createTextNode(judul));
        menu.append(label);

        return { indeks, centang };
    });

    const pemisah = document.createElement('div');
    pemisah.className = 'dropdown-divider';

    const semua = document.createElement('button');
    semua.type = 'button';
    semua.className = 'dropdown-item';
    semua.textContent = TEKS.semua;
    semua.addEventListener('click', () => {
        kotak.forEach(({ indeks, centang }) => {
            if (! centang.checked) {
                centang.checked = true;
                onUbah(indeks, true);
            }
        });
    });

    menu.append(pemisah, semua);
    bungkus.append(tombol, menu);

    return bungkus;
};

const pasangTabel = (tabel, indeks) => {
    if (tabel.dataset.kolomSiap === '1' || tabel.closest('[data-no-kolom]') || tabel.hasAttribute('data-no-kolom')) {
        return;
    }

    const kepala = tabel.querySelector(':scope > thead > tr');
    const th = kepala ? [...kepala.children] : [];

    // Kolom berjudul harus lebih dari satu; menyembunyikan satu-satunya kolom
    // hanya menyisakan tabel kosong.
    const kolom = th
        .map((el, i) => ({ indeks: i, judul: judulKolom(el) }))
        .filter(({ judul }) => judul !== '');

    if (kolom.length < 2) {
        return;
    }

    const wadah = tempatTombol(tabel);

    if (! wadah) {
        return;
    }

    const kunci = kunciTabel(indeks);
    const tersembunyi = bacaTersembunyi(kunci).filter((i) => kolom.some((k) => k.indeks === i));

    terapkan(tabel, th.length, tersembunyi);

    wadah.prepend(buatMenu(kolom, tersembunyi, (i, tampil) => {
        const posisi = tersembunyi.indexOf(i);

        tampil ? (posisi > -1 && tersembunyi.splice(posisi, 1)) : (posisi === -1 && tersembunyi.push(i));

        terapkan(tabel, th.length, tersembunyi);
        simpanTersembunyi(kunci, tersembunyi);
    }));

    tabel.dataset.kolomSiap = '1';
};

export default function pasangPemilihKolom(akar = document) {
    akar.querySelectorAll?.('table.card-table').forEach(pasangTabel);
}
