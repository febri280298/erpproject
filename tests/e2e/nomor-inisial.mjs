/**
 * Menguji penyisipan inisial mitra ke nomor dokumen.
 *
 * Dikerjakan lewat form aplikasi karena yang menentukan bukan formatternya,
 * melainkan apakah mitra sudah diketahui pada saat nomornya dibuat. Nomor
 * dibuat di dalam transaksi penyimpanan, sedangkan pratinjau di form dibuat
 * sebelum mitranya dipilih — keduanya diperiksa terpisah.
 *
 * Jalankan: node tests/e2e/nomor-inisial.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1700, height: 1000 } });
const page = await ctx.newPage();

const galat = [];
page.on('pageerror', (e) => galat.push(e.message));

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(46)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

const pilih = async (nama, ketik) => {
    const k = page.locator(`select[name="${nama}"] + .ts-wrapper .ts-control`);

    if (await k.count() === 0) {
        return false;
    }

    await k.first().click();
    await page.waitForTimeout(250);
    await page.keyboard.type(ketik);
    await page.waitForTimeout(400);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);

    return true;
};

/* ------------------------------------- 1. Pratinjau menunjukkan slot inisial */

for (const [nama, jalur] of [['Purchase Order', '/purchase-orders/create'],
    ['Surat Jalan', '/delivery-orders/create?mode=manual']]) {
    await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);

    const pratinjau = await page.evaluate(() =>
        document.querySelector('.page-pretitle')?.textContent.trim() ?? '');

    lapor(`Pratinjau ${nama} memuat slot mitra`, pratinjau.includes('(mitra)'), pratinjau);
}

/* ------------------------------------------- 2. Nomor tersimpan memuat inisial */

// PO ke PT GARUDAH BANGUNAN (inisial GB)
await page.goto(`${PANGKAL}/purchase-orders/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(800);
await pilih('partner_id', 'GARUDAH');
await pilih('items[0][product_id]', 'Semen');
await page.fill('input[name="items[0][quantity]"]', '30');
await page.waitForTimeout(400);
await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(800);

const noPo = await page.locator('.page-title').innerText().catch(() => '');
lapor('Nomor PO memuat inisial supplier', /PO\/GB\/\d{4}\/\d{2}\/\d{4}/.test(noPo), noPo);

// Surat jalan ke PT RAVALIA INTI MANDIRI (inisial RIM)
await page.goto(`${PANGKAL}/delivery-orders/create?mode=manual`, { waitUntil: 'networkidle' });
await page.waitForTimeout(800);
await pilih('partner_id', 'RAVALIA');
await pilih('items[0][product_id]', 'Triplek');
await page.fill('input[name="items[0][quantity]"]', '4');
await page.waitForTimeout(400);
await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(800);

const noSj = await page.locator('.page-title').innerText().catch(() => '');
lapor('Nomor Surat Jalan memuat inisial customer', /SJ\/RIM\/\d{4}\/\d{2}\/\d{4}/.test(noSj), noSj);

/* --------------------------------------- 3. GRN mewarisi inisial dari PO-nya */

// GRN butuh PO yang sudah disetujui, jadi membuatnya di tiap kali uji akan
// memakan nomor terus-menerus. Yang diperiksa di sini GRN yang sudah ada:
// nomornya harus memuat inisial supplier pada PO sumbernya.
await page.goto(`${PANGKAL}/goods-receipts`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

const grn = await page.evaluate(() => {
    const baris = document.querySelector('.page-body table tbody tr');

    return baris ? [...baris.children].map((td) => td.textContent.trim()) : null;
});

if (! grn) {
    console.log('  GRN belum ada — pemeriksaan dilewati');
} else {
    // Kolom pertama nomor, dan nama supplier ada di salah satu kolom berikutnya.
    const nomor = grn[0];
    const punyaInisial = /^GRN\/[A-Z0-9]+\/\d{4}\/\d{2}\/\d{4}$/.test(nomor);

    lapor('Nomor GRN memuat inisial supplier', punyaInisial, nomor);
}

/* ------------------------------------------------- 4. Dokumen lain tidak ikut */

await page.goto(`${PANGKAL}/sales-invoices/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(700);
const pratinjauInv = await page.evaluate(() =>
    document.querySelector('.page-pretitle')?.textContent.trim() ?? '');
lapor('Faktur penjualan tetap tanpa inisial', ! pratinjauInv.includes('(mitra)'), pratinjauInv);

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
