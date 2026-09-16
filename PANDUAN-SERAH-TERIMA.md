# Serah Terima ERP Peak ke Customer

Panduan ini untuk memasang sistem di tempat **customer** lalu menyerahkannya.
Berbeda dengan [PANDUAN-INSTALASI.md](PANDUAN-INSTALASI.md), yang dipakai saat
memindahkan sistem antar komputer sendiri.

Bedanya satu hal, dan penting:

> **Pemasangan bawaan menyiapkan komputer untuk membangun sistem, bukan untuk
> dipakai berusaha.**

Bila `pasang-erp.bat` langsung diklik apa adanya di komputer customer, hasilnya:

| Yang terjadi | Akibatnya bagi customer |
|---|---|
| Basis data ikut terisi **transaksi contoh** — PO, penerimaan, faktur, surat jalan, beserta jurnalnya | Laporan dan saldo awal salah sejak hari pertama; harus dibersihkan satu per satu |
| Setiap galat menampilkan **isi berkas `.env`** ke layar | Sandi basis data terbaca siapa pun yang kebetulan membuka halaman error |
| Ada **enam akun** yang semuanya bersandi `password` | Siapa pun yang tahu polanya bisa masuk sebagai Super Admin |

Ketiganya dicegah oleh berkas `.env` yang sudah disetel untuk produksi. Bila
memakai paket dari A2, berkas itu **sudah ada di dalam paket** — jadi kesalahan
ini tidak bisa terjadi karena lupa di lokasi. Yang tersisa hanya mengganti nama
perusahaan di Langkah 4, dan membuktikan hasilnya bersih di Langkah 7.

---

## Peta singkat

| Tahap | Di mana | Perkiraan waktu |
|---|---|---|
| **A.** Persiapan | kantor sendiri | 30 menit |
| **B.** Pemasangan | komputer customer | 30 menit, tanpa internet |
| **C.** Penyesuaian | komputer customer | 30–60 menit |
| **D.** Serah terima | bersama customer | 1–2 jam |

---

# Tahap A — Persiapan sebelum berangkat

## A1. Putuskan dulu: mulai dari nol atau bawa data?

| Pilihan | Isi yang sampai ke customer | Berkasnya |
|---|---|---|
| **Mulai dari nol** | data awal bawaan — contoh toko bangunan | tidak ada, cukup Langkah 5 |
| **Bawa data master kita** | produk, mitra, harga, bagan akun, pengaturan — **transaksi kosong** | `cadangkan-master.bat` |
| ~~Bawa seisi basis data~~ | **ikut membawa PO, faktur, surat jalan, dan jurnal kita** | `cadangkan-db.bat` — untuk pindah komputer sendiri, **bukan** untuk customer |

Pilihan kedua adalah yang biasa dipakai: data master di kantor sudah rapi dan
sayang diketik ulang, sementara pembukuan customer harus mulai dari nol.

**Di kantor**, sebelum berangkat:

1. Rapikan dulu basis data di sini — produk, mitra, harga, bagan akun, modul,
   nomor dokumen, tarif pajak.
2. Klik dua kali **`cadangkan-master.bat`**.
3. Berkas `backup\erppeak-master-….sql` yang dihasilkannya diambil sendiri
   oleh `buat-paket.bat` di A2 — tidak perlu disalin manual.

Skrip itu menyalin struktur seluruh tabel lalu mengisi **hanya tabel master**.
Tabel transaksi tetap terbentuk lengkap, isinya kosong. Penomoran dokumen juga
dikembalikan ke awal, jadi PO pertama customer bernomor `PO-0001`, bukan
melanjutkan urutan pembukuan kita.

Yang ikut dan yang tidak:

