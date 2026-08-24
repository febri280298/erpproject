/**
 * Menguji tombol buka rincian pada daftar Penerimaan Barang.
 *
 * Gunanya melihat isi dokumen tanpa berpindah halaman. Yang dibuktikan bukan
 * sekadar barisnya muncul, melainkan isinya benar milik dokumen itu — baris
 * yang terbuka mudah tertukar antar dokumen bila keadaan buka/tutupnya
 * dibagikan secara keliru.
 *
 * Jalankan: node tests/e2e/rincian-penerimaan.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';
import { AMBANG_AA, kontrasDari } from './lib/kontras.mjs';

const PANGKAL = 'http://127.0.0.1:7001';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
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

await page.goto(`${PANGKAL}/goods-receipts`, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);

const jumlahDokumen = await page.locator('.page-body tbody[x-data]').count();
lapor('Daftar penerimaan berisi dokumen', jumlahDokumen > 0, `${jumlahDokumen} dokumen`);

if (jumlahDokumen === 0) {
    await browser.close();
    process.exit(1);
}

/** Rincian yang sedang terlihat, beserta dokumen pemiliknya. */
const rincianTerbuka = () => page.evaluate(() =>
    [...document.querySelectorAll('.page-body tbody[x-data]')]
        .map((tb) => {
            // :scope > tr supaya baris tabel rincian di dalamnya tidak ikut terhitung.
            const barisRincian = tb.querySelectorAll(':scope > tr')[1];
            const terlihat = barisRincian && barisRincian.offsetParent !== null;

            return terlihat
                ? {
                    dokumen: tb.querySelector('a')?.textContent.trim(),
                    /*
                     * tBodies[0].rows, bukan querySelectorAll('tbody tr').
                     * Selektor keturunan mencocokkan leluhur DI MANA PUN, dan
                     * tabel rincian ini sendiri berada di dalam tbody luar —
                     * sehingga baris judulnya pun punya leluhur tbody dan ikut
                     * terjaring, membuat jumlah barisnya selalu lebih satu.
                     */
                    isi: [...(barisRincian.querySelector('table')?.tBodies[0]?.rows ?? [])]
                        .map((tr) => tr.children[1].textContent.replace(/\s+/g, ' ').trim()),
                }
                : null;
        })
        .filter(Boolean));

lapor('Semula semua rincian tertutup', (await rincianTerbuka()).length === 0, '');

// Buka dokumen pertama.
await page.locator('.page-body tbody[x-data] button[aria-label^="Lihat barang"]').first().click();
await page.waitForTimeout(400);

const setelahBuka = await rincianTerbuka();

lapor('Hanya dokumen yang diklik yang terbuka', setelahBuka.length === 1,
    setelahBuka.map((r) => r.dokumen).join(', ') || 'tidak ada yang terbuka');

lapor('Rincian memuat barangnya', (setelahBuka[0]?.isi.length ?? 0) > 0,
    `${setelahBuka[0]?.dokumen}: ${setelahBuka[0]?.isi.join(' | ') || 'kosong'}`);

/*
 * Ada isinya belum berarti terbaca. Utilitas latar Tabler berakhiran -lt ikut
 * memaksa warna teks terang lewat !important, sehingga baris yang tampil di DOM
 * bisa saja putih di atas latar putih — dan pemeriksaan yang hanya menghitung
 * baris akan tetap lolos. Jadi rasio kontrasnya yang dituntut, bukan sekadar
 * keberadaannya.
 */
const kontras = await kontrasDari(page, `
    const tb = document.querySelector('.page-body tbody[x-data]');

    return tb.querySelectorAll(':scope > tr')[1]
        .querySelector('table')?.tBodies[0]?.rows[0]?.children[1] ?? null;
`);

lapor('Nama barang cukup kontras untuk dibaca', (kontras?.rasio ?? 0) >= AMBANG_AA,
    kontras ? `${kontras.rasio}:1 — ${kontras.teks} di atas ${kontras.latar}` : 'sel tidak ditemukan');

// Isi yang terbuka harus benar-benar milik dokumen itu, bukan dokumen lain.
const cocok = await page.evaluate((nomor) => {
    const tb = [...document.querySelectorAll('.page-body tbody[x-data]')]
        .find((x) => x.querySelector('a')?.textContent.trim() === nomor);

    const baris = tb?.querySelectorAll(':scope > tr')[1];

    return baris?.querySelector('table')?.tBodies[0]?.rows.length ?? 0;
}, setelahBuka[0]?.dokumen);

lapor('Rincian berada di dalam baris dokumennya', cocok === (setelahBuka[0]?.isi.length ?? -1),
    `${cocok} baris`);

// Buka dokumen kedua: keduanya boleh terbuka bersamaan, tetapi isinya harus
// tetap terpisah — inilah yang rusak bila keadaannya dibagikan.
if (jumlahDokumen > 1) {
    await page.locator('.page-body tbody[x-data] button[aria-label^="Lihat barang"]').nth(1).click();
    await page.waitForTimeout(400);

    const dua = await rincianTerbuka();

    lapor('Dokumen kedua terbuka tanpa mengganggu yang pertama', dua.length === 2,
        dua.map((r) => r.dokumen).join(', '));

    lapor('Isi tiap dokumen tetap terpisah',
        dua.length === 2 && dua[0].dokumen !== dua[1].dokumen,
        dua.map((r) => `${r.dokumen}: ${r.isi.length} baris`).join('  |  '));
}

// Tutup lagi.
await page.locator('.page-body tbody[x-data] button[aria-label^="Lihat barang"]').first().click();
await page.waitForTimeout(400);
const setelahTutup = await rincianTerbuka();

lapor('Bisa ditutup kembali', setelahTutup.length === (jumlahDokumen > 1 ? 1 : 0),
    `tersisa terbuka: ${setelahTutup.map((r) => r.dokumen).join(', ') || 'tidak ada'}`);

await page.screenshot({ path: 'tests/e2e/hasil/grn-rincian.png', fullPage: true });

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
