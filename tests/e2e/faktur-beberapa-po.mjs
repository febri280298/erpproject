/**
 * Menguji satu faktur pembelian yang menagih beberapa pesanan sekaligus.
 *
 * Yang dibuktikan bukan sekadar layar pemilihnya muncul, melainkan:
 *  1. baris dari beberapa PO benar-benar tergabung, dan produk yang sama
 *     dengan harga sama menjadi satu baris — bukan dua;
 *  2. nilai fakturnya sama dengan jumlah nilai pesanan yang dicentang;
 *  3. tautannya tersimpan ke SEMUA pesanan, bukan hanya yang pertama;
 *  4. setelah diposting, kuantitas tertagih terbagi ke pesanan masing-masing
 *     dan tidak menumpuk di satu pesanan.
 *
 * Uji ini MEMBUAT dokumen: dua pesanan pembelian dan satu faktur.
 *
 * Jalankan: node tests/e2e/faktur-beberapa-po.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';
const SUPPLIER = 'GARUDAH';

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1700, height: 1000 } });
const page = await ctx.newPage();

const galat = [];
page.on('pageerror', (e) => galat.push(e.message));
page.on('dialog', (d) => d.accept());

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(48)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

const angka = (t) => Number(String(t ?? '').replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.'));

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
    await page.waitForTimeout(420);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);

    return true;
};

/** Membuat PO lalu menyetujuinya, supaya bisa ditagih. */
const buatPo = async (barisProduk) => {
    await page.goto(`${PANGKAL}/purchase-orders/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await pilih('partner_id', SUPPLIER);

    for (let i = 0; i < barisProduk.length; i++) {
        if (i > 0) {
            await page.click('button:has-text("Tambah Baris")');
            await page.waitForTimeout(450);
        }

        await pilih(`items[${i}][product_id]`, barisProduk[i][0]);
        await page.fill(`input[name="items[${i}][quantity]"]`, String(barisProduk[i][1]));
        await page.waitForTimeout(300);
    }

    await page.click('.page-body form button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);

    const nomor = await page.locator('.page-title').innerText();

    // Disetujui lewat pengiriman form langsung: butir dropdown sulit diklik
    // Playwright, dan itu kendala otomasi, bukan cacat tampilan.
    await page.evaluate(() => {
        const f = [...document.querySelectorAll('form[action*="/approve"]')][0];

        f?.requestSubmit();
    }).catch(() => {});

    await page.waitForFunction(
        () => /Disetujui/.test(document.querySelector('.page-body')?.innerText ?? ''),
        null, { timeout: 20000 },
    ).catch(() => {});

    return nomor.trim();
};

/* --------------------------------------------- 1. Siapkan dua pesanan */

// Semen muncul di KEDUA pesanan dengan harga sama -> harus tergabung jadi satu
// baris. Baut hanya di pesanan kedua -> tetap baris sendiri.
const po1 = await buatPo([['Semen', 40]]);
const po2 = await buatPo([['Semen', 60], ['Baut Mur', 100]]);

lapor('Dua pesanan pembelian tersedia', /^PO\//.test(po1) && /^PO\//.test(po2), `${po1} & ${po2}`);

/* ------------------------------------------ 2. Layar pemilih pesanan */

await page.goto(`${PANGKAL}/purchase-invoices/select-orders`, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);
await pilih('partner_id', SUPPLIER);
await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(700);

const daftar = await page.evaluate(() => [...document.querySelectorAll('input[name="purchase_order_ids[]"]')]
    .map((c) => {
        const tr = c.closest('tr');

        return { id: c.value, no: tr.children[1].textContent.trim(), nilai: tr.children[4].textContent.trim() };
    }));

lapor('Pesanan supplier tampil untuk dicentang', daftar.length >= 2,
    daftar.map((d) => `${d.no} ${d.nilai}`).join(' | '));

const targetnya = daftar.filter((d) => [po1, po2].includes(d.no));
lapor('Kedua pesanan yang dibuat ada di daftar', targetnya.length === 2, '');

const totalPesanan = targetnya.reduce((n, d) => n + angka(d.nilai), 0);

/* ----------------------------------- 3. Centang keduanya jadi satu faktur */

for (const t of targetnya) {
    await page.check(`input[name="purchase_order_ids[]"][value="${t.id}"]`);
}

await page.click('button:has-text("Lanjut ke Faktur")');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(900);

const form = await page.evaluate(() => {
    const baris = [...document.querySelectorAll('.table-items tbody tr')].map((tr) => ({
        produk: tr.querySelector('select[name*="[product_id]"]')?.selectedOptions?.[0]?.textContent.trim() ?? '',
        qty: tr.querySelector('input[name*="[quantity]"]')?.value ?? '',
        harga: tr.querySelector('input[name*="[unit_price]"]')?.value ?? '',
    }));

    return {
        catatanSumber: /Menagih \d+ pesanan sekaligus/.test(document.querySelector('.page-body').innerText),
        idTerkirim: [...document.querySelectorAll('input[name="purchase_order_ids[]"]')].map((i) => i.value),
        baris,
        // Kolom "Nilai Belum Ditagih" pada daftar pesanan adalah DPP tanpa
        // pajak, jadi yang dibandingkan Subtotal — bukan Total yang sudah
        // memuat PPN.
        subtotal: [...document.querySelectorAll('.page-body tr')]
            .find((tr) => /Subtotal/i.test(tr.children[0]?.textContent.trim() ?? ''))
            ?.lastElementChild.textContent.replace(/[^\d.,]/g, '').trim() ?? null,
    };
});

lapor('Form menyebut jumlah pesanan yang ditagih', form.catatanSumber, '');
lapor('Kedua pesanan ikut terkirim ke form', form.idTerkirim.length === 2, form.idTerkirim.join(','));

// Semen 40 + 60 harus menjadi SATU baris berisi 100.
const semen = form.baris.filter((b) => /Semen/i.test(b.produk));
lapor('Produk sama berharga sama digabung jadi satu baris', semen.length === 1,
    semen.map((b) => `${b.produk} qty ${b.qty}`).join(' | '));
lapor('Kuantitas gabungan benar', semen.length === 1 && Number(semen[0].qty) === 100,
    semen[0] ? `qty ${semen[0].qty} (harap 100)` : '-');
lapor('Produk yang hanya ada di satu pesanan tetap terpisah',
    form.baris.some((b) => /Baut/i.test(b.produk)), form.baris.map((b) => b.produk.split('—').pop().trim()).join(' | '));
lapor('DPP faktur sama dengan jumlah nilai pesanan',
    Math.abs(angka(form.subtotal) - totalPesanan) < 1,
    `faktur ${form.subtotal} vs pesanan ${totalPesanan.toLocaleString('id-ID')}`);

/* ------------------------------------------- 4. Simpan, posting, alokasi */

await page.click('.page-body form button[type="submit"]');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(900);

const tersimpan = /\/purchase-invoices\/\d+$/.test(page.url());
lapor('Faktur tersimpan', tersimpan, page.url().replace(PANGKAL, ''));

if (tersimpan) {
    const isi = await page.locator('.page-body').innerText();
    lapor('Detail faktur menyebut kedua pesanan',
        isi.includes(po1) && isi.includes(po2), `${po1} & ${po2}`);

    // Posting membagi kuantitas tertagih ke pesanan masing-masing.
    await page.evaluate(() => {
        const f = [...document.querySelectorAll('form[action*="/post"]')][0];

        f?.requestSubmit();
    }).catch(() => {});

    await page.waitForFunction(
        () => /Diposting/.test(document.querySelector('.page-body')?.innerText ?? ''),
        null, { timeout: 20000 },
    ).catch(() => {});

    // Menuntut label yang PASTI, bukan sekadar "bukan Draft". Versi sebelumnya
    // lolos walau statusnya tidak terbaca sama sekali — asersi yang tidak
    // pernah bisa gagal tidak membuktikan apa pun.
    const status = await page.evaluate(() =>
        document.querySelector('.page-body').innerText.match(/Draft|Diposting|Lunas|Sebagian|Batal/)?.[0] ?? '?');
    lapor('Faktur berhasil diposting', status === 'Diposting', `status: ${status}`);

    /*
     * Bukti alokasi: kedua pesanan harus habis tertagih, bukan menumpuk di satu
     * pesanan. Diperiksa lewat layar pemilih — pesanan yang sisanya nol tidak
     * lagi ditawarkan di sana, dan itu justru pemeriksaan yang paling dekat
     * dengan akibat nyatanya bagi pengguna.
     */
    await page.goto(`${PANGKAL}/purchase-invoices/select-orders`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await pilih('partner_id', SUPPLIER);
    await page.click('.page-body form button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(700);

    const tersisa = await page.evaluate(() => [...document.querySelectorAll('input[name="purchase_order_ids[]"]')]
        .map((c) => c.closest('tr').children[1].textContent.trim()));

    lapor('Kedua pesanan habis tertagih',
        ! tersisa.includes(po1) && ! tersisa.includes(po2),
        `masih ditawarkan: ${tersisa.join(', ') || 'tidak ada'}`);
}

console.log(galat.length ? `\nGALAT JS: ${galat.join(' | ')}` : '\nTidak ada galat JavaScript.');
gagal += galat.length ? 1 : 0;

await browser.close();
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
