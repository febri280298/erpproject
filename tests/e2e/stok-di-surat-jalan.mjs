/**
 * Menguji kolom Stok pada form Surat Jalan.
 *
 * Gunanya mencegah surat jalan dibuat untuk barang yang tidak ada. Stok negatif
 * ditolak saat posting, jadi tanpa kolom ini kekurangannya baru ketahuan di
 * langkah terakhir — setelah dokumennya terlanjur dibuat.
 *
 * Diuji pada dua jalur karena keduanya mengambil stok dengan cara berbeda:
 * jalur dari pesanan menghitungnya di server untuk satu gudang, sedangkan jalur
 * manual membacanya di peramban dan harus ikut berubah saat gudang diganti.
 *
 * Jalankan: node tests/e2e/stok-di-surat-jalan.mjs   (HEADED=1 untuk melihatnya)
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

/* ------------------------------------------ 1. Jalur dari pesanan penjualan */

await page.goto(`${PANGKAL}/delivery-orders/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

const soId = await page.evaluate(() =>
    document.querySelector('select[name="sales_order_id"] option[value]:not([value=""])')?.value ?? null);

if (! soId) {
    console.log('  Tidak ada pesanan siap kirim — jalur dari pesanan dilewati.');
} else {
    await page.goto(`${PANGKAL}/delivery-orders/create?sales_order_id=${soId}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);

    const tabel = await page.evaluate(() => {
        const t = document.querySelector('.page-body table');
        const judul = [...t.querySelectorAll('thead th')].map((x) => x.textContent.trim());
        const kolomStok = judul.findIndex((j) => /^Stok/i.test(j));

        const baris = [...t.querySelectorAll('tbody tr')].map((tr) => ({
            produk: tr.children[0].textContent.trim().split('\n')[0],
            sisa: tr.children[3].textContent.trim(),
            stok: kolomStok >= 0 ? tr.children[kolomStok].textContent.trim().replace(/\s+/g, ' ') : null,
            merah: kolomStok >= 0 && tr.children[kolomStok].className.includes('text-danger'),
        }));

        return { judul, kolomStok, baris };
    });

    lapor('Kolom Stok ada dan menyebut gudangnya',
        tabel.kolomStok >= 0 && /Stok\s+\S/.test(tabel.judul[tabel.kolomStok] ?? ''),
        tabel.judul.join(' | '));

    lapor('Stok tiap baris tampil', tabel.baris.every((b) => b.stok !== null && b.stok !== ''),
        tabel.baris.map((b) => `${b.produk.slice(0, 18)}: sisa ${b.sisa} stok ${b.stok}`).join('  |  '));

    // Barang yang stoknya kurang dari sisa kirim harus ditandai merah beserta
    // selisihnya — itu bagian yang menentukan tindakan orang gudang.
    const kurang = tabel.baris.filter((b) => b.merah);
    lapor('Kekurangan stok ditandai merah', kurang.length > 0,
        kurang.map((b) => `${b.produk.slice(0, 20)} → ${b.stok}`).join(' | ') || 'tidak ada yang ditandai');
    lapor('Selisih kekurangan disebutkan', kurang.every((b) => /kurang/i.test(b.stok)), '');
}

/* ----------------------------------------------------- 2. Jalur manual */

await page.goto(`${PANGKAL}/delivery-orders/create?mode=manual`, { waitUntil: 'networkidle' });
await page.waitForTimeout(700);

const pilih = async (nama, ketik) => {
    const k = page.locator(`select[name="${nama}"] + .ts-wrapper .ts-control`);

    if (await k.count() === 0) {
        return false;
    }

    await k.first().click();
    await page.waitForTimeout(250);
    await page.keyboard.type(ketik);
    await page.waitForTimeout(420);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);

    return true;
};

const adaKolom = await page.evaluate(() =>
    [...document.querySelectorAll('.table-items thead th')].map((x) => x.textContent.trim()).includes('Stok'));
lapor('Jalur manual punya kolom Stok', adaKolom, '');

await pilih('partner_id', 'RAVALIA');
await pilih('items[0][product_id]', 'Triplek');
await page.waitForTimeout(500);

const bacaBaris = () => page.evaluate(() => {
    const judul = [...document.querySelectorAll('.table-items thead th')].map((x) => x.textContent.trim());
    const i = judul.indexOf('Stok');
    const tr = document.querySelector('.table-items tbody tr');

    return {
        stok: tr?.children[i]?.textContent.trim() ?? null,
        merah: tr?.children[i]?.className.includes('text-danger') ?? false,
        gudang: document.querySelector('select[name="warehouse_id"]')?.selectedOptions?.[0]?.textContent.trim(),
    };
});

const awal = await bacaBaris();
lapor('Stok terisi setelah produk dipilih', awal.stok !== null && awal.stok !== '—',
    `${awal.gudang}: ${awal.stok}`);

// Melebihi stok harus langsung ditandai, tanpa menunggu simpan.
await page.fill('input[name="items[0][quantity]"]', '999');
await page.waitForTimeout(400);
const kelebihan = await bacaBaris();
lapor('Jumlah melebihi stok langsung ditandai merah', kelebihan.merah, `qty 999 vs stok ${kelebihan.stok}`);

await page.fill('input[name="items[0][quantity]"]', '1');
await page.waitForTimeout(400);
const wajar = await bacaBaris();
lapor('Jumlah wajar tidak ditandai', ! wajar.merah, `qty 1 vs stok ${wajar.stok}`);

// Ganti gudang: angkanya harus ikut berubah tanpa memuat ulang halaman.
const gudangLain = await page.evaluate(() => {
    const s = document.querySelector('select[name="warehouse_id"]');
    const lain = [...s.options].find((o) => o.value && o.value !== s.value);

    return lain ? { value: lain.value, label: lain.textContent.trim() } : null;
});

if (! gudangLain) {
    console.log('  Hanya ada satu gudang — pergantian gudang tidak diuji.');
} else {
    await page.selectOption('select[name="warehouse_id"]', gudangLain.value);
    await page.waitForTimeout(500);
    const sesudah = await bacaBaris();

    lapor('Stok ikut berubah saat gudang diganti', sesudah.stok !== awal.stok,
        `${awal.gudang}: ${awal.stok} → ${sesudah.gudang}: ${sesudah.stok}`);
}

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
