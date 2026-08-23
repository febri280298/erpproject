/**
 * Menguji DPP Nilai Lain per baris pada faktur penjualan.
 *
 * Yang dibuktikan bukan sekadar kolomnya muncul, melainkan angkanya benar dan
 * konsisten di tiga tempat: form entri, nilai yang tersimpan di basis data, dan
 * halaman detail. Rumusnya diperiksa terhadap hitungan mandiri di sini —
 * membandingkan tampilan dengan tampilan tidak membuktikan apa-apa.
 *
 * Prasyarat: DPP Nilai Lain harus dalam keadaan aktif (rasio 11/12).
 *
 * Jalankan: node tests/e2e/dpp-per-baris.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1600, height: 950 } });
const page = await ctx.newPage();

const galat = [];
page.on('pageerror', (e) => galat.push(e.message));

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(46)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

const angka = (teks) => Number(String(teks).replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.'));

// Jumlah desimal uang mengikuti Pengaturan (bawaannya mati), jadi harapan uji
// dibaca dari halaman itu sendiri — bukan dipatok dua desimal.
const rupiah = (n, desimal) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: desimal,
    maximumFractionDigits: desimal,
}).format(n);

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

/* --------------------------------------------------- 1. Kolom di form entri */

await page.goto(`${PANGKAL}/sales-invoices/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(800);

const adaKolom = await page.evaluate(() => {
    const judul = [...document.querySelectorAll('.table-items thead th')].map((th) => th.textContent.trim());

    return { judul, ada: judul.includes('DPP Nilai Lain') };
});
lapor('Kolom "DPP Nilai Lain" tampil di form', adaKolom.ada, adaKolom.judul.join(' | '));

/* ------------------------------------------------- 2. Angka per baris benar */

const pilih = async (nama, ketik) => {
    await page.locator(`select[name="${nama}"] + .ts-wrapper .ts-control`).click();
    await page.waitForTimeout(250);
    await page.keyboard.type(ketik);
    await page.waitForTimeout(400);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);
};

await pilih('partner_id', 'RAVALIA');
await pilih('items[0][product_id]', 'Triplek');
await page.fill('input[name="items[0][quantity]"]', '10');
await page.fill('input[name="items[0][tax_rate]"]', '12');
await page.waitForTimeout(500);

// Triplek Albasia: harga jual 80.000 x 10 = 800.000 DPP.
// DPP Nilai Lain = 11/12 x 800.000 = 733.333,33
// PPN 12% dari nilai lain = 88.000 (setara 11% dari harga jual).
const DPP = 800000;
const NILAI_LAIN = Math.round((DPP * 11 / 12) * 100) / 100;
const PPN = Math.round(NILAI_LAIN * 0.12 * 100) / 100;

// Sel dibaca lewat judul kolomnya, bukan urutan posisi: urutan kolom pernah
// berubah dan uji berbasis posisi diam-diam membandingkan angka yang salah.
const dariForm = await page.evaluate(() => {
    const judul = [...document.querySelectorAll('.table-items thead th')]
        .map((th) => th.textContent.trim());
    // Bukan tr:first-child — anak pertama tbody adalah <template> milik Alpine,
    // bukan barisnya, sehingga selector itu tidak pernah cocok.
    const baris = document.querySelector('.table-items tbody tr');
    const sel = baris ? [...baris.children].map((td) => td.textContent.trim()) : [];

    const ambil = (nama) => {
        const i = judul.findIndex((j) => j.toLowerCase() === nama.toLowerCase());

        return i >= 0 ? sel[i] : null;
    };

    return { judul, dpp: ambil('DPP'), nilaiLain: ambil('DPP Nilai Lain') };
});

if (! dariForm.dpp || ! dariForm.nilaiLain) {
    console.log('  Kolom DPP / DPP Nilai Lain tidak ditemukan:', dariForm.judul.join(' | '));
    process.exit(1);
}

const dppTampil = angka(dariForm.dpp);
const nilaiLainTampil = angka(dariForm.nilaiLain);

// Desimal yang dipakai halaman disimpulkan dari angka yang tampil, agar uji ini
// tetap sahih baik saat desimal uang dinyalakan maupun dimatikan.
const desimal = (dariForm.nilaiLain.split(',')[1] ?? '').length;
const toleransi = desimal >= 2 ? 0.01 : 1;

lapor('DPP baris benar', Math.abs(dppTampil - DPP) <= toleransi,
    `${dariForm.dpp} (harap ${rupiah(DPP, desimal)})`);
lapor('DPP Nilai Lain baris benar', Math.abs(nilaiLainTampil - NILAI_LAIN) <= toleransi,
    `${dariForm.nilaiLain} (harap ${rupiah(NILAI_LAIN, desimal)} = 11/12 x ${rupiah(DPP, desimal)})`);

/* ------------------------------------------------------ 3. Tersimpan benar */

await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(800);

const tersimpan = /\/sales-invoices\/\d+/.test(page.url());
lapor('Faktur tersimpan', tersimpan, page.url().replace(PANGKAL, ''));

if (tersimpan) {
    const isi = await page.locator('.page-body').innerText();

    // Tidak peka huruf besar/kecil: CSS menampilkan judul kolom dalam huruf
    // besar, dan innerText mengembalikan teks yang dirender, bukan sumbernya.
    lapor('Kolom tampil di halaman detail', /DPP NILAI LAIN/i.test(isi), '');
    lapor('Nilai lain per baris tercetak di detail',
        isi.includes(rupiah(NILAI_LAIN, desimal)), `mencari "${rupiah(NILAI_LAIN, desimal)}"`);
    lapor('PPN dihitung dari nilai lain, bukan DPP',
        isi.includes(rupiah(PPN, desimal)), `PPN 12% x nilai lain = ${rupiah(PPN, desimal)}`);

    const id = page.url().match(/\d+$/)[0];
    console.log('  ID faktur uji:', id);

    /* ------------------------------------------------------- 4. Cetakan A4 */

    await page.goto(`${PANGKAL}/sales-invoices/${id}/print`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);

    const cetak = await page.evaluate(() => {
        // Halaman cetak memuat beberapa tabel (keterangan dokumen, rekap total);
        // rincian itemnya adalah tabel berkelas .items.
        const t = document.querySelector('table.items');

        return {
            judul: [...t.querySelectorAll('thead th')].map((x) => x.textContent.trim()),
            baris: [...(t.querySelector('tbody tr')?.children ?? [])].map((x) => x.textContent.trim()),
        };
    });

    lapor('Kolom tercetak di dokumen A4',
        cetak.judul.some((j) => /DPP Nilai Lain/i.test(j)), cetak.judul.join(' | '));
    lapor('Jumlah sel cetakan sama dengan jumlah kolom',
        cetak.baris.length === cetak.judul.length,
        `${cetak.baris.length} sel vs ${cetak.judul.length} kolom`);

    await page.screenshot({ path: 'tests/e2e/hasil/dpp-cetak.png', fullPage: true });
}

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
