# Ceklist Serah Terima ERP Peak

Lembar ini **dicetak** dan dibawa ke tempat customer. Cara mengerjakan tiap
butir ada di [PANDUAN-SERAH-TERIMA.md](PANDUAN-SERAH-TERIMA.md); di sini hanya
yang harus dicentang beserta nomor langkahnya.

Butir yang belum tercentang bukan kegagalan — pemasangan memang sering
menyisakan pekerjaan. Yang berbahaya adalah yang tertinggal tanpa ada yang
tahu. Karena itu ada bagian **Yang belum selesai** di bawah, dan lembar ini
ditandatangani dua pihak: apa pun yang tersisa menjadi kesepakatan, bukan
kelupaan.

> **Jangan menulis kata sandi di lembar ini.** Lembar ini berpindah tangan dan
> difoto. Sandi diserahkan langsung kepada orangnya.

---

## Identitas

| | |
|---|---|
| Nama perusahaan customer | ____________________________________ |
| Alamat pemasangan | ____________________________________ |
| Tanggal pemasangan | ______________ Tanggal serah terima ______________ |
| Nama pemasang | ____________________________________ |
| Pendamping dari customer | ____________________________________ |
| Nama komputer / pemakainya | ____________________________________ |
| Folder aplikasi | `D:\ERP\Erppeak` atau ______________________________ |
| Berkas data master yang dipakai | `erppeak-master-` ____________ / tidak memakai |

---

## A. Di kantor, sebelum berangkat

- [ ] Basis data di kantor sudah dirapikan — produk, mitra, harga, bagan akun,
      pengaturan *(A1)*
- [ ] `cadangkan-master.bat` dijalankan, berkasnya ada di `backup\` *(A1)*
- [ ] `buat-paket.bat` selesai, folder `PAKET-ERP-PEAK` terbentuk *(A2)*
- [ ] Baris **"Data master: erppeak-master-…"** memang muncul saat paket
      dibuat — bila yang muncul justru pertanyaan "lanjut tanpa data master",
      pastikan itu memang disengaja *(A2)*
- [ ] Pemasang XAMPP (PHP 8.2+) sudah ada di `1-PEMASANG\` *(A2)*
- [ ] Seluruh folder paket tersalin ke flashdisk sampai **selesai**, bukan
      terputus di tengah *(A2)*
- [ ] Lembar ini dicetak, dan data master customer (Excel) ikut dibawa

## B. Pemasangan di komputer customer

- [ ] Windows 10/11 64-bit, RAM minimal 8 GB, ruang kosong minimal 5 GB, hak
      Administrator *(Langkah 1)*
- [ ] Sudah ditanyakan: dipakai satu komputer saja, atau dibuka dari beberapa
      komputer? Jawabannya menentukan bagian D *(Langkah 1)*
- [ ] XAMPP terpasang di `C:\xampp`, lalu semua jendela Command Prompt lama
      ditutup *(Langkah 2)*
- [ ] Folder aplikasi disalin ke lokasi tetap — **bukan** Desktop, Documents,
      atau OneDrive *(Langkah 3)*
- [ ] `.env` sudah berisi nama perusahaan customer *(Langkah 4)*
- [ ] `.env` diperiksa dengan mata sendiri: `APP_ENV=production`,
      `APP_DEBUG=false`, `APP_KEY=` kosong *(Langkah 4)*
- [ ] Saat pemasangan berjalan muncul kalimat **"Pustaka dan tampilan sudah
      ikut tersalin"** — bila tidak muncul, hentikan dan ulangi penyalinan
      *(Langkah 5)*
- [ ] `pasang-erp.bat` selesai tanpa galat *(Langkah 5)*
- [ ] Bila membawa data master: `pulihkan-db.bat` selesai, dan nomor dokumen
      pertama mulai dari **1** *(Langkah 5b)*
- [ ] Pintasan **ERP Peak** dan **ERP Peak - Matikan** ada di desktop
      *(Langkah 5)*
- [ ] Aplikasi terbuka di http://127.0.0.1:7001 dan bisa dimasuki *(Langkah 6)*

**Bukti basis data bersih** *(Langkah 7)* — tulis angka yang benar-benar
terbaca di layar, jangan hanya dicentang:

| `po` | `so` | `jurnal` | `pengguna` |
|---|---|---|---|
| ______ | ______ | ______ | ______ |
| harus 0 | harus 0 | harus 0 | harus 6 |

- [ ] Keempat angka sesuai. Bila `po` tidak nol berarti transaksi contoh ikut
      terpasang — **jangan diteruskan**; betulkan `.env` lalu bangun ulang
      selagi belum ada data sungguhan *(Langkah 7)*

## C. Menjadikannya milik customer

**Profil perusahaan** *(Langkah 8)* — semuanya tercetak di dokumen yang keluar
dari sistem:

- [ ] Nama perusahaan
- [ ] Alamat lengkap
- [ ] Telepon dan email
- [ ] NPWP
- [ ] Logo — PNG latar transparan, tinggi minimal 200 px
- [ ] Nama bank, nomor rekening, nama pemilik rekening
- [ ] Halaman masuk sudah menampilkan nama customer, bukan nama perusahaan
      kita yang ikut terbawa dari data master

**Akun dan kata sandi** *(Langkah 9)* — enam akun bawaan semuanya bersandi
`password`:

| Nama karyawan | Email | Peran | Sandi diserahkan ke (paraf) |
|---|---|---|---|
| | | Super Admin | |
| | | | |
| | | | |
| | | | |
| | | | |

- [ ] Setiap akun yang dipakai sudah berganti nama, email, dan sandi
- [ ] Akun peran yang tidak dipakai sudah dihapus — peran **Gudang** jangan
      dihapus, hanya peran itu yang boleh memposting penerimaan barang dan
      surat jalan
- [ ] Sandi Super Admin diserahkan langsung kepada customer, bukan lewat
      WhatsApp atau email
- [ ] Tidak ada satu pun akun yang masih bersandi `password`

**Pengaturan sistem** *(Langkah 10)*:

- [ ] Modul yang tidak dipakai sudah dimatikan
- [ ] Prefix dan pola reset nomor dokumen sesuai kebiasaan customer — diubah
      **sebelum** dokumen pertama dibuat
- [ ] Tarif PPN benar, beserta DPP Nilai Lain bila customer memakainya
- [ ] Gudang bawaan dan syarat pembayaran bawaan sudah disetel

**Data master** *(Langkah 11)*:

- [ ] Mitra contoh (6 nama karangan) sudah diganti
- [ ] Karyawan contoh (11 nama karangan) sudah diganti
- [ ] Produk dan kategori: dipakai sebagai contoh / dihapus lalu diisi milik
      customer *(coret salah satu)*
- [ ] Nama dan alamat gudang sesuai
- [ ] Bagan akun (56) **tidak** dihapus — pemetaan akun mengacu ke sini

## D. Sebelum ditinggalkan

**Cadangan** *(Langkah 12)* — dipraktikkan customer, bukan sekadar
diceritakan:

- [ ] Customer sendiri yang mengklik `cadangkan-db.bat` dan melihat hasilnya
- [ ] Cadangan pertama **sudah keluar dari komputer itu** — flashdisk atau
      Google Drive

| | |
|---|---|
| Penanggung jawab cadangan | ____________________________________ |
| Seberapa sering | mingguan / harian / ______________ |
| Disalin ke mana | ____________________________________ |

**Bila dipakai dari beberapa komputer** *(Langkah 13 — lewati bila hanya satu
komputer)*:

- [ ] `PHP_CLI_SERVER_WORKERS=8` ditambahkan di `.env`
- [ ] `jalankan-erp.bat` memakai `--host=0.0.0.0` beserta `--no-reload`
- [ ] Port 7001 dibuka di Windows Firewall
- [ ] IP komputer server disetel **statis** — IP: ______________________
- [ ] `APP_URL` diubah menjadi `http://IP-SERVER:7001`
- [ ] Dicoba dari satu komputer lain, dan memang terbuka

