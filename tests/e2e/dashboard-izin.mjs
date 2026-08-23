/**
 * Menguji pembatasan dashboard per modul untuk setiap peran.
 *
 * Tiga hal yang diperiksa, dan ketiganya adalah cara nyata dashboard bocor:
 *
 *  1. Akses halaman — peran yang tidak berkepentingan harus ditolak.
 *  2. Angka uang — nilai ringkasan keuangan mengikuti izin report.view, sama
 *     seperti modul Laporan yang sudah menyajikan angka itu. Uji ini menjaga
 *     agar keduanya tidak pernah berbeda untuk orang yang sama.
 *  3. Tautan mati — dashboard yang memuat tautan berujung 403 lebih
 *     membingungkan daripada tidak ada tautan sama sekali.
 *
 * Harapan akses di bawah diturunkan dari config/erp.php → roles, bukan dikarang:
 * Gudang memang memegang purchase-order.view dan sales-order.view karena harus
 * melihat dokumen itu untuk menerima dan mengirim barang, dan semua peran
 * memegang stock.view.
 *
 * Jalankan: node tests/e2e/dashboard-izin.mjs
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const JALUR = [
    ['master', '/dashboard/master'],
    ['purchasing', '/dashboard/purchasing'],
    ['sales', '/dashboard/sales'],
    ['inventory', '/dashboard/inventory'],
    ['accounting', '/dashboard/accounting'],
];

/**
 * boleh  : dashboard yang harus terbuka (200)
 * uang   : dashboard yang boleh menampilkan nilai uang ringkasan
 *
 * Semua peran trial memegang report.view, dan angka yang sama sudah dapat
 * dibuka lewat modul Laporan. Dashboard mengikuti izin itu supaya kedua tempat
 * menunjukkan hal yang sama — bukan hal berbeda untuk orang yang sama.
 */
const PERAN = {
    'admin': { boleh: ['master', 'purchasing', 'sales', 'inventory', 'accounting'], uang: ['purchasing', 'sales'] },
    'manajer': { boleh: ['master', 'purchasing', 'sales', 'inventory', 'accounting'], uang: ['purchasing', 'sales'] },
    'pembelian': { boleh: ['master', 'purchasing', 'inventory'], uang: ['purchasing'] },
    'penjualan': { boleh: ['master', 'sales', 'inventory'], uang: ['sales'] },
    'gudang': { boleh: ['master', 'purchasing', 'sales', 'inventory'], uang: ['purchasing', 'sales'] },
    'akuntansi': { boleh: ['master', 'purchasing', 'sales', 'inventory', 'accounting'], uang: ['purchasing', 'sales'] },
};

// Penanda kartu uang yang tidak boleh terlihat oleh yang tak berhak.
const PENANDA_UANG = {
    purchasing: ['Utang belum dibayar', 'Belanja bulan ini'],
    sales: ['Piutang belum tertagih', 'Omzet bulan ini'],
};

const browser = await chromium.launch({ channel: 'chrome', headless: true });

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`    ${nama.padEnd(38)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

for (const [peran, harapan] of Object.entries(PERAN)) {
    console.log(`\n  Peran: ${peran}`);

    const ctx = await browser.newContext({ viewport: { width: 1500, height: 900 } });
    const page = await ctx.newPage();

    await page.goto(`${PANGKAL}/login`);
    await page.fill('input[name="email"]', `${peran}@bonecomtricom.com`);
    await page.fill('input[name="password"]', 'password');
    await page.click('form button[type="submit"]');
    await page.waitForURL('**/dashboard').catch(() => {});

    const akses = [];
    const salahAkses = [];

    for (const [nama, jalur] of JALUR) {
        const r = await page.request.get(PANGKAL + jalur).catch(() => null);
        const kode = r?.status() ?? 0;
        const seharusnya = harapan.boleh.includes(nama);
        const nyata = kode === 200;

        akses.push(`${nama}:${kode}`);
        seharusnya === nyata || salahAkses.push(`${nama} → ${kode} (harap ${seharusnya ? '200' : 'ditolak'})`);
    }

    lapor('Akses sesuai peran', salahAkses.length === 0,
        salahAkses.length ? salahAkses.join('; ') : akses.join('  '));

    // Angka uang & tautan mati diperiksa pada halaman yang memang terbuka.
    for (const [nama, jalur] of JALUR) {
        if (! harapan.boleh.includes(nama) || ! PENANDA_UANG[nama]) {
            continue;
        }

        await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' });
        await page.waitForTimeout(400);
        const isi = await page.locator('.page-body').innerText();

        const terlihat = PENANDA_UANG[nama].filter((t) => isi.includes(t));
        const bolehUang = harapan.uang.includes(nama);

        lapor(`Angka uang ${nama}`,
            bolehUang ? terlihat.length > 0 : terlihat.length === 0,
            bolehUang
                ? `tampil (${terlihat.length}/${PENANDA_UANG[nama].length})`
                : (terlihat.length ? `BOCOR: ${terlihat.join(', ')}` : 'tersembunyi'));
    }

    // Tidak boleh ada tautan yang berujung 403/404 di dashboard mana pun.
    const mati = [];

    for (const [nama, jalur] of JALUR) {
        if (! harapan.boleh.includes(nama)) {
            continue;
        }

        await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' });
        await page.waitForTimeout(300);

        const tautan = await page.evaluate(() => [...new Set(
            [...document.querySelectorAll('.page-body a[href^="http"]')]
                .map((a) => a.href)
                .filter((h) => h.startsWith(location.origin) && !h.includes('#')),
        )]);

        for (const t of tautan) {
            const r = await page.request.get(t).catch(() => null);

            if (!r || r.status() >= 400) {
                mati.push(`${nama}: ${t.replace(PANGKAL, '')} → ${r?.status() ?? 'gagal'}`);
            }
        }
    }

    lapor('Tanpa tautan mati', mati.length === 0, mati.length ? mati.join('; ') : 'semua tautan hidup');

    await ctx.close();
}

await browser.close();
console.log(`\n${gagal === 0 ? 'Semua pemeriksaan izin lolos.' : `${gagal} pemeriksaan gagal.`}`);
process.exit(gagal === 0 ? 0 : 1);
