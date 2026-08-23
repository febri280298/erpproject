/**
 * Menguji dashboard per modul.
 *
 * Yang diperiksa bukan sekadar halaman terbuka, melainkan:
 *  1. Tidak ada galat PHP maupun JavaScript — dashboard penuh kueri agregat,
 *     dan satu nama kolom salah hanya ketahuan saat halamannya dirender.
 *  2. Kartu KPI, alur kerja, dan tabelnya benar-benar tampil.
 *  3. Grafiknya benar-benar tergambar, bukan hanya wadah kosong.
 *  4. Tautannya hidup — dashboard penuh tautan mati lebih buruk daripada
 *     tidak ada dashboard.
 *
 * Jalankan: node tests/e2e/dashboard-modul.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const DASHBOARD = [
    ['Data Master', '/dashboard/master', ['Kelengkapan Data', 'Produk per Kategori', 'Produk Terbaru'], false],
    ['Pembelian', '/dashboard/purchasing', ['Alur Pembelian', 'Tren Pembelian', 'Supplier Teratas'], true],
    ['Penjualan', '/dashboard/sales', ['Alur Penjualan', 'Tren Penjualan', 'Customer Teratas'], true],
    ['Stok & Gudang', '/dashboard/inventory', ['Dokumen Stok Tertahan', 'Stok Menipis', 'Nilai per Gudang'], false],
    ['Akuntansi', '/dashboard/accounting', ['Pendapatan vs Beban', 'Posisi Keuangan', 'Jurnal Terbaru'], true],
];

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
const page = await ctx.newPage();

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`    ${nama.padEnd(34)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

for (const [nama, jalur, kartuWajib, adaGrafik] of DASHBOARD) {
    console.log(`\n  ${nama}  (${jalur})`);

    const galat = [];
    const pendengar = (e) => galat.push(e.message);
    page.on('pageerror', pendengar);

    const res = await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' }).catch(() => null);
    await page.waitForTimeout(700);

    lapor('Halaman terbuka', !!res && res.status() === 200, `status ${res?.status() ?? 'gagal'}`);

    if (!res || res.status() !== 200) {
        page.off('pageerror', pendengar);
        continue;
    }

    const isi = await page.locator('.page-body').innerText();

    // Laravel menampilkan galat PHP sebagai halaman 500; whoops di mode debug
    // tetap berstatus 500, jadi status 200 di atas sudah menutup kemungkinan itu.
    // Yang tersisa diperiksa di sini adalah galat sisi peramban.
    lapor('Tanpa galat JavaScript', galat.length === 0, galat.join(' | '));

    const hilang = kartuWajib.filter((k) => !isi.includes(k));
    lapor('Kartu utama tampil', hilang.length === 0,
        hilang.length ? `hilang: ${hilang.join(', ')}` : kartuWajib.join(' · '));

    const kpi = await page.locator('.stat-tile').count();
    lapor('Kartu KPI tampil', kpi >= 4, `${kpi} kartu`);

    if (adaGrafik) {
        // ApexCharts menggambar <svg>; wadah kosong berarti grafiknya gagal.
        const svg = await page.locator('.page-body .apexcharts-canvas svg').count();
        lapor('Grafik tergambar', svg > 0, `${svg} kanvas`);
    }

    // Semua tautan internal diperiksa satu per satu terhadap server.
    const tautan = await page.evaluate(() => [...new Set(
        [...document.querySelectorAll('.page-body a[href^="http"]')]
            .map((a) => a.href)
            .filter((h) => h.startsWith(location.origin) && !h.includes('#')),
    )]);

    const mati = [];

    for (const t of tautan) {
        const r = await page.request.get(t).catch(() => null);

        if (!r || r.status() >= 400) {
            mati.push(`${t.replace(PANGKAL, '')} → ${r?.status() ?? 'gagal'}`);
        }
    }

    lapor('Tautan hidup semua', mati.length === 0,
        mati.length ? mati.join('; ') : `${tautan.length} tautan diperiksa`);

    await page.screenshot({ path: `tests/e2e/hasil/dashboard-${jalur.split('/').pop()}.png`, fullPage: true });
    page.off('pageerror', pendengar);
}

await browser.close();
console.log(`\n${gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`}`);
process.exit(gagal === 0 ? 0 : 1);
