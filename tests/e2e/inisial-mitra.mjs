/**
 * Menguji kode inisial mitra sekaligus memasukkan supplier PT Garudah Bangunan.
 *
 * Dikerjakan lewat form aplikasi, bukan langsung ke basis data, supaya yang
 * teruji adalah jalur yang benar-benar dipakai orang: pengisian otomatis dari
 * nama, normalisasi huruf besar, aturan unik, dan tersimpannya data.
 *
 * Jalankan: node tests/e2e/inisial-mitra.mjs   (HEADED=1 untuk melihatnya)
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
    console.log(`  ${nama.padEnd(44)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

/* ------------------------------------- 1. Pengisian otomatis dari nama */

await page.goto(`${PANGKAL}/partners/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

const contoh = [
    ['PT Ravalia Inti Mandiri', 'RIM'],
    ['PT Garudah Bangunan', 'GB'],
    ['CV Sumber Rejeki Abadi', 'SRA'],
    ['Toko Bangunan Jaya', 'BJ'],
    ['Ravalia', 'RAV'],
];

for (const [nama, harap] of contoh) {
    await page.fill('input[name="initial"]', '');
    await page.fill('input[name="name"]', '');
    await page.type('input[name="name"]', nama, { delay: 5 });
    await page.waitForTimeout(200);

    const isi = await page.inputValue('input[name="initial"]');
    lapor(`Inisial "${nama}"`, isi === harap, `dapat "${isi}", harap "${harap}"`);
}

/* ---------------------------------- 2. Simpan supplier PT Garudah Bangunan */

// Kode dan inisial bersifat unik, jadi uji ini hanya membuat bila memang belum
// ada — supaya bisa dijalankan berulang tanpa gagal palsu.
await page.goto(`${PANGKAL}/partners`, { waitUntil: 'networkidle' });
await page.waitForTimeout(400);
const sudahAda = /GARUDAH/i.test(await page.locator('.page-body').innerText());

if (sudahAda) {
    console.log('  Supplier PT Garudah Bangunan sudah ada — pembuatan dilewati');
} else {
    await page.goto(`${PANGKAL}/partners/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);

    await page.fill('input[name="code"]', 'SUPP-001');
    await page.type('input[name="name"]', 'PT GARUDAH BANGUNAN', { delay: 5 });
    await page.waitForTimeout(250);

    const inisialOtomatis = await page.inputValue('input[name="initial"]');
    lapor('Inisial supplier terisi otomatis', inisialOtomatis === 'GB', `"${inisialOtomatis}"`);

    // Tipe dan termin dipilih lewat select yang bisa dicari.
    const pilihSelect = async (nama, ketik) => {
        await page.locator(`select[name="${nama}"] + .ts-wrapper .ts-control`).click();
        await page.waitForTimeout(250);
        await page.keyboard.type(ketik);
        await page.waitForTimeout(350);
        await page.keyboard.press('Enter');
        await page.waitForTimeout(250);
    };

    await pilihSelect('type', 'Pemasok');
    await pilihSelect('payment_term_id', 'Net 30');
    await page.fill('input[name="city"]', 'Cikarang');

    await page.click('.page-body form button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);

    const tersimpan = /\/partners\/\d+/.test(page.url()) || page.url().endsWith('/partners');
    lapor('Supplier tersimpan', tersimpan, page.url().replace(PANGKAL, ''));
}

/* --------------------------------------- 3. Isi inisial RIM untuk Ravalia */

await page.goto(`${PANGKAL}/partners`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

// Id diambil dari tautan barisnya, lalu langsung ke halaman ubah — mencari
// tombol "Ubah" lewat teks ikut menangkap item menu lain di halaman.
const tautanRavalia = await page.locator('tr', { hasText: 'RAVALIA' }).first()
    .locator('a[href*="/partners/"]').first().getAttribute('href');
const idRavalia = tautanRavalia.match(/\/partners\/(\d+)/)[1];

await page.goto(`${PANGKAL}/partners/${idRavalia}/edit`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

// Diketik huruf kecil dengan spasi, untuk sekalian menguji normalisasinya.
await page.fill('input[name="initial"]', 'r i m');
await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(600);

/* ------------------------------------------------ 4. Periksa hasil akhir */

await page.goto(`${PANGKAL}/partners`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

const tabel = await page.locator('.page-body table').innerText();
lapor('Ravalia berinisial RIM', /RAVALIA[\s\S]*?/.test(tabel) && /\bRIM\b/.test(tabel), '');
lapor('Garudah Bangunan berinisial GB', /GARUDAH/i.test(tabel) && /\bGB\b/.test(tabel), '');
lapor('Normalisasi huruf besar bekerja', ! /\brim\b/.test(tabel), 'input "r i m" tersimpan sebagai RIM');

// Inisial harus ikut tercari lewat kotak pencarian.
await page.fill('input[name="q"], input[name="search"]', 'RIM').catch(() => {});
await page.locator('.filter-bar button, button:has-text("Filter")').first().click().catch(() => {});
await page.waitForLoadState('networkidle');
await page.waitForTimeout(500);
const hasilCari = await page.locator('.page-body table').innerText();
lapor('Mitra dapat dicari lewat inisialnya', /RAVALIA/i.test(hasilCari), '');

// Inisial kembar harus ditolak.
await page.goto(`${PANGKAL}/partners/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(400);
await page.fill('input[name="code"]', 'UJI-KEMBAR');
await page.fill('input[name="name"]', 'Uji Inisial Kembar');
await page.fill('input[name="initial"]', 'RIM');
await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(500);
const isiHalaman = await page.locator('body').innerText();
lapor('Inisial kembar ditolak', page.url().includes('/partners/create') && /sudah|digunakan|taken/i.test(isiHalaman),
    isiHalaman.match(/[^\n]*[Ii]nisial[^\n]*/)?.[0]?.trim() ?? '');

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
