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

**Sistem** — Pengguna, peran & izin (157 permission, 8 peran), pengaturan perusahaan/akuntansi/operasional, format nomor dokumen, log aktivitas.

## Harga Bertingkat & Riwayat Harga

Satu produk dapat memiliki **beberapa harga jual** (tingkat harga) dan **beberapa harga beli**
(satu per supplier). Harga dasar pada produk tetap dipakai sebagai cadangan bila keduanya
belum diisi, sehingga produk sederhana tetap bisa dijual tanpa pengaturan tambahan.

**Harga jual per tingkat** — maksimal 10 tingkat, diatur di *Data Master → Tingkat Harga*
(mis. Eceran, Grosir, Proyek, Reseller). Setiap customer dapat diberi tingkat harga bawaan;
saat SO atau faktur dibuat, harga seluruh baris terisi otomatis mengikuti tingkat tersebut.

**Harga beli per supplier** — daftar penawaran per produk berikut kode supplier, lead time,
dan minimum order. Satu supplier dapat ditandai **Utama**, dan yang termurah ditandai otomatis.
Saat PO dibuat, memilih supplier akan mengisi harga dari daftar tersebut.

**Urutan pemakaian harga**

| Dokumen | Urutan |
|---|---|
| PO / Faktur Pembelian | harga supplier terpilih → supplier utama → harga beli dasar |
| SO / Faktur Penjualan | tingkat harga customer → tingkat default → harga jual dasar |

**Riwayat harga** dicatat otomatis setiap harga benar-benar berubah — menyimpan harga lama,
harga baru, selisih nominal & persen, siapa yang mengubah, dan sumbernya (`manual`, `import`,
atau `receipt`). Semua penulisan harga melewati `PricingService`, jadi tidak ada jalur yang
bisa mengubah harga tanpa meninggalkan jejak.

> Saat penerimaan barang diposting, harga yang benar-benar dibayar ikut memperbarui daftar
> harga supplier dan tercatat di riwayat dengan sumber `receipt` — daftar harga mengikuti
> kenyataan tanpa perlu diketik ulang.

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
| Retur Penjualan | Persediaan *(barang baik)*, Kerugian Barang Rusak *(rusak)* | Harga Pokok Penjualan |
| — nota kreditnya | Retur Penjualan, PPN Keluaran | Piutang Usaha |
| Penyesuaian Stok | Persediaan *atau* Selisih Persediaan (Rugi) | Selisih Persediaan (Laba) *atau* Persediaan |
| Penyelesaian Produksi | Persediaan (barang jadi) | Persediaan (bahan), Overhead Dibebankan |
| Penggajian | Beban Gaji | Utang BPJS, Utang PPh 21, Kas/Bank |

HPP mengikuti **pergerakan fisik barang** (surat jalan), bukan faktur — sehingga buku besar
dan kartu stok selalu konsisten. Transfer antar gudang tidak menghasilkan jurnal karena
nilai persediaan total tidak berubah.

### Tipe Faktur: PPN, Non-PPN, dan Jasa

Setiap faktur penjualan memiliki tipe yang menentukan perlakuan pajaknya:

| Tipe | Perlakuan PPN | Judul cetakan |
|---|---|---|
| **PPN** | Tiap baris memakai tarif pajak produknya sendiri | Faktur Pajak |
| **Non-PPN** | Seluruh baris dipaksa 0% | Invoice |
| **Jasa** | Ber-PPN, ditambah potongan PPh 23 | Invoice Jasa |

Perlakuan PPN tiap produk diatur di *Data Master → Produk → Perlakuan PPN*, dan
terlihat sebagai badge pada daftar produk. Faktur bertipe PPN **menghormati**
setelan itu: produk yang memang bebas PPN tetap 0% walau fakturnya ber-PPN.
Sebaliknya faktur Non-PPN menolkan semua baris apa pun setelan produknya —
dipaksa di sisi server, bukan hanya di peramban.

**PPh 23 pada faktur jasa.** Customer memotong PPh 23 (bawaan 2%) dari nilai jasa
di luar PPN dan menyetorkannya sendiri, sehingga yang diterima lebih kecil dari
total faktur. Potongan itu bukan beban melainkan kredit pajak:

```
Dr Piutang Usaha        692.150     ← total dikurangi potongan
Dr Uang Muka PPh 23      12.700     ← 2% × 635.000, kredit pajak
Cr Pendapatan Penjualan            635.000
Cr PPN Keluaran                     69.850
```

