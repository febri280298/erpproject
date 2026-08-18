/**
 * Uji ujung-ke-ujung lewat antarmuka sungguhan.
 *
 * Menjalankan Chrome yang terpasang di komputer ini (bukan Chromium bawaan
 * Playwright) agar tampilannya persis seperti yang dilihat pengguna. Jalankan
 * dengan HEADED=1 untuk melihat jendelanya bergerak:
 *
 *   HEADED=1 node tests/e2e/faktur-jasa.mjs
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:7001';
const HASIL = 'tests/e2e/hasil';
const headed = process.env.HEADED === '1';

mkdirSync(HASIL, { recursive: true });

let langkah = 0;
const lolos = [];
const gagal = [];

async function tangkap(page, nama) {
  langkah += 1;
  const berkas = `${HASIL}/${String(langkah).padStart(2, '0')}-${nama}.png`;
  await page.screenshot({ path: berkas, fullPage: true });
  return berkas;
}

function periksa(nama, benar, catatan = '') {
  (benar ? lolos : gagal).push(nama);
  console.log(`  ${benar ? 'LOLOS' : 'GAGAL'}  ${nama}${catatan ? ` — ${catatan}` : ''}`);
}

const browser = await chromium.launch({
  channel: 'chrome',        // pakai Chrome yang sudah terpasang
  headless: !headed,
  slowMo: headed ? 350 : 0, // diperlambat supaya gerakannya terlihat
});

const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  recordVideo: headed ? undefined : { dir: `${HASIL}/video`, size: { width: 1440, height: 900 } },
});

const page = await context.newPage();
const galat = [];
page.on('console', (m) => m.type() === 'error' && galat.push(m.text()));
page.on('pageerror', (e) => galat.push(e.message));

try {
  console.log('\n=== 1. Masuk ke sistem ===');
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'admin@bonecomtricom.com');
  await page.fill('input[name="password"]', 'password');
  await tangkap(page, 'halaman-login');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
  periksa('Berhasil masuk dan sampai di dashboard', page.url().includes('/dashboard'));
  await tangkap(page, 'dashboard');

  console.log('\n=== 2. Buka form faktur penjualan ===');
  await page.goto(`${BASE}/sales-invoices/create`);
  await page.waitForSelector('select[name="invoice_type"]');
  periksa('Pemilih Tipe Faktur tampil', await page.isVisible('select[name="invoice_type"]'));
  periksa('Input PPh 23 tampil', await page.isVisible('input[name="wht_rate"]'));

  console.log('\n=== 3. Isi faktur bertipe Jasa ===');
  await page.selectOption('select[name="invoice_type"]', 'jasa');
  await page.selectOption('select[name="partner_id"]', { index: 1 });
  await page.fill('input[name="wht_rate"]', '2');

  // Baris pertama: pilih produk lalu isi qty dan harga.
  await page.selectOption('select[name="items[0][product_id]"]', { index: 1 });
  await page.fill('input[name="items[0][quantity]"]', '10');
  await page.fill('input[name="items[0][unit_price]"]', '63500');
  await page.click('h3.card-title'); // lepas fokus agar Alpine menghitung ulang

  await page.waitForTimeout(400);
  await tangkap(page, 'form-faktur-jasa-terisi');

  // Baca angka yang ditampilkan Alpine di panel rekap.
  const teksRekap = await page.locator('.card-body.border-top table').last().innerText();
  console.log('\n  --- panel rekap di layar ---');
  teksRekap.split('\n').filter(Boolean).forEach((b) => console.log(`    ${b.replace(/\s+/g, ' ')}`));

  periksa('Baris PPh 23 muncul di rekap', /PPh 23/.test(teksRekap));
  periksa('Baris "Dibayar Customer" muncul', /Dibayar Customer/.test(teksRekap));

  console.log('\n=== 4. Simpan faktur ===');
  await page.click('button[type="submit"]:has-text("Simpan")');
  await page.waitForURL('**/sales-invoices/*');
  const nomor = (await page.locator('h2.page-title').innerText()).trim();
  periksa('Faktur tersimpan', /INV\//.test(nomor), nomor);
  await tangkap(page, 'faktur-tersimpan');

  const detail = await page.locator('.card', { hasText: 'Informasi Dokumen' }).first().innerText();
  periksa('Tipe faktur tercatat "Jasa"', /Jasa/.test(detail));
  periksa('PPh 23 tampil di detail', /PPh 23/.test(detail));

  console.log('\n=== 5. Posting faktur ===');
  page.once('dialog', (d) => d.accept());
  await page.click('button:has-text("Posting")');
  await page.waitForLoadState('networkidle');
  periksa('Faktur berhasil diposting', await page.isVisible('text=diposting'));
  await tangkap(page, 'faktur-diposting');

  console.log('\n=== 6. Uji faktur Non-PPN ===');
  await page.goto(`${BASE}/sales-invoices/create`);
  await page.selectOption('select[name="invoice_type"]', 'non_ppn');
  await page.selectOption('select[name="partner_id"]', { index: 1 });
  await page.selectOption('select[name="items[0][product_id]"]', { index: 1 });
  await page.fill('input[name="items[0][quantity]"]', '10');
  await page.fill('input[name="items[0][unit_price]"]', '63500');
  await page.click('h3.card-title');
  await page.waitForTimeout(400);

  const pajakBaris = await page.inputValue('input[name="items[0][tax_rate]"]');
  periksa('Non-PPN menolkan pajak baris', Number(pajakBaris) === 0, `tarif terbaca = ${pajakBaris}`);
  await tangkap(page, 'form-faktur-non-ppn');

  periksa('Tidak ada galat JavaScript di konsol', galat.length === 0, galat[0] ?? '');

  console.log("=== 7. Bersihkan faktur uji ===");
  await page.goto(`${BASE}/sales-invoices`);
  const barisUji = page.locator('tr', { hasText: nomor });
  if (await barisUji.count()) {
    await barisUji.first().locator('a').first().click();
    await page.waitForLoadState('networkidle');
    page.once('dialog', (d) => d.accept());
    await page.click('button:has-text("Batalkan")');
    await page.waitForLoadState('networkidle');
    periksa('Faktur uji dibatalkan (jurnal dibalik)', await page.isVisible('text=dibatalkan'));
  }
} catch (e) {
  gagal.push(`pengecualian: ${e.message}`);
  console.log(`\n  GAGAL karena pengecualian: ${e.message}`);
  await tangkap(page, 'saat-gagal').catch(() => {});
} finally {
  console.log(`\n=== RINGKASAN ===\n  lolos: ${lolos.length}   gagal: ${gagal.length}`);
  if (gagal.length) gagal.forEach((g) => console.log(`  - ${g}`));
  console.log(`  tangkapan layar: ${HASIL}/`);

  await context.close();
  await browser.close();
  process.exit(gagal.length ? 1 : 0);
}
