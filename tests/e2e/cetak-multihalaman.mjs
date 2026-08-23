/**
 * Membuktikan kepala tabel terulang di setiap halaman cetak.
 *
 * Tidak cukup memeriksa CSS-nya: `display: table-header-group` hanya berarti
 * peramban BOLEH mengulang, dan hasilnya baru terlihat setelah dokumen benar
 * benar dipaginasi. Jadi halamannya dicetak ke PDF sungguhan lalu teksnya
 * dibaca per halaman dengan pdftotext.
 *
 * Dipanggil dengan id dokumen yang barisnya cukup panjang untuk melewati satu
 * halaman:  node tests/e2e/cetak-multihalaman.mjs /quotations/3/print
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync, unlinkSync } from 'node:fs';

const PANGKAL = 'http://127.0.0.1:7001';
const JALUR = process.argv[2] ?? '/quotations/3/print';
const PDF = 'tests/e2e/hasil/cetak-multihalaman.pdf';

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext();
const page = await ctx.newPage();

await page.goto(`${PANGKAL}/login`);
await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('form button[type="submit"]');
await page.waitForURL('**/dashboard');

await page.goto(PANGKAL + JALUR, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);

const jumlahBaris = await page.locator('.items tbody tr').count();

// Ukuran dan margin diambil dari @page pada layout cetak, bukan dari bawaan
// Playwright, supaya hasilnya sama dengan yang keluar dari printer.
await page.pdf({
    path: PDF,
    format: 'A4',
    printBackground: true,
    margin: { top: '12mm', right: '12mm', bottom: '14mm', left: '12mm' },
});

await browser.close();

const teks = execFileSync('pdftotext', ['-layout', PDF, '-'], { encoding: 'latin1' });

// pdftotext memisahkan halaman dengan form feed (\f).
const halaman = teks.split('\f').filter((h) => h.trim() !== '');

// Judul kolom yang harus muncul lagi di tiap halaman berisi tabel.
const KEPALA = 'DESKRIPSI';

const berkepala = halaman.map((h, i) => ({ ke: i + 1, ada: h.includes(KEPALA) }));

// Halaman yang memuat baris item dikenali dari kode produknya.
const berbaris = halaman.map((h, i) => ({
    ke: i + 1,
    ada: /\b[A-Z]{3,4}-\d{3}\b/.test(h),
}));

let gagal = 0;
const lapor = (nama, hasil, ket = '') => {
    hasil || gagal++;
    console.log(`  ${nama.padEnd(44)} ${hasil ? 'LOLOS' : 'GAGAL'}  ${ket}`);
};

console.log(`Dokumen ${JALUR} — ${jumlahBaris} baris, ${halaman.length} halaman PDF\n`);

lapor('Dokumen memang lebih dari satu halaman', halaman.length >= 2, `${halaman.length} halaman`);

const halamanBerbaris = berbaris.filter((h) => h.ada).map((h) => h.ke);
const tanpaKepala = halamanBerbaris.filter((ke) => ! berkepala[ke - 1].ada);

lapor('Setiap halaman berisi baris punya kepala tabel', tanpaKepala.length === 0,
    tanpaKepala.length
        ? `halaman tanpa kepala: ${tanpaKepala.join(', ')}`
        : `halaman berbaris: ${halamanBerbaris.join(', ')} — semuanya berkepala`);

// Total hanya boleh muncul sekali, di halaman terakhir.
const halamanTotal = halaman.map((h, i) => (/\bTOTAL\b/.test(h) ? i + 1 : null)).filter(Boolean);
lapor('Blok total hanya di halaman terakhir',
    halamanTotal.length === 1 && halamanTotal[0] === halaman.length,
    `muncul di halaman ${halamanTotal.join(', ') || '—'} dari ${halaman.length}`);

console.log(`\nBerkas PDF: ${PDF}`);
console.log(gagal === 0 ? 'Semua pemeriksaan lolos.' : `${gagal} pemeriksaan gagal.`);
process.exit(gagal === 0 ? 0 : 1);
