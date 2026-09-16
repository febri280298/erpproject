/**
 * Menguji tampilan cetak seluruh dokumen di semua modul.
 *
 * Layout cetak dipakai bersama delapan dokumen, jadi satu perubahan gaya bisa
 * merusak tujuh dokumen lain tanpa terlihat. Yang diperiksa:
 *
 *  1. Halaman terbuka tanpa galat PHP/JS.
 *  2. Jumlah sel tiap baris sama dengan jumlah kolomnya — kolom bersyarat
 *     (DPP Nilai Lain, Disc) paling mudah membuat header dan badan tak sinkron.
 *  3. Isinya tidak melebihi lebar kertas A4; kolom yang meluber baru ketahuan
 *     setelah dicetak, saat sudah terlambat.
 *  4. Bagian wajib ada: kop, pihak, tabel item, tanda tangan.
 *
 * Jalankan: node tests/e2e/cetak-dokumen.mjs   (HEADED=1 untuk melihatnya)
 */
import { chromium } from 'playwright';

const PANGKAL = 'http://127.0.0.1:7001';

// Dokumen dicari dari daftarnya masing-masing; yang tabelnya kosong dilewati.
const DOKUMEN = [
    ['Faktur Penjualan', '/sales-invoices', true],
    ['Penawaran', '/quotations', true],
    ['Sales Order', '/sales-orders', true],
    ['Surat Jalan', '/delivery-orders', false],
    ['Retur Penjualan', '/sales-returns', true],
    ['Purchase Order', '/purchase-orders', true],
    ['Penerimaan Barang', '/goods-receipts', false],
    ['Faktur Pembelian', '/purchase-invoices', true],
];

const browser = await chromium.launch({ channel: 'chrome', headless: !process.env.HEADED });
const ctx = await browser.newContext({ viewport: { width: 1200, height: 1400 } });
const page = await ctx.newPage();

let gagal = 0;
let diuji = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`    ${nama.padEnd(34)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

for (const [nama, daftar, adaHarga] of DOKUMEN) {
    const res = await page.goto(PANGKAL + daftar, { waitUntil: 'networkidle' }).catch(() => null);

    if (!res || res.status() >= 400) {
        console.log(`\n  ${nama}: modul tidak aktif (${res?.status() ?? 'gagal'})`);
        continue;
    }

    await page.waitForTimeout(300);

    const tautan = await page.evaluate(() => {
        const a = document.querySelector('.page-body table tbody a[href*="/"]');

        return a ? a.getAttribute('href') : null;
    });

    if (!tautan) {
        console.log(`\n  ${nama}: belum ada dokumen`);
        continue;
    }

    console.log(`\n  ${nama}`);
    diuji++;

    const galat = [];
    const dengar = (e) => galat.push(e.message);
    page.on('pageerror', dengar);

    const cetak = await page.goto(`${PANGKAL}${new URL(tautan, PANGKAL).pathname}/print`,
        { waitUntil: 'networkidle' }).catch(() => null);

    lapor('Halaman cetak terbuka', !!cetak && cetak.status() === 200, `status ${cetak?.status() ?? 'gagal'}`);

    if (!cetak || cetak.status() !== 200) {
        page.off('pageerror', dengar);
        continue;
    }

    await page.waitForTimeout(500);
    lapor('Tanpa galat JavaScript', galat.length === 0, galat.join(' | '));

    const d = await page.evaluate(() => {
        const lembar = document.querySelector('.sheet');
        const tabel = document.querySelector('table.items');
        const kolom = tabel ? tabel.querySelectorAll('thead th').length : 0;

        const barisSalah = tabel
            ? [...tabel.querySelectorAll('tbody tr')]
                .map((tr, i) => (tr.children.length === kolom ? null : `baris ${i + 1}: ${tr.children.length} sel`))
                .filter(Boolean)
            : ['tabel item tidak ada'];

        // Apa pun yang lebih lebar dari lembarnya akan terpotong saat dicetak.
        const lebarLembar = lembar?.clientWidth ?? 0;
        const meluber = [...document.querySelectorAll('.sheet *')]
            .filter((el) => el.scrollWidth > lebarLembar + 2)
            .map((el) => el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).split(' ')[0] : ''));

        const teks = lembar?.innerText ?? '';

        return {
            kolom,
            barisSalah,
            meluber: [...new Set(meluber)],
            adaKop: !!document.querySelector('.company-name') && !!document.querySelector('.doc-type'),
            adaPihak: !!document.querySelector('.party-name'),
            /*
             * Bidang berdampingan diukur dari posisinya, bukan sekadar dihitung
             * ada berapa. Kop, identitas pihak, dan tanda tangan memakai tabel
             * agar dompdf bisa merendernya untuk unduhan PDF — dan kalau
             * tabelnya rusak, kolomnya menumpuk ke bawah sementara jumlahnya
             * tetap sama. Menghitung saja tidak akan menangkapnya.
             */
            sejajar: ['.letterhead', '.party', '.signatures'].flatMap((induk) =>
                // Tiap tabel diperiksa sendiri-sendiri: dokumen berharga punya
                // DUA tabel .party — identitas di atas, catatan & total di
                // bawah. Mengukurnya sekaligus akan selalu tampak menumpuk
                // karena keduanya memang berbeda tinggi.
                [...document.querySelectorAll(induk)].map((tabel, i) => {
                    const sel = [...tabel.querySelectorAll(':scope > tbody > tr > td')]
                        .map((el) => el.getBoundingClientRect());

                    return {
                        induk: induk.slice(1) + (i ? `#${i + 1}` : ''),
                        jumlah: sel.length,
                        sejajar: sel.length < 2 || sel.every(
                            (r, j) => Math.abs(r.top - sel[0].top) <= 2 && (j === 0 || r.left > sel[j - 1].left)
                        ),
                    };
                })
            ),
            adaTtd: document.querySelectorAll('.signatures > tbody > tr > td').length,
            adaTotal: /TOTAL/.test(teks),
            adaTerbilang: /Terbilang/i.test(teks),
        };
    });

    lapor('Sel baris sesuai jumlah kolom', d.barisSalah.length === 0,
        d.barisSalah.length ? d.barisSalah.join('; ') : `${d.kolom} kolom`);
    lapor('Tidak meluber dari kertas', d.meluber.length === 0,
        d.meluber.length ? d.meluber.join(', ') : 'pas dalam 186 mm');
    lapor('Kop & identitas pihak ada', d.adaKop && d.adaPihak, '');
    lapor('Blok tanda tangan ada', d.adaTtd >= 1, `${d.adaTtd} kolom`);

    const menumpuk = d.sejajar.filter((b) => ! b.sejajar).map((b) => b.induk);
    lapor('Bidang berdampingan sejajar', menumpuk.length === 0,
        menumpuk.length
            ? menumpuk.join(', ') + ' menumpuk ke bawah'
            : d.sejajar.map((b) => `${b.induk} ${b.jumlah}`).join(' · '));

    if (adaHarga) {
        lapor('Blok total & terbilang ada', d.adaTotal && d.adaTerbilang, '');
    }

    await page.screenshot({
        path: `tests/e2e/hasil/cetak-${daftar.replace(/\//g, '')}.png`,
        fullPage: true,
    });

    page.off('pageerror', dengar);
}

await browser.close();
console.log(`\n${diuji} dokumen diuji. ${gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`}`);
process.exit(gagal === 0 ? 0 : 1);