| Ikut terbawa | Dikosongkan |
|---|---|
| Bagan akun, satuan, pajak, termin, gudang | PR, PO, penerimaan, faktur pembelian, pembayaran supplier |
| Produk, kategori, mitra, tingkat harga, harga jual/beli | Penawaran, SO, surat jalan, faktur penjualan, pembayaran customer, retur |
| Karyawan, departemen, jabatan, jenis cuti | Jurnal, buku besar, stok, kartu stok, penyesuaian, transfer |
| Pengguna, peran, izin | Absensi, cuti, penggajian |
| Pengaturan sistem, prefix nomor dokumen | Log aktivitas, sesi, antrean, riwayat harga |

> Kata sandi, profil perusahaan, dan nama pengguna **ikut terbawa apa adanya**
> dari komputer kita. Keduanya diganti di Langkah 8 dan Langkah 9 — jangan
> dilewati.

## A2. Buat paketnya — satu folder yang tinggal disalin

Klik dua kali **`buat-paket.bat`**. Hasilnya satu folder
`PAKET-ERP-PEAK` di sebelah folder proyek, sekitar **200 MB**:

```
PAKET-ERP-PEAK\
├── BACA-DULU.txt            tujuh langkah ringkas untuk di lokasi
├── 1-PEMASANG\              tempat menaruh pemasang XAMPP
├── 2-APLIKASI\Erppeak\      aplikasi lengkap, .env sudah siap produksi
├── 3-DATA\                  erppeak-master-….sql dari A1
├── CEKLIST-SERAH-TERIMA.pdf lembar untuk dicetak dan ditandatangani
├── CEKLIST-SERAH-TERIMA.md
├── PANDUAN-SERAH-TERIMA.md
└── PANDUAN-INSTALASI.md
```

Isinya sengaja dibuat sampai **tidak perlu internet sama sekali** di tempat
customer. `vendor\` (104 MB) dan `public\build\` (1,8 MB) ikut, jadi
`pasang-erp.bat` melewati `composer install` dan `npm` — dan karena tidak
dijalankan, **Composer dan Node.js tidak perlu dipasang** di komputer customer.
Cukup XAMPP.

`node_modules\` (352 MB) tidak ikut. Itu hanya alat untuk membangun
`public\build\`, dan hasil bangunannya sudah ikut.

Berkas `.env` di dalam paket sudah berisi `APP_ENV=production` dan
`APP_DEBUG=false`. Langkah yang paling mudah terlupa jadi sudah selesai sebelum
berangkat, bukan diserahkan pada ingatan di lokasi. `APP_KEY` sengaja kosong —
`pasang-erp.bat` membuat kunci baru di komputer customer, jadi tiap customer
memakai kunci yang berbeda.

Setelah skripnya selesai, tinggal dua hal:

- [ ] Unduh pemasang **XAMPP** (PHP 8.2+) dari https://www.apachefriends.org,
      taruh di folder `1-PEMASANG\`. Unduh **di kantor** — jangan diandalkan
      bisa mengunduh di tempat customer.
- [ ] Cetak `CEKLIST-SERAH-TERIMA.pdf` dari dalam paket, **dua rangkap** —
      satu ditinggal untuk customer, satu dibawa pulang.
- [ ] Salin seluruh folder `PAKET-ERP-PEAK` ke flashdisk.

Bawa juga data master customer bila sudah dikumpulkan — daftar produk,
supplier, customer, saldo awal dalam bentuk Excel — untuk diunggah di
Langkah 11.

---

# Tahap B — Pemasangan di komputer customer

## Langkah 1 — Periksa komputernya

| Yang diperiksa | Minimum | Kenapa |
|---|---|---|
| Windows | 10 / 11, 64-bit | XAMPP 8.2 tidak berjalan di bawahnya |
| RAM | 8 GB | MySQL + PHP + browser |
| Ruang kosong | 5 GB | pustaka dan basis data yang tumbuh |
| Hak akses | **Administrator** | XAMPP menulis ke `C:\` |
| Port 3306 dan 7001 | belum dipakai | lihat bagian *Bila ada masalah* |

Tanyakan juga: komputer ini nanti dipakai **sendiri**, atau ERP-nya mau dibuka
dari beberapa komputer lain? Jawabannya menentukan Langkah 13.

## Langkah 2 — Pasang XAMPP

Jalankan pemasang XAMPP dari folder `1-PEMASANG\`, biarkan di `C:\xampp`.
Semua skrip `.bat` mencarinya di situ.

**Composer dan Node.js tidak perlu dipasang.** Pustaka dan tampilan sudah ikut
di dalam paket, jadi `pasang-erp.bat` tidak menjalankan `composer install`
maupun `npm` — dan tidak memeriksa keberadaannya.

Setelah XAMPP terpasang, **tutup semua jendela Command Prompt yang terbuka.**
Jendela lama belum membaca program yang baru dipasang.

## Langkah 3 — Salin folder aplikasi

Salin `2-APLIKASI\Erppeak` dari flashdisk ke lokasi yang tidak akan tersentuh,
misalnya `D:\ERP\Erppeak`.

Hindari `Desktop`, `Documents`, dan `OneDrive`. Folder yang disinkronkan ke
awan akan mengunci berkas di tengah pemakaian dan menyebabkan galat yang sulit
dilacak.

Menyalin 200 MB dari flashdisk memakan beberapa menit. Tunggu sampai benar-benar
selesai sebelum lanjut — folder yang tersalin separuh gagal dengan pesan yang
membingungkan.

## Langkah 4 — Ubah nama perusahaan di `.env`

Berkas `.env` **sudah ada** di dalam folder aplikasi dan sudah disetel untuk
produksi. Yang perlu diubah hanya satu baris.

Buka `D:\ERP\Erppeak\.env` dengan Notepad, ganti baris pertama:

```
APP_NAME="GANTI DENGAN NAMA PERUSAHAAN CUSTOMER"
```

menjadi nama perusahaan customer, lalu simpan. **Baris lain jangan diubah.**

Periksa sekilas bahwa tiga baris ini memang seperti berikut:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=
```

