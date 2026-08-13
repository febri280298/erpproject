# ERP Peak

Sistem ERP berbasis **Laravel 12 + MySQL** dengan antarmuka **[Tabler](https://github.com/tabler/tabler)** (`@tabler/core` v1.4).
Mencakup alur bisnis penuh: pembelian, penjualan, persediaan, produksi, akuntansi double-entry, dan SDM.

---

## Daftar Isi

- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
- [Akun Demo](#akun-demo)
- [Modul](#modul)
- [Alur Transaksi & Jurnal](#alur-transaksi--jurnal)
- [Arsitektur](#arsitektur)
- [Konfigurasi](#konfigurasi)
- [Pengembangan](#pengembangan)

---

## Kebutuhan Sistem

| Komponen | Versi |
|---|---|
| PHP | ≥ 8.2 (ekstensi: `pdo_mysql`, `mbstring`, `gd`, `intl`, `bcmath`, `zip`) |
| MySQL / MariaDB | ≥ 8.0 / ≥ 10.4 |
| Node.js | ≥ 20 |
| Composer | 2.x |

## Instalasi

```bash
composer install
npm install

cp .env.example .env          # sesuaikan DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate

php artisan migrate --seed    # skema + data master + transaksi demo
php artisan storage:link      # agar upload logo/foto tampil
npm run build

php artisan serve
```

Buka `http://127.0.0.1:8000`.

> Seeder transaksi demo hanya berjalan pada `APP_ENV=local`. Untuk instalasi produksi,
> set `APP_ENV=production` sebelum `migrate --seed` agar database berisi master data saja.

## Akun Demo

Semua akun memakai kata sandi `password` — **ganti sebelum dipakai produksi**.

| Email | Peran | Akses |
|---|---|---|
| `admin@bonecomtricom.com` | Super Admin | Seluruh modul |
| `manajer@bonecomtricom.com` | Manajer | Persetujuan, laporan |
| `akuntansi@bonecomtricom.com` | Akuntansi | Faktur, pembayaran, jurnal, laporan keuangan |
| `pembelian@bonecomtricom.com` | Pembelian | PR, PO, mitra |
| `penjualan@bonecomtricom.com` | Penjualan | Penawaran, SO, pelanggan |
| `gudang@bonecomtricom.com` | Gudang | Penerimaan, surat jalan, transfer, opname, produksi |
| `hrd@bonecomtricom.com` | HRD | Karyawan, absensi, cuti, penggajian |

## Modul

Modul dapat dinyalakan/dimatikan dari **Pengaturan → Modul** tanpa deploy ulang.
Modul yang dimatikan menunya hilang dan alamatnya membalas 404 — **data tidak dihapus**,
jadi menyalakannya kembali memulihkan tampilan seperti semula.

Nilai bawaan instalasi ini (lihat `config/erp.php` → `modules`):

| Modul | Bawaan | Keterangan |
|---|:--:|---|
| Master Data | selalu aktif | Inti — tidak dapat dimatikan |
| Persediaan | selalu aktif | Inti — tidak dapat dimatikan |
| Pembelian | aktif | PO, penerimaan barang, faktur, pembayaran pemasok |
| Penjualan | aktif | SO, surat jalan, faktur pelanggan, penerimaan pembayaran |
| Laporan | aktif | Laporan operasional |
| Permintaan Pembelian | mati | Alur langsung ke PO tanpa tahap pengajuan |
| Penawaran | mati | Alur langsung ke SO tanpa tahap penawaran |
| Produksi | mati | BOM & perintah produksi |
| Akuntansi | mati | COA, jurnal, laporan keuangan |
| SDM | mati | Karyawan, absensi, cuti, penggajian |

> **Akuntansi dimatikan bukan berarti pembukuan berhenti.** Jurnal tetap ditulis di
> belakang layar setiap kali dokumen diposting, sehingga bila modul Akuntansi
> dinyalakan lagi, buku besar sudah utuh sejak transaksi pertama.

Sub-modul mengikuti induknya: mematikan **Pembelian** otomatis menonaktifkan
**Permintaan Pembelian**, dan mematikan **Penjualan** menonaktifkan **Penawaran**.

**Master Data** — Produk (barang & jasa), kategori, satuan, pajak, termin pembayaran, gudang, mitra bisnis (pelanggan/pemasok/keduanya).

**Pembelian** — Permintaan Pembelian (PR) → Pesanan Pembelian (PO) → Penerimaan Barang (GRN) → Faktur Pembelian → Pembayaran Pemasok. PR dan PO memiliki alur persetujuan; PO melacak sisa penerimaan per baris.

**Penjualan** — Penawaran → Pesanan Penjualan (SO) → Surat Jalan → Faktur Penjualan → Penerimaan Pembayaran. Penawaran yang diterima dapat dikonversi menjadi SO satu klik.

**Persediaan** — Stok per gudang, kartu stok, buku pergerakan stok, transfer antar gudang, penyesuaian/stok opname, valuasi. Penilaian memakai **rata-rata bergerak (moving average)**.

**Produksi** — Bill of Materials dengan toleransi waste, perintah produksi dengan kebutuhan bahan otomatis, penyelesaian produksi yang mengkonsumsi bahan dan menerima barang jadi berikut perhitungan HPP.

**Akuntansi** — Bagan akun (54 akun standar Indonesia), jurnal umum manual & otomatis, buku besar, neraca saldo, laba rugi, neraca, periode fiskal yang dapat dikunci.

**SDM** — Karyawan, departemen, jabatan, absensi harian & rekap bulanan, pengajuan cuti dengan persetujuan, penggajian bulanan lengkap dengan slip gaji dan jurnal.

**Laporan** — Penjualan, pembelian, persediaan, umur piutang, umur utang, produk terlaris.

**Sistem** — Pengguna, peran & izin (148 permission, 8 peran), pengaturan perusahaan/akuntansi/operasional, format nomor dokumen, log aktivitas.

## Alur Transaksi & Jurnal

Setiap dokumen dibuat sebagai **draft** dan baru berdampak pada stok/buku besar ketika **diposting**.
Pembatalan tidak menghapus data — sistem menulis pergerakan stok balik dan jurnal pembalik.

| Dokumen | Debit | Kredit |
|---|---|---|
| Penerimaan Barang | Persediaan | Penerimaan Belum Ditagih (GRNI) |
| Faktur Pembelian | GRNI / Persediaan, PPN Masukan, Beban Angkut | Utang Usaha, Potongan Pembelian |
| Pembayaran Pemasok | Utang Usaha | Kas / Bank |
| Surat Jalan | Harga Pokok Penjualan | Persediaan |
| Faktur Penjualan | Piutang Usaha, Potongan Penjualan | Pendapatan, PPN Keluaran, Pendapatan Angkut |
| Penerimaan Pembayaran | Kas / Bank | Piutang Usaha |
| Penyesuaian Stok | Persediaan *atau* Selisih Persediaan (Rugi) | Selisih Persediaan (Laba) *atau* Persediaan |
| Penyelesaian Produksi | Persediaan (barang jadi) | Persediaan (bahan), Overhead Dibebankan |
| Penggajian | Beban Gaji | Utang BPJS, Utang PPh 21, Kas/Bank |

HPP mengikuti **pergerakan fisik barang** (surat jalan), bukan faktur — sehingga buku besar
dan kartu stok selalu konsisten. Transfer antar gudang tidak menghasilkan jurnal karena
nilai persediaan total tidak berubah.

## Arsitektur

```
app/
├── Http/Controllers/
│   ├── Concerns/
│   │   ├── SimpleMasterController.php      # CRUD deklaratif untuk tabel referensi
│   │   ├── LineItemDocumentController.php  # CRUD dokumen berbaris (PO/SO/Faktur)
│   │   └── PaymentDocumentController.php   # Alokasi pembayaran ke faktur
│   ├── Master/ Purchasing/ Sales/ Inventory/ Manufacturing/ Accounting/ Hr/ System/
├── Models/
│   ├── Concerns/                           # LogsActivity, HasDocumentStatus, CalculatesTotals, Searchable
│   └── Master/ Purchasing/ Sales/ Inventory/ Manufacturing/ Accounting/ Hr/
└── Services/
    ├── InventoryService.php                # Satu-satunya pintu perubahan stok (moving average)
    ├── JournalService.php                  # Menolak jurnal timpang & periode tertutup
    ├── AccountMap.php                      # settings → kode akun → id akun
    ├── DocumentNumberService.php           # Penomoran anti-bentrok (lockForUpdate)
    ├── LineItemCalculator.php              # Kembaran server-side dari doc-items.js
    └── Posting/                            # Purchasing, Sales, Inventory, Manufacturing, Payroll
```

**Prinsip yang dipegang**

- `config/erp.php` adalah sumber tunggal untuk permission, peran, **dan** menu sidebar —
  navigasi tidak akan pernah menampilkan menu yang tidak boleh diakses.
- Perhitungan baris dokumen ada di dua tempat yang sengaja dikembarkan:
  `resources/js/doc-items.js` (pratinjau) dan `LineItemCalculator` (yang disimpan).
- `stock_movements` bersifat *append-only*; `stocks.quantity` selalu sama dengan
  jumlah bersih ledger-nya.
- Semua posting berjalan di dalam transaksi database dan mengunci baris stok
  (`lockForUpdate`) agar aman terhadap request bersamaan.

## Konfigurasi

Pengaturan dikelola lewat UI (**Pengaturan → Sistem**) dan disimpan di tabel `settings`:

- **Profil perusahaan** — nama, alamat, NPWP, logo (dipakai kop dokumen cetak).
- **Pemetaan akun** — 21 pemetaan yang menentukan akun tiap jurnal otomatis.
  Bagan akun boleh dinomori ulang tanpa mengubah kode.
- **Operasional** — izin stok negatif, jam kerja, persentase BPJS/PPh 21, tarif lembur.
- **Format nomor** — prefix, reset periode (bulanan/tahunan), jumlah digit per modul.

Variabel `.env` tambahan:

```dotenv
ERP_COMPANY_NAME="PT Bonecom Tricom"
ERP_CURRENCY=IDR
ERP_TAX_RATE=11
ERP_FISCAL_YEAR_START=01-01
```

## Pengembangan

```bash
npm run dev          # Vite dev server + hot reload
./vendor/bin/pint    # Perbaikan gaya kode
php artisan test     # Test suite
```

Aset frontend dibangun dari SCSS Tabler (`resources/scss/app.scss`), sehingga variabel
tema dapat ditimpa sebelum `@import '@tabler/core/scss/tabler'`. Font ikon Tabler disalin
ke `public/fonts` oleh `npm run copy:fonts`, yang dipanggil otomatis oleh `npm run build`.

---

Antarmuka menggunakan [Tabler](https://tabler.io) (MIT License).
