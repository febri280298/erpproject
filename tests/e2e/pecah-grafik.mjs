/**
 * Memastikan pustaka grafik (ApexCharts) hanya diunduh di halaman yang memakainya.
 *
 * ApexCharts ~241 KB gzip, jauh lebih besar daripada seluruh sisa bundel. Dulu ia
 * diimpor statis di app.js sehingga ikut terunduh di setiap halaman, termasuk
 * login yang tidak punya satu grafik pun. Sekarang impornya dinamis.
 *
 * Yang diuji bukan isi manifest, melainkan lalu lintas jaringan yang benar-benar
 * terjadi — sebab yang menentukan berat halaman adalah berkas yang sungguh
 * diminta browser, bukan yang tertulis di konfigurasi. Rendernya ikut diuji,
 * karena pemecahan bundel yang membuat grafik gagal tampil bukan penghematan.
 *
 * Jalankan: node tests/e2e/pecah-grafik.mjs   (HEADED=1 untuk melihat prosesnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1500, height: 900 } });
const page = await ctx.newPage();

const galat = [];
page.on('pageerror', (e) => galat.push(e.message));

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(48)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

// Setiap berkas .js yang diminta halaman, beserta ukurannya.
let skrip = [];
page.on('response', async (res) => {
    const url = res.url();
    if (!url.endsWith('.js') || !url.includes('/build/')) return;

    let bytes = 0;
    try {
        bytes = (await res.body()).length;
    } catch {
        /* respons tanpa body (mis. 304) diabaikan */
    }

    skrip.push({ url, bytes });
});

const kunjungi = async (jalur) => {
    skrip = [];
    await page.goto(`${PANGKAL}${jalur}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);

    return {
        adaApex: skrip.some((s) => s.url.includes('apexcharts')),
        totalKb: Math.round(skrip.reduce((n, s) => n + s.bytes, 0) / 1024),
    };
};

/* ------------------------------------------- 1. Halaman tanpa grafik */

const login = await kunjungi('/login');
lapor('Login tidak mengunduh ApexCharts', !login.adaApex, `${login.totalKb} KB JS`);

await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');

// Dashboard adalah tujuan redirect login, jadi ia sempat meminta ApexCharts.
// Tanpa menunggunya tuntas, respons itu mendarat saat halaman berikutnya sudah
// mulai dimuat dan salah terhitung sebagai unduhan halaman tersebut.
await page.waitForLoadState('networkidle');

for (const [nama, jalur] of [
    ['Daftar produk', '/products'],
    ['Daftar sales order', '/sales-orders'],
    ['Form penawaran', '/quotations/create'],
]) {
    const h = await kunjungi(jalur);
    lapor(`${nama} tidak mengunduh ApexCharts`, !h.adaApex, `${h.totalKb} KB JS`);
}

/* ------------------------------------------- 2. Dashboard: harus tetap utuh */

const dash = await kunjungi('/dashboard');
lapor('Dashboard mengunduh ApexCharts saat perlu', dash.adaApex, `${dash.totalKb} KB JS`);

// Grafik dianggap jadi hanya bila SVG-nya benar-benar punya isi.
await page.waitForSelector('#trend-chart svg', { timeout: 10000 }).catch(() => {});
const grafik = await page.evaluate(() => {
    const el = document.getElementById('trend-chart');
    const svg = el?.querySelector('svg');

    return {
        adaSvg: !!svg,
        tinggi: Math.round(el?.getBoundingClientRect().height ?? 0),
        jumlahGaris: el?.querySelectorAll('.apexcharts-series path').length ?? 0,
    };
});
lapor('Grafik tren tergambar', grafik.adaSvg && grafik.tinggi > 100,
    `tinggi ${grafik.tinggi}px, ${grafik.jumlahGaris} seri`);

lapor('Tidak ada galat JavaScript', galat.length === 0, galat.join(' | '));

await browser.close();
console.log(gagal === 0 ? '\nSemua lolos.' : `\n${gagal} gagal.`);
process.exit(gagal === 0 ? 0 : 1);