**Pelatihan** *(Langkah 14)*:

- [ ] Buku Panduan di dalam sistem sudah ditunjukkan — ikon tanda tanya di
      kanan atas
- [ ] Satu alur pembelian utuh dikerjakan sampai selesai: PR → PO → Penerimaan
      Barang → Faktur Pembelian → Pembayaran Supplier
- [ ] Satu alur penjualan utuh dikerjakan sampai selesai: Penawaran → SO →
      Surat Jalan → Faktur Penjualan → Pembayaran Customer
- [ ] Cara menyalakan, dan mematikan lewat pintasan **ERP Peak - Matikan**,
      bukan dengan menutup jendelanya
- [ ] Setiap peserta sudah mengerjakan sendiri sekali tanpa dituntun

Peserta pelatihan: _______________________________________________________

_________________________________________________________________________

**Dukungan**

| | |
|---|---|
| Jalur dan nomor | ____________________________________ |
| Jam dukungan | ____________________________________ |
| Panduan diserahkan | [ ] cetak &nbsp; [ ] berkas `.md` &nbsp; [ ] di dalam sistem |

---

## Tiga yang tidak boleh dilewati

Selama salah satu dari tiga ini belum tercentang, sistem **belum boleh**
dianggap diserahkan. Sisanya boleh menyusul; tiga ini tidak.

- [ ] Tidak ada akun yang masih bersandi `password` *(Langkah 9)*
- [ ] Basis data terbukti tanpa transaksi contoh *(Langkah 7)*
- [ ] Cadangan pertama sudah tersimpan di luar komputer itu *(Langkah 12)*

---

## Yang belum selesai

Tulis apa pun yang tertinggal, beserta siapa yang mengerjakan dan kapan. Yang
tidak tertulis di sini dianggap sudah selesai.

| Yang tertinggal | Siapa | Kapan |
|---|---|---|
| | | |
| | | |
| | | |

---

## Tanda tangan

Kedua pihak sepakat bahwa yang tercentang di atas memang dikerjakan dan
dibuktikan bersama, dan bahwa yang tertulis pada **Yang belum selesai** masih
menjadi pekerjaan yang terbuka.

Pemasang &nbsp;&nbsp; Nama: __________________ Tanda tangan: __________________ Tanggal: __________

Customer &nbsp;&nbsp; Nama: __________________ Tanda tangan: __________________ Tanggal: __________

Dibuat dua rangkap: satu untuk customer, satu dibawa pulang.
