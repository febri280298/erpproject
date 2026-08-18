/**
 * Memastikan menu "Aksi" di dalam tabel tidak terpotong pembungkus .table-responsive.
 *
 * Yang diuji baris TERAKHIR tiap tabel, karena di situlah menu paling mungkin
 * melewati tepi bawah pembungkus. Tabel yang masih kosong tetap diuji dengan
 * menyisipkan satu baris tiruan berisi dropdown — sekaligus membuktikan baris
 * yang muncul belakangan (bukan saat halaman dimuat) ikut tertangani.
 *
 * Kelolosan diukur lewat hit-test, bukan perbandingan koordinat: menu yang sudah
 * benar memakai position:fixed dan memang sengaja keluar dari kotak pembungkus.
 *
 * Jalankan: node tests/e2e/dropdown-tabel.mjs   (HEADED=1 untuk melihat prosesnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

// Semua halaman yang punya dropdown di dalam .table-responsive.
const HALAMAN = [
    ['Master · Customer & Supplier', '/partners'],
    ['Master · Produk', '/products'],
    ['Master · Kategori Produk', '/categories'],
    ['Master · Satuan (UOM)', '/uoms'],
    ['Master · Pajak', '/taxes'],
    ['Master · Tingkat Harga', '/price-levels'],
    ['Master · Termin Pembayaran', '/payment-terms'],
    ['Master · Gudang', '/warehouses'],
    ['Pembelian · Permintaan', '/purchase-requisitions'],
    ['Pembelian · Purchase Order', '/purchase-orders'],
    ['Pembelian · Penerimaan', '/goods-receipts'],
    ['Pembelian · Faktur', '/purchase-invoices'],
    ['Penjualan · Penawaran', '/quotations'],
    ['Penjualan · Sales Order', '/sales-orders'],
    ['Penjualan · Surat Jalan', '/delivery-orders'],
    ['Penjualan · Faktur', '/sales-invoices'],
    ['Stok · Penyesuaian', '/stock-adjustments'],
    ['Stok · Transfer', '/stock-transfers'],
    ['Akuntansi · Akun (COA)', '/accounts'],
    ['Produksi · BOM', '/boms'],
    ['Produksi · Perintah Produksi', '/production-orders'],
    ['SDM · Karyawan', '/employees'],
    ['SDM · Cuti', '/leaves'],
    ['SDM · Penggajian', '/payrolls'],
    ['Sistem · Pengguna', '/users'],
    ['Sistem · Peran', '/roles'],
];

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1500, height: 900 } });
const page = await ctx.newPage();

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');

/**
 * Menguji apakah menu benar-benar TERLIHAT, bukan sekadar posisinya di mana.
 *
 * Membandingkan koordinat menu dengan kotak pembungkus tidak sahih: menu yang
 * sudah diperbaiki memakai position:fixed memang sengaja melewati kotak itu.
 * Yang menentukan adalah hit-test — document.elementFromPoint menghormati
 * kliping overflow, jadi kalau titik di dalam menu mengembalikan menu itu
 * sendiri, berarti piksel di situ betul-betul tampak di layar.
 */
const ukurMenu = () => page.evaluate(() => {
    const menu = document.querySelector('.table-responsive .dropdown-menu.show, table .dropdown-menu.show');

    if (!menu) {
        return { error: 'menu tidak terbuka' };
    }

    const m = menu.getBoundingClientRect();
    const item = [...menu.querySelectorAll('.dropdown-item')];

    if (item.length === 0) {
        return { error: 'menu tanpa isi' };
    }

    // Tiap butir menu diperiksa satu per satu: yang sering hilang adalah butir
    // terakhir, karena ia paling bawah dan paling dekat tepi pembungkus.
    const tertutup = [];

    for (const it of item) {
        const r = it.getBoundingClientRect();
        const x = Math.round(r.left + r.width / 2);
        const y = Math.round(r.top + r.height / 2);

        if (y < 0 || y > window.innerHeight || x < 0 || x > window.innerWidth) {
            tertutup.push(`${it.textContent.trim()} (di luar layar)`);
            continue;
        }

        const kena = document.elementFromPoint(x, y);

        if (!kena || !menu.contains(kena)) {
            tertutup.push(`${it.textContent.trim()} (tertutup ${kena?.tagName.toLowerCase() ?? 'kosong'}${kena?.className ? '.' + [...kena.classList].join('.') : ''})`);
        }
    }

    return {
        posisi: getComputedStyle(menu).position,
        jumlahItem: item.length,
        tinggi: Math.round(m.height),
        tertutup,
    };
});

