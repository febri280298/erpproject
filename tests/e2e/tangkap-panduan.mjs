/**
 * Mengambil gambar layar untuk panduan trial, lalu mengubahnya menjadi
 * data URI. Artifact memblokir permintaan ke host luar, jadi gambarnya harus
 * tertanam di dalam berkas HTML.
 */
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'node:fs';

const BASE = 'http://127.0.0.1:7001';
const OUT = 'tests/e2e/hasil';
mkdirSync(OUT, { recursive: true });

const halaman = [
  ['login',      '/login',                            'Halaman masuk dengan daftar akun uji coba'],
  ['modul',      '/settings#modules',                 'Pengaturan modul: menyalakan & mematikan area sistem'],
  ['produk',     '/products/create',                  'Form produk: perlakuan PPN, harga bertingkat, harga per supplier'],
  ['mitra',      '/partners/create',                  'Form customer & supplier dengan tingkat harganya'],
  ['po',         '/purchase-orders/create',           'Form pesanan pembelian'],
  ['penawaran',  '/quotations/create',                'Form penawaran harga'],
  ['so',         '/sales-orders/create',              'Form pesanan penjualan'],
  ['suratjalan', '/delivery-orders/create',           'Dua jalur surat jalan: dari SO atau tanpa SO'],
  ['faktur',     '/sales-invoices/create',            'Form faktur dengan pemilih tipe dan PPh 23'],
  ['coa',        '/accounts',                         'Bagan akun standar Indonesia'],
];

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1400, height: 880 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

await page.goto(`${BASE}/login`);
const hasil = {};

// Tangkapan halaman login diambil sebelum masuk.
await page.waitForTimeout(500);
hasil.login = (await page.screenshot({ type: 'jpeg', quality: 68 })).toString('base64');
console.log('  login                 diambil');

await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
await page.fill('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');

for (const [kunci, jalur] of halaman.slice(1)) {
  await page.goto(BASE + jalur, { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);
  hasil[kunci] = (await page.screenshot({ type: 'jpeg', quality: 68 })).toString('base64');
  console.log(`  ${kunci.padEnd(21)} diambil`);
}

const keterangan = Object.fromEntries(halaman.map(([k, , t]) => [k, t]));
writeFileSync(`${OUT}/gambar.json`, JSON.stringify({ gambar: hasil, keterangan }));

const totalKb = Object.values(hasil).reduce((n, b) => n + b.length, 0) / 1024;
console.log(`\n${Object.keys(hasil).length} gambar, total base64 ${totalKb.toFixed(0)} KB`);

await browser.close();
