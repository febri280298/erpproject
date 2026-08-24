/**
 * Menguji menu sidebar yang tersorot dan grup yang terbuka.
 *
 * Semua dashboard modul berbagi awalan rute yang sama (dashboard.master,
 * dashboard.sales, dan seterusnya), begitu pula Produk/Upload Produk dan
 * Stok/Kartu Stok. Pencocokan berbasis awalan karena itu menyalakan beberapa
 * menu sekaligus — sidebar terbuka lebar dan pengguna kehilangan petunjuk
 * sedang berada di mana.
 *
 * Yang dituntut: tepat SATU menu tersorot, dan hanya grup yang memuatnya yang
 * terbuka. Halaman anak (buat, ubah, detail) tetap menyorot menu daftarnya.
 *
 * Jalankan: node tests/e2e/sidebar-aktif.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

// [jalur, menu yang seharusnya tersorot, grup yang seharusnya terbuka]
const KASUS = [
    ['/dashboard', 'Dashboard', null],
    ['/dashboard/master', 'Ringkasan Master', 'Data Master'],
    ['/dashboard/purchasing', 'Ringkasan Pembelian', 'Pembelian'],
    ['/dashboard/sales', 'Ringkasan Penjualan', 'Penjualan'],
    ['/dashboard/inventory', 'Ringkasan Stok', 'Stok & Gudang'],
    ['/dashboard/accounting', 'Ringkasan Akuntansi', 'Akuntansi'],

    // Menu yang berbagi awalan dengan tetangganya.
    ['/products', 'Produk / Barang', 'Data Master'],
    ['/products/import', 'Upload Produk', 'Data Master'],
    ['/stocks', 'Stok Barang', 'Stok & Gudang'],
    ['/stocks/card', 'Kartu Stok', 'Stok & Gudang'],

    // Halaman anak: menu daftarnya yang tetap tersorot.
    ['/products/create', 'Produk / Barang', 'Data Master'],
    ['/purchase-orders/create', 'Pesanan Pembelian (PO)', 'Pembelian'],
    ['/quotations/create', 'Penawaran (Quotation)', 'Penjualan'],
];

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1500, height: 1000 } });
const page = await ctx.newPage();

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(46)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

for (const [jalur, menuAktif, grupTerbuka] of KASUS) {
    const res = await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' }).catch(() => null);

    if (!res || res.status() >= 400) {
        console.log(`  ${jalur.padEnd(46)} dilewati — status ${res?.status() ?? 'gagal'}`);
        continue;
    }

    await page.waitForTimeout(300);

    const d = await page.evaluate(() => {
        const sidebar = document.querySelector('aside.navbar-vertical');

        return {
            // Butir menu yang tersorot, baik di tingkat atas maupun dalam grup.
            tersorot: [
                ...sidebar.querySelectorAll('.nav-item:not(.dropdown).active .nav-link-title'),
                ...sidebar.querySelectorAll('.dropdown-item.active'),
            ].map((el) => {
                /*
                 * Label diambil dari pembungkus .flex-fill, bukan dari seluruh
                 * isi tautannya: butir beralur memuat badge nomor langkah di
                 * depan, sehingga membaca seluruh isi menghasilkan "1" atau "0"
                 * alih-alih nama menunya.
                 */
                const pembungkus = el.querySelector('.flex-fill') ?? el;

                return pembungkus.textContent.trim().split('\n')[0].trim();
            }),

            grupTerbuka: [...sidebar.querySelectorAll('.nav-item.dropdown.active .nav-link-title')]
                .map((el) => el.textContent.trim()),
        };
    });

    const satuMenu = d.tersorot.length === 1 && d.tersorot[0] === menuAktif;
    const grupBenar = grupTerbuka === null
        ? d.grupTerbuka.length === 0
        : d.grupTerbuka.length === 1 && d.grupTerbuka[0] === grupTerbuka;

    lapor(jalur, satuMenu && grupBenar,
        `tersorot [${d.tersorot.join(', ') || '-'}]  grup [${d.grupTerbuka.join(', ') || '-'}]`);
}

await browser.close();
console.log(gagal === 0 ? '\nSemua halaman menyorot tepat satu menu.' : `\n${gagal} halaman salah sorot.`);
process.exit(gagal === 0 ? 0 : 1);