Sisa tagihan yang dipantau sistem juga sudah dikurangi potongan tersebut, jadi
faktur tetap dianggap lunas ketika customer membayar sebesar nilai bersihnya.

### PPN & DPP Nilai Lain

Perhitungan pajak per baris:

```
DPP        = qty × harga × (1 − diskon%)
Dasar PPN  = DPP × rasio          ← rasio 1 bila DPP Nilai Lain tidak aktif
PPN        = Dasar PPN × tarif%
Total      = Σ DPP − diskon nota + biaya kirim + Σ PPN
```

Kolom **DPP** pada tabel item menampilkan nilai *sebelum* pajak, sehingga kolomnya
berjumlah sama dengan Subtotal di rekap — pajak ditambahkan sekali saja di bawah.

**DPP Nilai Lain** (PMK 131/2024) diaktifkan di *Pengaturan → PPN & DPP Nilai Lain*.
Sejak 2025 tarif PPN 12%, tetapi untuk barang/jasa umum dasar pengenaannya adalah
11/12 dari harga jual — hasilnya setara 11% dari harga jual:

| | Tarif | Dasar PPN | PPN atas DPP 4.316.000 |
|---|---|---|---|
| Tidak aktif | 11% | DPP penuh | 474.760 |
| Aktif, rasio 11/12 | 12% | 3.956.333 | 474.760 |

> Rasio disimpan sebagai **pembilang dan penyebut** (11 dan 12), bukan desimal,
> agar 11/12 tidak kehilangan presisi dan pajaknya tidak meleset beberapa sen.

Bawaannya **tidak aktif** supaya angka pada instalasi yang sudah berjalan tidak
berubah sendiri. Baris berpajak nol tidak terpengaruh rasio.

> **Rasio dan tarif harus berpasangan.** Rasio 11/12 di atas tarif 11% menghasilkan
> 10,08% — pajak kurang bayar tanpa gejala apa pun di layar. Karena itu sistem
> **menolak** mengaktifkan DPP Nilai Lain selama pajak default belum 12%, dan
> menampilkan peringatan merah di dashboard bila kombinasi salah itu sempat terjadi.

### Satu Faktur untuk Beberapa Surat Jalan

Pengiriman yang dilakukan beberapa kali dapat ditagih sekaligus dalam satu faktur
lewat **Faktur Penjualan → Dari Surat Jalan**: pilih customer, centang pengiriman
yang akan ditagih, lalu fakturnya tersusun otomatis.

- Baris dengan produk, harga, diskon, dan pajak yang sama **digabung** menjadi satu,
  sehingga faktur tetap ringkas walau menagih banyak pengiriman.
- Jumlah yang ditagih adalah yang dikirim **dikurangi yang sudah diretur**, jadi
  barang yang telanjur kembali tidak ikut tertagih.
- Tautannya disimpan pada `delivery_orders.sales_invoice_id`. Satu surat jalan hanya
  bisa ditagih satu faktur — inilah yang mencegah pengiriman tertagih dua kali,
  bahkan bila dua faktur dibuat bersamaan.
- Hanya surat jalan berstatus *posted* yang bisa ditagih; yang masih draft ditolak.
- Membatalkan atau menghapus faktur **melepas** tautannya, sehingga pengiriman
  tersebut dapat ditagih ulang oleh faktur pengganti.

### Retur Penjualan

Retur adalah dokumen tersendiri, bukan pembatalan surat jalan. Surat jalan asalnya tetap
berstatus *posted* sehingga riwayat pengiriman utuh, sementara retur mencatat peristiwa
barunya sendiri: tanggalnya sendiri, boleh sebagian, dan boleh berulang selama masih ada
sisa yang bisa dikembalikan.

- **Kondisi per baris** — barang *Baik* masuk kembali ke stok; barang *Rusak* tidak
  menambah stok dan langsung dibebankan sebagai kerugian.
- **Harga pokok** diambil dari pergerakan stok surat jalan asalnya, bukan rata-rata
  terbaru, sehingga barang kembali dengan biaya yang sama seperti saat keluar.
- **Nota kredit** opsional dan menempel pada sebuah faktur. Nilainya masuk ke
  `sales_invoices.credit_amount`, sehingga sisa tagihan berkurang tanpa dicatat
  seolah-olah sudah dibayar.
- Surat jalan yang sudah punya retur **tidak dapat dibatalkan**, karena stoknya akan
  terhitung dua kali. Batalkan returnya lebih dulu.

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