`APP_KEY` yang kosong itu benar — `pasang-erp.bat` mengisinya dengan kunci baru
di komputer ini, jadi tiap customer memakai kunci yang berbeda.

**Kenapa dua baris pertama itu penting:**

- `APP_ENV=production` — `database/seeders/DatabaseSeeder.php` hanya
  menjalankan `DemoTransactionSeeder` ketika lingkungannya `local` atau
  `development`. Dengan `production`, basis data customer tidak pernah terisi
  PO, faktur, dan jurnal contoh.
- `APP_DEBUG=false` — halaman galat berubah menjadi pesan singkat, bukan
  jejak kesalahan lengkap yang ikut menampilkan isi `.env`.
`pasang-erp.bat` **tidak akan menimpa** `.env` yang sudah ada, jadi berkas dari
paket beserta suntingan Anda aman.

> Memasang tanpa paket, langsung dari folder proyek? Maka `.env` belum ada:
> jalankan `copy .env.customer .env` lebih dulu, baru sunting `APP_NAME`.
> Jangan memakai `.env.example` — isinya masih `APP_ENV=local`.

## Langkah 5 — Jalankan pemasangan

Klik dua kali **`pasang-erp.bat`**.

Skrip mengerjakan tujuh langkah berurutan dan berhenti dengan pesan yang jelas
bila ada yang kurang:

| Langkah | Isinya |
|---|---|
| 0 | memeriksa PHP, MySQL, Composer, Node.js |
| 1 | menyiapkan `.env` — milik kita dari Langkah 4 dibiarkan |
| 2 | menyalakan MySQL |
| 3 | membuat basis data `erppeak` |
| 4 | `composer install` — **dilewati**, pustaka sudah ikut di paket |
| 5 | membuat kunci aplikasi |
| 6 | membuat tabel dan mengisi data awal |
| 7 | membangun tampilan — **dilewati**, lalu membuat pintasan di desktop |

