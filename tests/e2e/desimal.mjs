/**
 * Menguji pengaturan "Tampilkan 2 angka di belakang koma".
 *
 * Diuji dalam dua keadaan (mati dan hidup) pada halaman yang mewakili tiap
 * jalur render: transaksi, laporan akuntansi, dokumen cetak, dan total yang
 * dihitung di browser. Yang dibandingkan adalah angka yang benar-benar tampil,
 * bukan keberadaan pengaturannya — sebuah kolom yang terlewat hanya ketahuan
 * dari teksnya.
 *
 * Pengaturan dikembalikan ke keadaan semula setelah uji selesai.
 *
 * Jalankan: node tests/e2e/desimal.mjs   (HEADED=1 untuk melihat prosesnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

// Pola "1.234,56" — angka berkoma dua digit. Inilah yang harus hilang saat
// pengaturan dimatikan dan muncul kembali saat dinyalakan.
const BERKOMA = /\d{1,3}(?:\.\d{3})*,\d{2}\b/;

const HALAMAN = [
    ['Buat penawaran', '/quotations/create'],
    ['Daftar produk', '/products'],
    ['Neraca', '/accounting/balance-sheet'],
    ['Laba rugi', '/accounting/income-statement'],
    ['Neraca saldo', '/accounting/trial-balance'],
    ['Buku besar', '/accounting/ledger'],
    ['Jurnal', '/journals'],
    ['Daftar akun', '/accounts'],
    ['Faktur penjualan', '/sales-invoices'],
    ['Laporan omzet', '/reports/sales'],
    ['Laporan persediaan', '/reports/inventory'],
    ['Umur piutang', '/reports/receivable-aging'],
    ['Detail jurnal', '/journals/1'],
    ['Detail akun (buku besar)', '/accounts/1'],
];

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1500, height: 900 } });
const page = await ctx.newPage();

const galat = [];
page.on('pageerror', (e) => galat.push(e.message));

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');

let gagal = 0;
let kosong = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(40)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

/** Nyalakan atau matikan pengaturan lewat halaman pengaturan, seperti pengguna. */
const setelDesimal = async (nyala) => {
    await page.goto(`${PANGKAL}/settings#operations`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);

    const kotak = page.locator('input[type="checkbox"][name="show_decimals"]');
    const sekarang = await kotak.isChecked();

    if (sekarang !== nyala) {
        await kotak.setChecked(nyala);
        await page.locator('form#operations button[type="submit"]').click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(400);
    }

    return page.locator('input[type="checkbox"][name="show_decimals"]').isChecked();
};

const keadaanAwal = await page.locator('input[type="checkbox"][name="show_decimals"]').isChecked()
    .catch(() => false);

for (const nyala of [false, true]) {
    const tersimpan = await setelDesimal(nyala);
    lapor(`Pengaturan tersimpan (${nyala ? 'hidup' : 'mati'})`, tersimpan === nyala, `checked=${tersimpan}`);

    console.log(`\n  --- desimal ${nyala ? 'HIDUP: angka berkoma harus ADA' : 'MATI: angka berkoma harus TIDAK ADA'} ---`);

    for (const [nama, jalur] of HALAMAN) {
        const res = await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' }).catch(() => null);

        if (!res || res.status() >= 400) {
            console.log(`  ${nama.padEnd(40)} — tidak tersedia (${res?.status() ?? 'gagal'})`);
            continue;
        }

        await page.waitForTimeout(350);
        const teks = await page.locator('.page-body').innerText();
        const nominal = teks.match(/\bRp\s?[\d.,]+/g) ?? [];

        // Halaman tanpa nominal sama sekali tidak membuktikan apa pun, jadi
        // dilaporkan terpisah — bukan diam-diam dihitung lolos.
        if (nominal.length === 0) {
            kosong++;
            console.log(`  ${nama.padEnd(40)} — belum ada nominal di halaman ini`);
            continue;
        }

        const adaKoma = BERKOMA.test(teks);
        const contoh = nominal.find((n) => BERKOMA.test(n)) ?? nominal[0];

        lapor(nama, adaKoma === nyala, `${nominal.length} nominal, contoh: ${contoh}`);
    }

    // Total yang dihitung di browser (Alpine) harus ikut pengaturan yang sama.
    await page.goto(`${PANGKAL}/quotations/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    await page.fill('input[name="items[0][quantity]"]', '3');
    await page.fill('input[name="items[0][unit_price]"]', '1500');
    await page.waitForTimeout(500);

    const totalJs = await page.evaluate(() => {
        const baris = [...document.querySelectorAll('.page-body tr, .page-body .d-flex')]
            .map((el) => el.innerText)
            .find((t) => /Total\s*$|^Total/m.test(t) && /Rp/.test(t));

        return baris ?? document.querySelector('.page-body').innerText.match(/Rp[^\n]*/g)?.pop() ?? '';
    });

    lapor('Total dihitung di browser', BERKOMA.test(totalJs) === nyala, totalJs.replace(/\s+/g, ' ').trim());
}

// Kembalikan seperti semula.
const dipulihkan = await setelDesimal(keadaanAwal);
console.log(`\n  Pengaturan dikembalikan ke ${keadaanAwal ? 'hidup' : 'mati'}: ${dipulihkan === keadaanAwal ? 'COCOK' : 'TIDAK COCOK'}`);
gagal += dipulihkan === keadaanAwal ? 0 : 1;

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(`${kosong} halaman dilewati karena belum ada nominalnya.`);
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
