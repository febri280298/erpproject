# Memasang ERP Peak di Komputer Lain

Panduan ini untuk memindahkan atau memasang sistem di laptop/PC baru.

> Memasang di tempat **customer**? Pakai
> [PANDUAN-SERAH-TERIMA.md](PANDUAN-SERAH-TERIMA.md). Urutannya berbeda: ada
> langkah menyiapkan `.env` sebelum pemasangan supaya transaksi contoh tidak
> ikut masuk ke basis data mereka.

Ada dua hal yang harus ikut pindah, dan keduanya berbeda tempat:

| Yang pindah | Isinya | Cara membawa |
|---|---|---|
| **Folder proyek** | kode program, tampilan, skrip | salin foldernya, atau `git clone` |
| **Basis data** | produk, mitra, dokumen, pengaturan, pengguna | berkas `.sql` hasil `cadangkan-db.bat` |

> Menyalin folder proyek saja **tidak cukup**. Seluruh data usaha ada di basis
> data MySQL, bukan di dalam folder.

---

## 1. Pasang dulu tiga perangkat ini di komputer baru

Ketiganya gratis dan hanya dipasang sekali.

| Perangkat | Untuk apa | Unduh |
|---|---|---|
| **XAMPP** (PHP 8.2 atau lebih baru) | menjalankan PHP dan MySQL | https://www.apachefriends.org |
| **Composer** | mengambil pustaka PHP | https://getcomposer.org/download |
| **Node.js** versi LTS | membangun tampilan | https://nodejs.org |

Setelah ketiganya terpasang, **tutup semua jendela Command Prompt** yang sedang
terbuka. Program yang baru dipasang belum terbaca oleh jendela lama.

---

## 2. Salin folder proyek

Salin seluruh folder proyek ke komputer baru — misalnya ke `D:\LARAVEL\Erppeak`.

Boleh lewat flashdisk, atau dari GitHub:

```
git clone https://github.com/febri280298/erpproject.git Erppeak
```

Folder `vendor`, `node_modules`, dan `public\build` tidak perlu ikut disalin;
semuanya dibangun ulang pada langkah berikutnya.

---

## 3. Jalankan pemasangan

Klik dua kali **`pasang-erp.bat`** di dalam folder proyek.

Skrip ini mengerjakan semuanya berurutan:

1. memeriksa PHP, MySQL, Composer, dan Node.js — berhenti dengan pesan jelas
   bila ada yang belum terpasang;
2. membuat berkas `.env` dari contohnya;
3. menyalakan MySQL;
4. membuat basis data `erppeak`;
5. mengambil pustaka PHP (`composer install`);
6. membuat kunci aplikasi;
7. membuat tabel, mengisi data awal, membangun tampilan, lalu membuat pintasan
   di desktop.

Pemasangan pertama memakan beberapa menit karena mengunduh pustaka.

**Aman dijalankan ulang.** Migrasi hanya menjalankan yang belum pernah
dijalankan, dan data awal hanya diisi bila basis datanya memang masih kosong —
data yang sudah ada tidak pernah ditimpa.

---

## 4. Bawa data dari komputer lama

Lewati langkah ini bila memang ingin memulai dari nol.

**Di komputer lama:**

1. Klik dua kali **`cadangkan-db.bat`**.
2. Berkas tersimpan di folder `backup\`, misalnya
   `backup\erppeak-20260824-1857.sql`.
3. Salin berkas itu ke komputer baru.

**Di komputer baru:**

1. Taruh berkas `.sql` tadi di folder `backup\`.
2. Klik dua kali **`pulihkan-db.bat`** — otomatis memakai cadangan terbaru.
   Bisa juga menyeret berkas `.sql` ke atas ikon skripnya.
3. Ketik `YA` lalu Enter.

Sebelum menimpa, skrip mencadangkan dulu isi yang sekarang ke
`backup\sebelum-pulih-….sql`. Jadi salah pilih berkas masih bisa dibatalkan.

Setelah dipulihkan, struktur tabel otomatis disesuaikan dengan versi kode di
komputer itu — jadi cadangan dari versi lama tetap bisa dipakai.

---

## 5. Pemakaian sehari-hari

| Pintasan di desktop | Fungsi |
|---|---|
| **ERP Peak** | menyalakan MySQL, server aplikasi, lalu membuka browser |
| **ERP Peak - Matikan** | menghentikan server dan menutup MySQL baik-baik |

Matikan lewat pintasan **Matikan**, jangan dengan menutup jendelanya begitu
saja — supaya basis datanya ditutup rapi.

Alamat aplikasi: **http://127.0.0.1:7001**

---

## 6. Bila ada masalah

**"Composer tidak ditemukan" padahal sudah dipasang**
Tutup jendela Command Prompt lalu jalankan lagi `pasang-erp.bat`. Jendela lama
belum membaca program yang baru dipasang.

**MySQL tidak mau menyala**
Buka XAMPP Control Panel dan nyalakan MySQL dari sana untuk melihat pesan
galatnya. Penyebab paling sering: port 3306 sudah dipakai MySQL lain yang
terpasang terpisah.

**Halaman terbuka sangat lambat lalu menggantung**
Hampir selalu MySQL belum jalan. Session dan cache disimpan di basis data, jadi
setiap permintaan menunggu koneksi yang tidak pernah datang. Jalankan pintasan
**ERP Peak** — MySQL dinyalakan lebih dulu di sana.

**XAMPP dipasang bukan di `C:\xampp`**
Ubah baris `set "XAMPP=..."` di bagian atas berkas `pasang-erp.bat`,
`jalankan-erp.bat`, `hentikan-erp.bat`, `cadangkan-db.bat`, dan
`pulihkan-db.bat`.

**Tampilan berantakan setelah menyalin folder**
Berkas tampilan belum dibangun. Jalankan di folder proyek:

```
npm install
npm run build
```

---

## 7. Setelah pemasangan pertama

Pada pemasangan yang benar-benar baru, akun bawaannya:

```
admin@bonecomtricom.com  /  password
```

**Segera ganti kata sandinya** lewat menu Profil, dan hapus akun percobaan yang
tidak dipakai lewat *Pengaturan → Pengguna*.

Lalu isi *Pengaturan → Profil Perusahaan*: nama, alamat, NPWP, logo, dan
rekening tujuan transfer — semuanya tercetak di dokumen.