Langkah 0 akan mencetak *"Pustaka dan tampilan sudah ikut tersalin"*. Kalau
kalimat itu **tidak** muncul, berarti folder `vendor\` atau `public\build\`
tidak ikut tersalin di Langkah 3 — dan pemasangan akan mencoba mengunduh.
Hentikan, ulangi penyalinannya.

Karena kedua langkah unduhan itu dilewati, pemasangan selesai dalam hitungan
detik dan **tidak menyentuh internet sama sekali**.

Aman dijalankan ulang bila macet di tengah: migrasi hanya menjalankan yang
belum pernah dijalankan, dan data awal hanya diisi bila tabel penggunanya masih
kosong.

### Langkah 5b — Bila membawa data master dari kantor (opsional)

Kerjakan **setelah** Langkah 5 selesai. Data awal bawaan yang baru saja terisi
akan tergantikan oleh data master kita.

1. Salin berkas `erppeak-master-….sql` dari folder `3-DATA\` di flashdisk ke
   `D:\ERP\Erppeak\backup\`.
2. Klik dua kali **`pulihkan-db.bat`** — otomatis memakai berkas `.sql` terbaru
   di folder itu. Bisa juga menyeret berkasnya ke atas ikon skripnya, dan itu
   lebih aman bila di folder `backup\` sudah ada berkas lain.
3. Ketik `YA` lalu Enter.

Isi yang sekarang dicadangkan dulu ke `backup\sebelum-pulih-….sql`, jadi salah
pilih berkas masih bisa dibatalkan. Struktur tabel disesuaikan otomatis dengan
versi kode di komputer itu setelah pemulihan.

## Langkah 6 — Nyalakan dan uji

Klik pintasan **ERP Peak** di desktop. MySQL menyala lebih dulu, lalu server
aplikasi, lalu browser terbuka sendiri di **http://127.0.0.1:7001**.

Masuk dengan:

```
admin@bonecomtricom.com  /  password
```

## Langkah 7 — Pastikan hasilnya bersih

Sebelum lanjut, buktikan bahwa Langkah 4 memang bekerja. Di Command Prompt,
dari folder proyek:

```
C:\xampp\mysql\bin\mysql.exe -u root -D erppeak -e "SELECT (SELECT COUNT(*) FROM purchase_orders) AS po, (SELECT COUNT(*) FROM sales_orders) AS so, (SELECT COUNT(*) FROM journals) AS jurnal, (SELECT COUNT(*) FROM users) AS pengguna;"
```

Yang benar:

| Kolom | Harus | Artinya bila tidak |
|---|---|---|
| `po`, `so`, `jurnal` | **0** | transaksi contoh ikut terpasang — Langkah 4 terlewat |
| `pengguna` | **6** | enam akun peran bawaan, dirapikan di Langkah 9 |

Pemeriksaan ini berlaku untuk kedua jalur — pemasangan polos maupun yang
memakai Langkah 5b. Berkas dari `cadangkan-master.bat` memang tidak membawa
satu pun transaksi, dan langkah ini yang membuktikannya di komputer customer.

Bila `po` tidak nol, cara paling bersih adalah mengulang: tutup aplikasi lewat
pintasan **ERP Peak - Matikan**, betulkan `.env`, lalu

```
C:\xampp\php\php.exe artisan migrate:fresh --seed --force
```

Ini menghapus seluruh isi basis data dan membangunnya ulang. Aman **hanya**
sekarang, selagi belum ada data sungguhan.

---

# Tahap C — Menjadikannya milik customer

## Langkah 8 — Profil perusahaan

**Pengaturan → Pengaturan Sistem → Profil Perusahaan**

Isi semuanya; semua tercetak di dokumen yang keluar dari sistem:

- [ ] Nama perusahaan
- [ ] Alamat lengkap
- [ ] Telepon dan email
- [ ] NPWP
- [ ] Logo — PNG latar transparan, tinggi minimal 200 px
- [ ] Nama bank, nomor rekening, dan nama pemilik rekening

Nama di sini yang muncul di halaman masuk dan judul setiap halaman — bukan
`APP_NAME` di `.env`.

Bila Langkah 5b dipakai, profil perusahaan **kita** ikut terbawa dan sudah
terpasang di sana. Halaman masuk akan menampilkan nama perusahaan kita sampai
diganti — jadi periksa langsung, jangan diandaikan kosong.

## Langkah 9 — Akun dan kata sandi

**Wajib, dan jangan ditunda.** Enam akun bawaan semuanya bersandi `password`.

1. **Pengaturan → Pengguna** — untuk setiap akun yang **akan dipakai**, ubah
   nama dan emailnya menjadi milik karyawan customer, lalu isi sandi baru.
2. Hapus akun peran yang memang tidak dipakai. Toko yang tidak punya staf
   akuntansi tersendiri tidak perlu akun Akuntansi.
3. Akun **Super Admin** dipegang customer, bukan kita. Serahkan sandinya
   secara langsung, jangan lewat WhatsApp atau email.
4. Minta setiap pengguna mengganti sandinya sendiri lewat **Profil Saya** di
   pojok kanan atas.

Peran yang tersedia dan wewenangnya bisa dilihat di **Pengaturan → Peran &
Izin**. Peran **Gudang** jangan dihapus — hanya peran itu yang boleh memposting
penerimaan barang dan surat jalan; tanpanya alur pembelian dan penjualan
berhenti di tengah.

## Langkah 10 — Modul, nomor dokumen, dan pajak

**Pengaturan → Pengaturan Sistem**

| Bagian | Yang dikerjakan |
|---|---|
| **Modul Aktif** | matikan modul yang tidak dipakai — menunya langsung hilang dari sidebar, tampilan jadi jauh lebih ringkas |
| **Nomor Dokumen** | sesuaikan prefix dan reset (bulanan/tahunan) dengan kebiasaan customer; ubah **sebelum** dokumen pertama dibuat |
| **Pengaturan Pajak** | tarif PPN, dan DPP Nilai Lain bila customer memakainya |
| **Pemetaan Akun** | biarkan bawaannya kecuali bagan akun diubah |
| **Operasional** | gudang bawaan, syarat pembayaran bawaan |

Nomor dokumen paling merepotkan bila diubah belakangan — penomoran jadi
melompat di tengah tahun buku. Selesaikan sekarang.

## Langkah 11 — Data master

**Bila Langkah 5b dipakai**, isi bagian ini sudah menjadi data master kita —
tinggal disesuaikan nama, harga, dan gudangnya dengan customer. Lewati tabel di
bawah; yang berlaku hanya dua kalimat terakhir bagian ini.

**Bila memasang polos**, transaksi contoh sudah dicegah oleh Langkah 4, tetapi
**data master contoh tetap terpasang** — dan memang harus, karena bagan akun dan
satuan diperlukan agar sistem bisa dipakai sama sekali. Yang perlu ditinjau
hanya sebagian:

| Isinya | Jumlah | Tindakan |
|---|---|---|
| Produk | 38 | contoh **toko bangunan** — besi, cat, semen, kayu, pipa |
| Kategori produk | 8 | ikut mengikuti barang di atas |
| Mitra (supplier/customer) | 6 | nama karangan, **wajib** diganti |
| Karyawan | 11 | nama karangan, **wajib** diganti |
| Departemen | 7 | tinjau, sesuaikan struktur customer |
| Gudang | 2 | sesuaikan nama dan alamatnya |
| Satuan (UOM) | 14 | umum, biarkan |
| Termin pembayaran | 6 | umum, biarkan |
| Bagan akun (COA) | 56 | umum, **jangan dihapus** — pemetaan akun mengacu ke sini |

Untuk produk, kategori, dan mitra:

- **Customer memang toko bangunan** → pakai sebagai contoh, sesuaikan nama dan
  harganya.
- **Bukan** → hapus semuanya, lalu isi milik customer. Selagi belum ada dokumen
  yang memakainya, semuanya masih bisa dihapus.

Karyawan contoh di **SDM → Karyawan** tertaut ke akun pengguna bawaan. Rapikan
bersamaan dengan Langkah 9 supaya nama yang muncul di dokumen bukan nama
karangan.

Untuk produk yang banyak, pakai **Data Master → Upload Produk**; unduh
templatnya dari halaman itu.

---

# Tahap D — Sebelum ditinggalkan

## Langkah 12 — Cadangan

Ajarkan langsung, jangan hanya diceritakan:

1. Klik dua kali **`cadangkan-db.bat`**.
2. Berkas muncul di `backup\erppeak-TANGGAL-JAM.sql`.
3. Salin ke flashdisk atau Google Drive.

Sepakati siapa yang mencadangkan dan seberapa sering — **mingguan** cukup untuk
pemakaian biasa, **harian** bila transaksinya ramai. Tulis nama orangnya di
berita acara.

Ingatkan: folder `backup\` ada di komputer yang sama. Bila komputernya rusak,
cadangan ikut hilang. Cadangan harus keluar dari komputer itu.

## Langkah 13 — Bila dipakai dari beberapa komputer (opsional)

Bawaannya, ERP hanya bisa dibuka di komputer tempat ia dipasang. Untuk membuka
dari komputer lain di jaringan yang sama:

1. Di `.env`, tambahkan satu baris:

   ```
   PHP_CLI_SERVER_WORKERS=8
   ```

2. Di `jalankan-erp.bat`, ubah baris server aplikasi menjadi:

   ```
   start "ERP Peak Server" /MIN cmd /c ""%PHP%" artisan serve --host=0.0.0.0 --port=%PORT% --no-reload"
   ```

   `--no-reload` tidak boleh dilewat: tanpanya jumlah pekerja diabaikan dan
   server tetap melayani satu permintaan pada satu waktu.

3. Buka port di Windows Firewall, sekali saja, lewat Command Prompt sebagai
   Administrator:

   ```
   netsh advfirewall firewall add rule name="ERP Peak" dir=in action=allow protocol=TCP localport=7001
   ```

4. Catat IP komputer server (`ipconfig`), lalu setel IP itu menjadi **statis**
   di pengaturan jaringan. IP yang berubah membuat semua komputer lain gagal
   membuka ERP keesokan harinya.

5. Ubah `APP_URL` di `.env` menjadi `http://IP-SERVER:7001`.

