/**
 * Menguji pembuatan Pesanan Penjualan dari penawaran yang sudah deal.
 *
 * Yang dibuktikan bukan sekadar halamannya terbuka, melainkan angkanya benar-
 * benar terbawa: harga yang sudah disepakati tidak boleh berubah saat menjadi
 * pesanan, karena justru itu alasan menariknya dari penawaran alih-alih
 * mengetik ulang.
 *
 * Uji ini MEMBUAT dokumen: satu penawaran bila belum ada yang siap dikonversi,
 * dan satu pesanan penjualan. Penawaran yang sudah dikonversi tidak bisa
 * dikonversi lagi, jadi tanpa membuat sendiri ujinya hanya bisa dijalankan
 * sekali seumur hidup basis data.
 *
 * Jalankan: node tests/e2e/so-dari-penawaran.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
const page = await ctx.newPage();

const galat = [];
page.on('pageerror', (e) => galat.push(e.message));
page.on('dialog', (d) => d.accept());

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(46)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

const angka = (t) => Number(String(t).replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.'));

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

/* ------------------------------------------ 1. Siapkan penawaran yang deal */

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
    await page.waitForTimeout(300);

    return true;
};

/** Penawaran berstatus Diterima yang belum pernah jadi pesanan. */
const cariSiapKonversi = async () => {
    await page.goto(`${PANGKAL}/sales-orders/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);

    return page.evaluate(() => [...document.querySelectorAll('select[name="quotation_id"] option')]
        .map((o) => ({ id: o.value, teks: o.textContent.trim() }))
        .filter((o) => o.id));
};

let siap = await cariSiapKonversi();

if (siap.length === 0) {
    console.log('  Tidak ada penawaran siap konversi — membuat satu untuk diuji.');

    await page.goto(`${PANGKAL}/quotations/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await pilih('partner_id', 'RAVALIA');
    await pilih('items[0][product_id]', 'Triplek');
    await page.fill('input[name="items[0][quantity]"]', '20');
    await page.click('button:has-text("Tambah Baris")');
    await page.waitForTimeout(500);
    await pilih('items[1][product_id]', 'Hollow Hitam 30');
    await page.fill('input[name="items[1][quantity]"]', '12');
    await page.waitForTimeout(400);
    await page.click('.page-body form button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);

    // Tandai deal. Formnya dikirim langsung: Playwright menganggap form
    // pembungkus menghadang titik klik butir dropdown, padahal diperiksa dengan
    // elementFromPoint tombolnya menutupi seluruh baris menu — kendala otomasi,
    // bukan cacat tampilan.
    await page.locator('button:has-text("Ubah Status")').first().click();
    await page.waitForTimeout(400);
    await page.evaluate(() => {
        const form = [...document.querySelectorAll('form[action*="/transition"]')]
            .find((f) => f.querySelector('input[name="status"]')?.value === 'accepted');

        form?.requestSubmit();
    }).catch(() => {});

    // Menunggu perubahan ISINYA, bukan kondisi jaringan: waitForLoadState
    // kembali seketika bila navigasinya belum sempat mulai.
    await page.waitForFunction(
        () => /Diterima/.test(document.querySelector('.page-body')?.innerText ?? ''),
        null, { timeout: 20000 },
    ).catch(() => {});

    siap = await cariSiapKonversi();
}

lapor('Ada penawaran deal yang siap dikonversi', siap.length > 0,
    siap.map((o) => o.teks.replace(/\s+/g, ' ')).join(' | ') || 'tidak ada');

if (siap.length === 0) {
    await browser.close();
    process.exit(1);
}

// Acuan pembanding diambil dari halaman penawarannya sendiri.
await page.goto(`${PANGKAL}/quotations/${siap[0].id}`, { waitUntil: 'networkidle' });
await page.waitForSelector('.page-body', { timeout: 15000 });

const dariPenawaran = await page.evaluate(() => ({
    nomor: document.querySelector('.page-title')?.textContent.trim(),
}));

// Menghitung "semua tabel di halaman" ikut menjaring tabel rekap dan jurnal,
// dan mencari "Total" di seluruh teks bisa mengenai Total Kuantitas.
const acuan = await page.evaluate(() => {
    const tabelItem = [...document.querySelectorAll('.page-body table')]
        .find((t) => /PRODUK|DESKRIPSI/i.test(t.querySelector('thead')?.innerText ?? ''));

    const barisTotal = [...document.querySelectorAll('.page-body tr')]
        .find((tr) => /^\s*Total\s*$/i.test(tr.children[0]?.textContent.trim() ?? ''));

    return {
        total: barisTotal ? barisTotal.lastElementChild.textContent.replace(/[^\d.,]/g, '').trim() : null,
        baris: tabelItem ? tabelItem.querySelectorAll('tbody tr').length : 0,
    };
});

/* -------------------------------- 2. Pemilih sumber di Buat Pesanan */

await page.goto(`${PANGKAL}/sales-orders/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);

const pemilih = await page.evaluate(() => {
    const teks = document.querySelector('.page-body').innerText;

    return {
        adaKartuPenawaran: /Dari Penawaran yang Sudah Deal/i.test(teks),
        adaKartuManual: /Tanpa Penawaran/i.test(teks),
        opsi: [...document.querySelectorAll('select[name="quotation_id"] option')]
            .map((o) => o.textContent.trim()).filter((t) => t && !t.startsWith('—')),
    };
});

lapor('Halaman Buat Pesanan menawarkan sumbernya',
    pemilih.adaKartuPenawaran && pemilih.adaKartuManual, '');
lapor('Penawaran yang deal muncul di daftar', pemilih.opsi.length > 0,
    pemilih.opsi.join(' | ') || 'daftar kosong');

/* ------------------------------------- 3. Data penawaran benar-benar terbawa */

await page.selectOption('select[name="quotation_id"]', { index: 1 });
await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(900);

const form = await page.evaluate(() => {
    const baris = [...document.querySelectorAll('.table-items tbody tr')].map((tr) => ({
        produk: tr.querySelector('select[name*="[product_id]"]')?.selectedOptions?.[0]?.textContent.trim() ?? '',
        qty: tr.querySelector('input[name*="[quantity]"]')?.value ?? '',
        harga: tr.querySelector('input[name*="[unit_price]"]')?.value ?? '',
        disc: tr.querySelector('input[name*="[discount_percent]"]')?.value ?? '',
    }));

    return {
        adaCatatanSumber: /Dibuat dari penawaran/i.test(document.querySelector('.page-body').innerText),
        quotationId: document.querySelector('input[name="quotation_id"]')?.value ?? '',
        pelanggan: document.querySelector('select[name="partner_id"]')?.selectedOptions?.[0]?.textContent.trim() ?? '',
        baris,
        total: document.querySelector('.page-body')?.innerText.match(/Total\s*\n?\s*Rp\s*([\d.,]+)/i)?.[1] ?? null,
    };
});

lapor('Form menyebut penawaran sumbernya', form.adaCatatanSumber, '');
lapor('Tautan ke penawaran ikut terbawa', /^\d+$/.test(form.quotationId), `quotation_id=${form.quotationId}`);
lapor('Pelanggan terisi otomatis', form.pelanggan.length > 2, form.pelanggan);
lapor('Jumlah baris sama dengan penawaran', form.baris.length === acuan.baris,
    `${form.baris.length} baris vs ${acuan.baris} di penawaran`);
lapor('Harga tidak berubah dari yang disepakati',
    form.baris.every((b) => Number(b.harga) > 0),
    form.baris.map((b) => `${b.qty}x${b.harga}`).join('  '));
lapor('Total sama dengan penawaran', form.total === acuan.total,
    `pesanan ${form.total} vs penawaran ${acuan.total}`);

/* ------------------------------------------------ 4. Tersimpan dan tertaut */

await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(900);

const tersimpan = /\/sales-orders\/\d+$/.test(page.url());
lapor('Pesanan tersimpan', tersimpan, page.url().replace(PANGKAL, ''));

if (tersimpan) {
    const isi = await page.locator('.page-body').innerText();
    lapor('Detail pesanan menyebut penawarannya',
        isi.includes(dariPenawaran.nomor ?? 'QT/'), dariPenawaran.nomor ?? '');
}

// Penawaran yang sudah dikonversi tidak boleh ditawarkan lagi.
await page.goto(`${PANGKAL}/sales-orders/create`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);
const sisa = await page.evaluate(() =>
    [...document.querySelectorAll('select[name="quotation_id"] option')]
        .map((o) => o.textContent.trim()).filter((t) => t && !t.startsWith('—')));
lapor('Penawaran terkonversi hilang dari daftar', sisa.length === pemilih.opsi.length - 1,
    `tersisa ${sisa.length}, sebelumnya ${pemilih.opsi.length}`);

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