let gagal = 0;
let diuji = 0;
let kosong = 0;

for (const [nama, jalur] of HALAMAN) {
    const res = await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' }).catch(() => null);

    if (!res || res.status() >= 400) {
        console.log(`  ${nama.padEnd(32)} — halaman tidak tersedia (${res?.status() ?? 'gagal'})`);
        continue;
    }

    await page.waitForTimeout(350);

    const tombol = page.locator('.page-body table tbody [data-bs-toggle="dropdown"]');
    const jumlah = await tombol.count();

    let disisipkan = false;

    if (jumlah === 0) {
        // Tabel kosong disisipi satu baris tiruan yang meniru markup baris asli,
        // supaya halaman tetap teruji dan jalur "baris ditambah belakangan"
        // ikut terbukti bekerja.
        disisipkan = await page.evaluate(() => {
            // Halaman yang datanya masih kosong tidak merender <table> sama
            // sekali, jadi seluruh struktur pembungkusnya yang disisipkan —
            // .table-responsive persis seperti di Blade, karena justru
            // overflow milik pembungkus itulah yang dulu memotong menu.
            const kartu = [...document.querySelectorAll('.page-body .card')].pop();

            if (!kartu) {
                return false;
            }

            const bungkus = document.createElement('div');
            bungkus.className = 'table-responsive';
            bungkus.innerHTML = `
                <table class="table table-vcenter card-table">
                    <tbody>
                        <tr>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle"
                                            data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#">Detail</a>
                                        <a class="dropdown-item" href="#">Ubah</a>
                                        <a class="dropdown-item" href="#">Hapus</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>`;
            kartu.append(bungkus);

            return true;
        });

        if (!disisipkan) {
            kosong++;
            console.log(`  ${nama.padEnd(32)} — tidak ada tabel di halaman ini`);
            continue;
        }
    }

    // Baris terakhir: posisi paling rawan terpotong tepi bawah pembungkus.
    const sasaran = page.locator('.page-body .table-responsive [data-bs-toggle="dropdown"]').last();
    await sasaran.scrollIntoViewIfNeeded();
    await sasaran.click();
    await page.waitForTimeout(350);

    const d = await ukurMenu();
    diuji++;

    const lolos = !d.error && d.tertutup.length === 0;
    lolos || gagal++;

    const catatan = d.error
        ? d.error
        : d.tertutup.length > 0
            ? `${d.tertutup.length}/${d.jumlahItem} butir tidak terlihat → ${d.tertutup.join('; ')}`
            : `${d.jumlahItem} butir terlihat semua, posisi=${d.posisi}, tinggi=${d.tinggi}px`;

    console.log(`  ${nama.padEnd(32)} ${lolos ? 'LOLOS' : 'GAGAL'}  ${catatan}${disisipkan ? '  [baris tiruan]' : ''}`);

    if (!lolos) {
        await page.screenshot({ path: `tests/e2e/hasil/dropdown-gagal-${jalur.replace(/\//g, '-')}.png` });
    }
}

console.log(`\n${diuji} halaman diuji, ${kosong} dilewati karena tabelnya kosong, ${gagal} gagal.`);
await browser.close();
process.exit(gagal === 0 ? 0 : 1);