Komputer lain membuka **http://IP-SERVER:7001** dari browser. Komputer server
harus tetap menyala selama jam kerja.

> Cara ini pas untuk sekitar lima pemakai bersamaan. Lebih dari itu, `artisan
> serve` bukan lagi alat yang tepat — ERP sebaiknya dijalankan lewat Apache
> bawaan XAMPP dengan virtual host yang mengarah ke folder `public\`.

## Langkah 14 — Pelatihan

Latih memakai alur yang sungguhan, bukan demo. Urutan yang paling mudah
dicerna:

1. **Buku Panduan di dalam sistem** — ikon tanda tanya di kanan atas. Isinya
   mengikuti modul yang aktif, jadi yang tampil hanya yang memang dipakai.
2. **Satu alur pembelian utuh** — Permintaan Pembelian (PR) → Pesanan
   Pembelian (PO) → Penerimaan Barang (GRN) → Faktur Pembelian → Pembayaran
   ke Supplier. Kerjakan bersama, biarkan mereka yang mengetik.
3. **Satu alur penjualan utuh** — Penawaran → Pesanan Penjualan (SO) → Surat
   Jalan (DO) → Faktur Penjualan → Pembayaran dari Customer.
4. **Menyalakan dan mematikan** — tekankan mematikan lewat pintasan
   **ERP Peak - Matikan**, bukan dengan menutup jendelanya.
5. **Mencadangkan** — Langkah 12.

Minta setiap peserta mengerjakan sendiri sekali tanpa dituntun sebelum sesi
ditutup.

## Langkah 15 — Checklist serah terima

Daftar centangnya ada di lembar tersendiri,
**[CEKLIST-SERAH-TERIMA.md](CEKLIST-SERAH-TERIMA.md)** — dan versi cetaknya,
`CEKLIST-SERAH-TERIMA.pdf`, sudah ikut di dalam paket. Lembar itulah yang
dicentang pakai pena di lokasi lalu ditandatangani bersama customer.

Daftarnya sengaja hanya ada di satu berkas: dua daftar yang sama-sama disunting
akan berbeda isi tanpa ada yang menyadarinya, dan yang berbeda itu justru yang
dibawa ke lapangan. PDF-nya pun tidak disunting sendiri — `buat-paket.bat`
membuatnya ulang dari berkas `.md` setiap kali paket disusun, dan
`cetak-ceklist.bat` melakukan hal yang sama bila lembarnya perlu dicetak di
luar itu.

Isinya mengikuti panduan ini langkah demi langkah, ditambah yang tidak bisa
dicentang begitu saja: angka hasil pemeriksaan Langkah 7 ditulis apa adanya
dari layar, daftar akun yang dibuat beserta paraf penerima sandinya,
penanggung jawab cadangan, dan IP server bila Langkah 13 dipakai.

Tiga butir di dalamnya berdiri sendiri sebagai pintu terakhir — selama salah
satunya belum tercentang, sistem belum boleh dianggap diserahkan:

- tidak ada akun yang masih bersandi `password` *(Langkah 9)*
- basis data terbukti tanpa transaksi contoh *(Langkah 7)*
- cadangan pertama sudah tersimpan di luar komputer itu *(Langkah 12)*

Pekerjaan yang tersisa ditulis di bagian **Yang belum selesai** di ujung
lembar, lengkap dengan nama dan tanggal. Pemasangan memang sering menyisakan
sesuatu; yang berbahaya adalah yang tertinggal tanpa ada yang tahu. Tanda
tangan di bawahnya membuat sisa itu menjadi kesepakatan, bukan kelupaan.

---

# Bila ada masalah

**Pemasangan malah menjalankan `composer install` atau `npm`**
Folder `vendor\` atau `public\build\` tidak ikut tersalin di Langkah 3.
Periksa keduanya ada di `D:\ERP\Erppeak`, lalu jalankan ulang
`pasang-erp.bat`. Menyalin ulang dari flashdisk jauh lebih cepat daripada
mengunduh lewat jaringan customer.

**"Composer tidak ditemukan"**
Muncul hanya bila `vendor\` tidak ikut — lihat butir di atas. Pada pemasangan
tanpa paket, tutup jendela Command Prompt, buka baru, lalu jalankan lagi
`pasang-erp.bat`.

**MySQL tidak mau menyala**
Hampir selalu port 3306 sudah dipakai MySQL lain yang terpasang terpisah.
Periksa dengan:

```
netstat -ano | findstr :3306
```

Bila terpakai, matikan layanan MySQL yang lama lewat *services.msc*, atau ubah
`DB_PORT` di `.env` dan port MySQL di `C:\xampp\mysql\bin\my.ini` bersamaan.

**Port 7001 sudah dipakai**
Ubah `set "PORT=7001"` di `jalankan-erp.bat` dan `hentikan-erp.bat`, lalu
sesuaikan `APP_URL` di `.env`.

**Halaman terbuka sangat lambat lalu menggantung**
MySQL belum jalan. Session dan cache disimpan di basis data, jadi setiap
permintaan menunggu koneksi yang tidak pernah datang. Nyalakan lewat pintasan
**ERP Peak** — di sana MySQL dinyalakan lebih dulu.

**Tampilan berantakan, tanpa warna**
Berkas tampilan belum dibangun. Di folder proyek:

```
npm install
npm run build
```

**Halaman galat hanya berbunyi "Server Error"**
Itu memang perilaku yang benar setelah `APP_DEBUG=false`. Sebab aslinya ada di
baris terakhir `storage\logs\laravel.log`.

**XAMPP dipasang bukan di `C:\xampp`**
Ubah baris `set "XAMPP=..."` di bagian atas `pasang-erp.bat`,
`jalankan-erp.bat`, `hentikan-erp.bat`, `cadangkan-db.bat`, dan
`pulihkan-db.bat`.
