/**
 * Membuka setiap halaman form dan jalur masuknya.
 *
 * Dokumen kini punya beberapa pintu masuk: form kosong, dari satu dokumen
 * sumber, dan dari beberapa sumber sekaligus. Ketiganya berbagi berkas Blade
 * yang sama, sehingga perubahan untuk satu jalur bisa merusak jalur lain tanpa
 * terlihat — variabel yang hanya ada di salah satu jalur adalah penyebab yang
 * paling sering.
 *
 * Uji ini tidak menyimpan apa pun. Yang dituntut: halamannya benar-benar
 * terbentuk (status 200 dan kerangka aplikasinya ada) serta tanpa galat
 * JavaScript.
 *
 * Keberadaan <form> sengaja TIDAK dituntut. Halaman pemilih sumber menampilkan
 * keadaan kosong tanpa form ketika belum ada dokumen yang bisa dipilih, dan itu
 * perilaku yang benar — menuntutnya menghasilkan kegagalan palsu yang lama-lama
 * membuat hasil uji ini diabaikan.
 *
 * Jalankan: node tests/e2e/halaman-form.mjs
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
const page = await ctx.newPage();

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

/** Satu id dokumen yang ada, untuk jalur "dari dokumen sumber". */
const idPertama = async (daftar, pola) => {
    const res = await page.goto(PANGKAL + daftar, { waitUntil: 'networkidle' }).catch(() => null);

    if (!res || res.status() >= 400) {
        return null;
    }

    await page.waitForTimeout(250);

    return page.evaluate((p) => {
        const a = [...document.querySelectorAll('.page-body table tbody a')]
            .find((x) => new RegExp(p).test(x.getAttribute('href') ?? ''));

        return a ? (a.getAttribute('href').match(/(\d+)$/)?.[1] ?? null) : null;
    }, pola);
};

/*
 * PO untuk jalur "dari satu pesanan" diambil dari layar pemilih, bukan dari
 * daftar PO. Daftar PO memuat semua pesanan termasuk yang sudah tertagih penuh,
 * dan membuka jalur itu dengan pesanan seperti itu dijawab 403 — penjagaan yang
 * benar, tetapi bukan yang sedang diuji di sini.
 */
await page.goto(`${PANGKAL}/purchase-invoices/select-orders`, { waitUntil: 'networkidle' });
await page.waitForTimeout(300);

const supplierPertama = await page.evaluate(() =>
    document.querySelector('select[name="partner_id"] option[value]:not([value=""])')?.value ?? null);

let poId = null;

if (supplierPertama) {
    await page.goto(`${PANGKAL}/purchase-invoices/select-orders?partner_id=${supplierPertama}`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(300);
    poId = await page.evaluate(() =>
        document.querySelector('input[name="purchase_order_ids[]"]')?.value ?? null);
}

const HALAMAN = [
    ['Master · Produk', '/products/create'],
    ['Master · Mitra', '/partners/create'],
    ['Master · Gudang', '/warehouses/create'],

    ['Pembelian · PO', '/purchase-orders/create'],
    ['Pembelian · Penerimaan', '/goods-receipts/create'],
    ['Pembelian · Faktur (kosong)', '/purchase-invoices/create'],
    ['Pembelian · Faktur (pilih PO)', '/purchase-invoices/select-orders'],
    ['Pembelian · Faktur (1 PO)', poId ? `/purchase-invoices/from-order/${poId}` : null],
    ['Pembelian · Bayar Supplier', '/supplier-payments/create'],

    ['Penjualan · Penawaran', '/quotations/create'],
    ['Penjualan · SO (pilih sumber)', '/sales-orders/create'],
    ['Penjualan · SO (manual)', '/sales-orders/create?mode=manual'],
    ['Penjualan · Surat Jalan', '/delivery-orders/create'],
    ['Penjualan · SJ (manual)', '/delivery-orders/create?mode=manual'],
    ['Penjualan · Faktur', '/sales-invoices/create'],
    ['Penjualan · Faktur (pilih SJ)', '/sales-invoices/select-deliveries'],
    ['Penjualan · Terima Bayar', '/customer-payments/create'],
    ['Penjualan · Retur', '/sales-returns/create'],

    ['Stok · Transfer', '/stock-transfers/create'],
    ['Stok · Penyesuaian', '/stock-adjustments/create'],

    ['Akuntansi · Jurnal', '/journals/create'],
    ['Sistem · Pengguna', '/users/create'],
    ['Sistem · Peran', '/roles/create'],
];

let gagal = 0;
let diuji = 0;

for (const [nama, jalur] of HALAMAN) {
    if (! jalur) {
        console.log(`  ${nama.padEnd(32)} dilewati — tidak ada dokumen sumber`);
        continue;
    }

    const galat = [];
    const dengar = (e) => galat.push(e.message);
    page.on('pageerror', dengar);

    const res = await page.goto(PANGKAL + jalur, { waitUntil: 'networkidle' }).catch(() => null);
    await page.waitForTimeout(300);

    const kode = res?.status() ?? 0;

    if (kode === 404) {
        console.log(`  ${nama.padEnd(32)} dilewati — modul tidak aktif`);
        page.off('pageerror', dengar);
        continue;
    }

    diuji++;

    const isi = await page.evaluate(() => ({
        // Halaman galat Laravel memakai tata letak sendiri tanpa .page-body,
        // jadi keberadaannya sekaligus membuktikan halaman aplikasinya terbentuk.
        adaKerangka: !! document.querySelector('.page-body'),
        adaForm: document.querySelectorAll('.page-body form').length > 0,
        judul: document.querySelector('h1, .page-title')?.textContent.trim().slice(0, 60) ?? '',
    }));

    const lolos = kode === 200 && isi.adaKerangka && galat.length === 0;
    lolos || gagal++;

    console.log(`  ${nama.padEnd(32)} ${lolos ? 'LOLOS' : 'GAGAL'}  ${kode}`
        + (isi.adaForm ? '' : '  (halaman pemilih, belum ada form)')
        + (lolos ? '' : `  ${isi.judul}${galat.length ? ' | JS: ' + galat.join(' ; ') : ''}`));

    page.off('pageerror', dengar);
}

await browser.close();
console.log(`\n${diuji} halaman diuji. ${gagal === 0 ? 'Semua terbuka dengan benar.' : `${gagal} gagal.`}`);
process.exit(gagal === 0 ? 0 : 1);
