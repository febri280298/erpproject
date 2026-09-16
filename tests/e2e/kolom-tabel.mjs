/**
 * Menguji pemilih kolom di halaman daftar.
 *
 * Pemilihnya dipasang sendiri ke setiap tabel daftar tanpa suntingan di Blade,
 * jadi yang perlu dibuktikan bukan satu halaman melainkan bahwa ia benar-benar
 * hinggap di semua — dan tidak merusak tabel yang barisnya ber-colspan.
 *
 * Yang diperiksa:
 *
 *  1. Tombol "Kolom" muncul di tiap halaman daftar.
 *  2. Mencentang-lepas satu kolom benar-benar menyembunyikan sel yang sama di
 *     kepala maupun badan tabel — bukan sekadar kepalanya.
 *  3. Baris ber-colspan tidak ikut tergeser.
 *  4. Pilihannya diingat setelah halaman dimuat ulang.
 *
 * Jalankan: node tests/e2e/kolom-tabel.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const HALAMAN = [
    ['Produk', '/products'],
    ['Customer & Supplier', '/partners'],
    ['Pesanan Penjualan', '/sales-orders'],
    ['Surat Jalan', '/delivery-orders'],
    ['Faktur Penjualan', '/sales-invoices'],
    ['Purchase Order', '/purchase-orders'],
    ['Stok Barang', '/stocks'],
    ['Jurnal Umum', '/journals'],
];

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`    ${nama.padEnd(38)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

for (const [nama, jalur] of HALAMAN) {
    const res = await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' }).catch(() => null);

    if (! res || res.status() >= 400) {
        console.log(`\n  ${nama}: tidak tersedia (${res?.status() ?? 'gagal'})`);
        continue;
    }

    console.log(`\n  ${nama}`);
    await page.waitForTimeout(250);

    const tombol = page.locator('.dropdown-toggle', { hasText: 'Kolom' }).first();
    const ada = await tombol.count() > 0;
    lapor('Tombol Kolom terpasang', ada);

    if (! ada) {
        continue;
    }

    await tombol.click();
    const kotak = page.locator('.dropdown-menu.show input[type="checkbox"]');
    const jumlah = await kotak.count();
    lapor('Daftar kolom terisi', jumlah >= 2, `${jumlah} kolom bisa dipilih`);

    // Kolom kedua dipilih, bukan pertama: kolom pertama sering jadi tautan
    // utama baris dan paling terasa bila keliru disembunyikan.
    const sebelum = await page.evaluate(() => {
        const t = document.querySelector('table.card-table');

        return {
            kepala: [...t.querySelectorAll(':scope > thead > tr > th')].filter((el) => ! el.hidden).length,
            barisPertama: [...(t.querySelector(':scope > tbody > tr')?.children ?? [])].filter((el) => ! el.hidden).length,
        };
    });

    await kotak.nth(1).uncheck();
    await page.waitForTimeout(150);

    const sesudah = await page.evaluate(() => {
        const t = document.querySelector('table.card-table');
        const trs = [...t.querySelectorAll(':scope > tbody > tr')];

        return {
            kepala: [...t.querySelectorAll(':scope > thead > tr > th')].filter((el) => ! el.hidden).length,
            barisPertama: [...(trs[0]?.children ?? [])].filter((el) => ! el.hidden).length,
            // Baris ber-colspan tidak boleh ikut disembunyikan selnya.
            colspanUtuh: trs
                .filter((tr) => [...tr.children].some((td) => td.hasAttribute('colspan')))
                .every((tr) => [...tr.children].every((td) => ! td.hidden)),
        };
    });

    lapor('Kepala tabel berkurang satu', sesudah.kepala === sebelum.kepala - 1,
        `${sebelum.kepala} -> ${sesudah.kepala}`);

    if (sebelum.barisPertama > 0) {
        lapor('Sel badan ikut tersembunyi', sesudah.barisPertama === sebelum.barisPertama - 1,
            `${sebelum.barisPertama} -> ${sesudah.barisPertama}`);
    }

    lapor('Baris colspan tidak tergeser', sesudah.colspanUtuh);

    // Diingat setelah dimuat ulang?
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(250);

    const setelahMuatUlang = await page.evaluate(() =>
        [...document.querySelectorAll('table.card-table > thead > tr > th')].filter((el) => ! el.hidden).length);

    lapor('Pilihan diingat setelah dimuat ulang', setelahMuatUlang === sesudah.kepala,
        `${setelahMuatUlang} kolom`);

    // Dikembalikan supaya halaman berikutnya dan pemakaian sehari-hari bersih.
    await page.evaluate(() => {
        Object.keys(window.localStorage)
            .filter((k) => k.startsWith('erp-kolom:'))
            .forEach((k) => window.localStorage.removeItem(k));
    });
}

await browser.close();
console.log(gagal === 0 ? '\nSemua pemeriksaan lolos.' : `\n${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
