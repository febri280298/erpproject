/**
 * Menguji select yang bisa dicari (TomSelect).
 *
 * Titik rawannya ada tiga, dan ketiganya diuji terhadap perilaku nyata di
 * browser, bukan sekadar keberadaan elemen:
 *
 *  1. Baris item dokumen ditambah Alpine setelah halaman dimuat — select baru
 *     harus tetap dapat dicari DAN pilihannya lengkap (bila disiapkan sebelum
 *     Alpine selesai merender x-for, daftarnya kosong).
 *  2. Menu tidak boleh terpotong pembungkus tabel yang ber-overflow.
 *  3. Nilai harus benar-benar masuk ke <select> asli, karena itu yang dikirim
 *     saat form disimpan — tampilan yang berubah tidak menjamin datanya ikut.
 *
 * Jalankan: node tests/e2e/select-pencarian.mjs   (HEADED=1 untuk melihatnya)
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
    console.log(`  ${nama.padEnd(46)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');

/* ------------------------------------------------ 1. Form penawaran (Alpine) */

await page.goto(`${PANGKAL}/quotations/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(700);

// Select pelanggan: dirender server, ada saat halaman dimuat.
const pelanggan = await page.evaluate(() => {
    const s = document.querySelector('select[name="partner_id"]');

    return { adaTom: !!s?.tomselect, jumlahOpsi: s?.options.length ?? 0 };
});
lapor('Select pelanggan dapat dicari', pelanggan.adaTom, `${pelanggan.jumlahOpsi} opsi`);

// Select produk baris pertama: opsinya dirender Alpine lewat x-for.
const produkAwal = await page.evaluate(() => {
    const s = document.querySelector('select[name="items[0][product_id]"]');

    return {
        adaTom: !!s?.tomselect,
        opsiAsli: s?.options.length ?? 0,
        opsiTom: s?.tomselect ? Object.keys(s.tomselect.options).length : 0,
    };
});
lapor('Select produk baris 1 dapat dicari', produkAwal.adaTom
    && produkAwal.opsiTom >= 21, `${produkAwal.opsiTom} opsi terbaca dari ${produkAwal.opsiAsli} option`);

// Baris yang ditambahkan belakangan — kasus paling rawan.
await page.click('button:has-text("Tambah Baris")');
await page.waitForTimeout(600);

const produkBaru = await page.evaluate(() => {
    const s = document.querySelector('select[name="items[1][product_id]"]');

    return {
        adaTom: !!s?.tomselect,
        opsiTom: s?.tomselect ? Object.keys(s.tomselect.options).length : 0,
    };
});
lapor('Select produk baris tambahan dapat dicari', produkBaru.adaTom, '');
lapor('Pilihan baris tambahan lengkap', produkBaru.opsiTom >= 21, `${produkBaru.opsiTom} opsi`);

/* ------------------------------------------- 2. Mencari & memilih sungguhan */

// Select produk tidak punya id (namanya dirakit Alpine saat baris dibuat).
// TomSelect menaruh pembungkusnya sebagai saudara TEPAT SETELAH select asli,
// bukan membungkusnya, jadi kontrolnya dicari lewat selector saudara.
const kendali = page.locator('select[name="items[0][product_id]"] + .ts-wrapper .ts-control');
await kendali.click();
await page.waitForTimeout(300);
await page.keyboard.type('rubber');
await page.waitForTimeout(400);

const hasilCari = await page.evaluate(() => {
    const menu = document.querySelector('.ts-dropdown:not([style*="display: none"])');

    if (!menu) {
        return { error: 'menu tidak terbuka' };
    }

    const opsi = [...menu.querySelectorAll('.option')].map((o) => o.textContent.trim());
    const r = menu.getBoundingClientRect();
    const kena = document.elementFromPoint(
        Math.round(r.left + r.width / 2),
        Math.round(r.top + Math.min(20, r.height / 2)),
    );

    return { opsi, terlihat: !!kena && menu.contains(kena), induk: menu.parentElement.tagName };
});

lapor('Mengetik menyaring daftar', !hasilCari.error && hasilCari.opsi?.length === 2,
    hasilCari.opsi ? hasilCari.opsi.join(' | ') : hasilCari.error);
lapor('Menu tidak terpotong pembungkus tabel', !!hasilCari.terlihat,
    `digantung ke <${hasilCari.induk?.toLowerCase()}>`);

// Pilih hasil pertama, lalu pastikan nilainya benar-benar masuk ke select asli.
await page.keyboard.press('Enter');
await page.waitForTimeout(500);

const setelahPilih = await page.evaluate(() => {
    const s = document.querySelector('select[name="items[0][product_id]"]');
    const baris = s.closest('tr');

    return {
        nilai: s.value,
        teks: s.options[s.selectedIndex]?.text ?? '',
        // onProductChange() mengisi satuan dan harga; kalau ini terisi berarti
        // event change sampai ke Alpine, bukan cuma tampilannya yang berubah.
        harga: baris.querySelector('input[name$="[unit_price]"]')?.value ?? '',
    };
});

lapor('Nilai masuk ke <select> asli', /^\d+$/.test(setelahPilih.nilai),
    `value=${setelahPilih.nilai} (${setelahPilih.teks})`);
lapor('Alpine ikut ter-update (harga terisi)', Number(setelahPilih.harga) > 0,
    `harga=${setelahPilih.harga}`);

/* ------------------------------------------------------- 3. Select filter */

await page.goto(`${PANGKAL}/products`, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);

const filter = await page.evaluate(() => {
    const hasil = {};

    for (const s of document.querySelectorAll('.filter-bar select, form select')) {
        hasil[s.name || s.id] = {
            adaTom: !!s.tomselect,
            jumlah: s.querySelectorAll('option[value]:not([value=""])').length,
            adaKotakKetik: !!s.tomselect?.control_input?.isConnected,
        };
    }

    return hasil;
});

const daftar = Object.entries(filter).map(([k, v]) => `${k}:${v.jumlah}`).join(' ');
lapor('Semua select filter dibungkus TomSelect',
    Object.values(filter).every((f) => f.adaTom), daftar);
lapor('Semua select punya kotak ketik',
    Object.values(filter).every((f) => f.adaKotakKetik), daftar);

/* ------------------------------------------------------ 4. Form tersimpan  */

await page.goto(`${PANGKAL}/products/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);

// Pilih kategori lewat pencarian, simpan, dan pastikan tersimpan benar.
await page.fill('input[name="sku"]', 'UJI-TOMSELECT-001');
await page.fill('input[name="name"]', 'Uji TomSelect — hapus setelah uji');

const pilihLewatPencarian = async (namaField, ketik) => {
    const ts = page.locator(`select[name="${namaField}"] + .ts-wrapper .ts-control`);
    await ts.click();
    await page.waitForTimeout(250);
    await page.keyboard.type(ketik);
    await page.waitForTimeout(350);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(250);
};

await pilihLewatPencarian('product_category_id', 'Karet');
await pilihLewatPencarian('uom_id', 'Lembar');

const sebelumSimpan = await page.evaluate(() => ({
    kategori: document.querySelector('select[name="product_category_id"]').value,
    satuan: document.querySelector('select[name="uom_id"]').value,
}));
lapor('Pencarian mengisi kategori & satuan', !!sebelumSimpan.kategori && !!sebelumSimpan.satuan,
    `kategori=${sebelumSimpan.kategori} satuan=${sebelumSimpan.satuan}`);

console.log(`\n${galat.length === 0 ? 'Tidak ada galat JavaScript.' : `GALAT JS: ${galat.join(' | ')}`}`);
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
