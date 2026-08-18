<?php

use App\Models\Sales\SalesInvoice;

/**
 * Isi Buku Panduan yang tampil di /panduan.
 *
 * SATU-SATUNYA sumber isi panduan. Setiap kali ada modul baru, dokumen baru,
 * atau alur yang berubah, tambahkan atau sesuaikan tahapnya di sini — jangan
 * menulis ulang di Blade, agar panduan tidak pernah tertinggal dari sistemnya.
 *
 * Kunci tiap tahap:
 *   nomor    urutan tampil (dihitung ulang otomatis bila tahap disembunyikan)
 *   peran    nama peran yang mengerjakan; dipakai untuk warna dan lencana
 *   modul    tahap disembunyikan bila modul ini dimatikan (null = selalu tampil)
 *   opsional true bila tahap boleh dilewati
 *   langkah  daftar instruksi, boleh memuat HTML terbatas (<strong>, <em>, .path)
 *   periksa  yang harus benar sebelum lanjut
 *   catat    catatan tambahan di bawah tahap
 *   tabel    ['kepala' => [...], 'baris' => [[...], ...]]
 */
return [

    'peran' => [
        'Super Admin' => ['warna' => 'secondary', 'email' => 'admin@bonecomtricom.com'],
        'Manajer' => ['warna' => 'orange', 'email' => 'manajer@bonecomtricom.com'],
        'Pembelian' => ['warna' => 'blue', 'email' => 'pembelian@bonecomtricom.com'],
        'Gudang' => ['warna' => 'teal', 'email' => 'gudang@bonecomtricom.com'],
        'Penjualan' => ['warna' => 'pink', 'email' => 'penjualan@bonecomtricom.com'],
        'Akuntansi' => ['warna' => 'purple', 'email' => 'akuntansi@bonecomtricom.com'],
    ],

    'tahap' => [
        [
            'peran' => 'Super Admin',
            'modul' => null,
            'judul' => 'Siapkan identitas & aturan dasar',
            'langkah' => [
                '<span class="path">Pengaturan → Pengaturan Sistem → Profil Perusahaan</span> — isi nama, alamat, telepon, email, NPWP, dan unggah logo. Semuanya muncul di kop dokumen cetak.',
                'Di halaman yang sama buka <span class="path">Modul</span>. Nyalakan hanya area yang dipakai; menu yang dimatikan hilang dari sidebar dan alamatnya membalas 404.',
                'Buka <span class="path">PPN & DPP Nilai Lain</span>. Biarkan mati bila memakai tarif 11%. Untuk skema 12% atas 11/12, ubah dulu pajak default menjadi 12% — sistem menolak menyimpan bila urutannya terbalik.',
                'Periksa <span class="path">Pemetaan Akun</span>, pastikan tidak ada yang kosong. Inilah akun yang dipakai saat dokumen diposting.',
            ],
            'periksa' => [
                'Nama perusahaan benar di sidebar kiri atas dan di footer.',
                'Dashboard tidak menampilkan peringatan merah apa pun.',
            ],
        ],
        [
            'peran' => 'Super Admin',
            'modul' => null,
            'judul' => 'Isi data master',
            'pengantar' => 'Kerjakan berurutan — yang di bawah memakai yang di atasnya.',
            'langkah' => [
                '<span class="path">Data Master → Satuan (UOM)</span> — tambah satuan yang khas usaha Anda.',
                '<span class="path">Data Master → Pajak (PPN)</span> — tandai satu tarif sebagai default.',
                '<span class="path">Data Master → Kategori Produk</span> — kelompokkan barang.',
                '<span class="path">Data Master → Gudang</span> — sesuaikan nama, tandai satu sebagai default.',
                '<span class="path">Data Master → Termin Pembayaran (TOP)</span> — Tunai, Net 30, dan seterusnya.',
                '<span class="path">Data Master → Tingkat Harga</span> — Eceran, Grosir, Proyek. Tandai satu sebagai default.',
                '<span class="path">Data Master → Customer & Supplier</span> — untuk customer pilih <strong>Tingkat Harga</strong>-nya; harga di pesanan penjualan nanti terisi otomatis dari situ.',
                '<span class="path">Data Master → Produk / Barang</span> — isi SKU, nama, kategori, satuan, <strong>Perlakuan PPN</strong>, harga beli & jual, stok minimum.',
                'Masih di form produk, gulir ke bawah: isi <strong>Harga Jual per Tingkat</strong>, <strong>Harga Khusus per Customer</strong> bila ada, dan <strong>Harga Beli per Supplier</strong> — tandai satu supplier utama.',
            ],
            'catat' => 'Bila produknya banyak, pakai <span class="path">Data Master → Upload Produk</span>. Unduh templatnya, isi di Excel, lalu unggah. Kolomnya sama persis dengan hasil ekspor, jadi data yang diekspor bisa diedit lalu dimasukkan kembali.',
            'periksa' => [
                'Daftar produk menampilkan lencana <strong>PPN</strong> atau <strong>Bebas PPN</strong> sesuai maksud Anda.',
                'Sudah ada minimal satu supplier dan satu customer.',
                'Stok masih nol — memang belum ada barang masuk.',
            ],
        ],
        [
            'peran' => 'Pembelian',
            'modul' => 'purchase_requisition',
            'opsional' => true,
            'judul' => 'Ajukan permintaan pembelian',
            'pengantar' => 'Dipakai bila pembelian harus melewati pengajuan dari bagian yang membutuhkan.',
            'langkah' => [
                '<span class="path">Pembelian → Permintaan Pembelian (PR) → Buat</span>, isi barang dan jumlah yang dibutuhkan.',
                'Simpan lalu <strong>Ajukan</strong>. Manajer yang menyetujuinya.',
                'Setelah disetujui, tombol <strong>Buat Pesanan Pembelian</strong> muncul dan barisnya tersalin otomatis.',
            ],
            'periksa' => ['Status berubah dari Draft → Diajukan → Disetujui.'],
        ],
        [
            'peran' => 'Pembelian',
            'modul' => 'purchasing',
            'judul' => 'Buat pesanan pembelian',
            'langkah' => [
                '<span class="path">Pembelian → Pesanan Pembelian (PO) → Buat</span>.',
                'Pilih supplier dan gudang tujuan, lalu tambahkan baris produk. Harga terisi otomatis dari daftar harga supplier yang dipilih.',
                'Simpan. Statusnya <strong>Draft</strong> — barangnya belum bisa diterima.',
            ],
            'periksa' => [
                'Nomor dokumen terbentuk, contohnya <span class="doc">PO/2026/08/0001</span>.',
                'Jumlah kolom <strong>DPP</strong> sama dengan Subtotal di rekap bawah.',
                'Tombol <strong>Setujui</strong> tidak ada — memang bukan wewenang peran ini.',
            ],
        ],
        [
            'peran' => 'Manajer',
            'modul' => 'purchasing',
            'judul' => 'Setujui pesanan pembelian',
            'langkah' => [
                'Dashboard menampilkan antrian <strong>Perlu Tindakan</strong> — klik <em>PO menunggu persetujuan</em>.',
                'Buka PO tersebut, tekan <strong>Setujui</strong>.',
            ],
            'periksa' => [
                'Status PO menjadi <strong>Disetujui</strong>.',
                'Belum ada jurnal apa pun — memesan barang bukan peristiwa akuntansi.',
            ],
        ],
        [
            'peran' => 'Gudang',
            'modul' => 'purchasing',
            'judul' => 'Terima barang',
            'langkah' => [
                '<span class="path">Pembelian → Penerimaan Barang (GRN) → Buat</span>, pilih PO yang sudah disetujui.',
                'Isi jumlah yang benar-benar datang. Boleh sebagian — sisanya tetap tercatat sebagai kekurangan pada PO.',
                'Simpan, lalu tekan <strong>Posting</strong>. Sebelum diposting, stok belum berubah.',
            ],
            'catat' => 'Harga beli yang benar-benar dibayar ikut memperbarui daftar harga supplier produk itu dan tercatat di riwayat harga.',
            'periksa' => [
                '<span class="path">Stok & Gudang → Stok Barang</span> menunjukkan jumlah bertambah.',
                '<span class="path">Stok & Gudang → Kartu Stok</span> mencatat satu baris masuk berikut harga pokoknya.',
                'Status PO menjadi <strong>Diterima</strong>, atau <strong>Sebagian</strong> bila tidak penuh.',
            ],
        ],
        [
            'peran' => 'Akuntansi',
            'modul' => 'purchasing',
            'judul' => 'Catat tagihan supplier lalu bayar',
            'langkah' => [
                '<span class="path">Pembelian → Faktur Pembelian (Invoice)</span> — buat dari PO agar barisnya tertarik otomatis. Isi nomor faktur supplier dan jatuh temponya.',
                'Simpan, lalu <strong>Posting</strong>.',
                '<span class="path">Pembelian → Pembayaran ke Supplier → Buat</span>. Pilih supplier dan akun kas/bank, lalu alokasikan ke faktur — boleh ke beberapa faktur sekaligus.',
                'Simpan, lalu <strong>Posting</strong>.',
            ],
            'periksa' => [
                'Di halaman faktur muncul panel <strong>Jurnal Terkait</strong>.',
                'Setelah dibayar penuh, status faktur menjadi <strong>Lunas</strong>.',
            ],
        ],
        [
            'peran' => 'Penjualan',
            'modul' => 'quotation',
            'opsional' => true,
            'judul' => 'Kirim penawaran harga',
            'pengantar' => 'Dipakai bila customer meminta penawaran sebelum memesan. Bila order langsung masuk, lewati tahap ini.',
            'langkah' => [
                '<span class="path">Penjualan → Penawaran (Quotation) → Buat</span>.',
                'Pilih customer dan masa berlaku, lalu isi barisnya. Harga mengikuti tingkat harga customer.',
                'Simpan, lalu cetak atau ekspor ke <strong>Excel</strong> untuk dikirim ke customer.',
                'Bila customer setuju, ubah status menjadi <strong>Diterima</strong>. Tombol <strong>Buat Pesanan Penjualan</strong> muncul dan seluruh barisnya tersalin.',
            ],
            'catat' => 'Menu ini hanya terlihat oleh Super Admin, Penjualan, dan Manajer. Bila tidak muncul, periksa peran akun yang dipakai atau modul Penawaran di Pengaturan → Modul.',
            'periksa' => [
                'Ekspor Excel menghasilkan berkas dengan rumus hidup — mengubah qty di Excel ikut menghitung ulang totalnya.',
                'Setelah dikonversi, penawaran berstatus <strong>Ditutup</strong> dan tidak bisa dikonversi dua kali.',
            ],
        ],
        [
            'peran' => 'Penjualan',
            'modul' => 'sales',
            'judul' => 'Terima order customer',
            'langkah' => [
                '<span class="path">Penjualan → Pesanan Penjualan (SO) → Buat</span>.',
                'Pilih customer — harga seluruh baris langsung menyesuaikan tingkat harganya, dan sumber harga tertulis di atas tabel.',
                'Simpan sebagai Draft, lalu minta <strong>Manajer</strong> menekan <strong>Konfirmasi</strong>.',
            ],
            'periksa' => [
                'Harga jual berubah bila customer diganti dengan tingkat harga berbeda.',
                'Staf penjualan tidak dapat mengonfirmasi pesanannya sendiri.',
            ],
        ],
        [
            'peran' => 'Gudang',
            'modul' => 'sales',
            'judul' => 'Kirim barang',
            'langkah' => [
                '<span class="path">Penjualan → Surat Jalan (DO) → Buat</span> — pilih <strong>Dari Pesanan Penjualan</strong>, lalu pilih SO tadi.',
                'Isi jumlah kirim, nama pengemudi, dan nomor kendaraan. Simpan, lalu <strong>Posting</strong>.',
                'Cetak surat jalannya untuk dibawa pengantar.',
            ],
            'catat' => 'Tersedia juga jalur <strong>Tanpa Pesanan Penjualan</strong> untuk kiriman contoh barang atau penjualan langsung — customer, gudang, dan produknya dipilih sendiri.',
            'periksa' => [
                'Stok berkurang sebanyak yang dikirim.',
                'Terbentuk jurnal <strong>HPP</strong> memakai harga pokok rata-rata bergerak, bukan harga jual.',
            ],
        ],
        [
            'peran' => 'Akuntansi',
            'modul' => 'sales',
            'judul' => 'Tagih customer lalu terima pembayaran',
            'langkah' => [
                '<span class="path">Penjualan → Faktur Penjualan (Invoice)</span>, tekan <strong>Dari Surat Jalan</strong>. Pilih customer, centang surat jalan yang ditagih — boleh beberapa sekaligus.',
                'Pilih <strong>Tipe Faktur</strong>. Untuk tipe Jasa, isi tarif PPh 23.',
                'Simpan, lalu <strong>Posting</strong>. Cetak untuk dikirim ke customer.',
                '<span class="path">Penjualan → Pembayaran dari Customer → Buat</span> — pilih akun kas/bank, alokasikan ke faktur, lalu <strong>Posting</strong>.',
            ],
            'tabel' => [
                'kepala' => ['Tipe faktur', 'Perlakuan pajak', 'Judul cetakan'],
                'baris' => [
                    [SalesInvoice::TYPES[SalesInvoice::TYPE_PPN], 'Tiap baris ikut tarif pajak produknya', 'Faktur Pajak'],
                    [SalesInvoice::TYPES[SalesInvoice::TYPE_NON_PPN], 'Seluruh baris dipaksa 0%', 'Invoice'],
                    [SalesInvoice::TYPES[SalesInvoice::TYPE_JASA], 'Ber-PPN, dipotong PPh 23', 'Invoice Jasa'],
                ],
            ],
            'periksa' => [
                'Faktur mencantumkan seluruh surat jalan yang ditagihnya.',
                'Pada tipe Jasa muncul baris <strong>PPh 23</strong> dan <strong>Dibayar Customer</strong> yang lebih kecil dari total.',
                'Surat jalan yang sudah ditagih hilang dari daftar pilihan — tidak bisa tertagih dua kali.',
            ],
        ],
        [
            'peran' => 'Akuntansi',
            'modul' => 'sales',
            'opsional' => true,
            'judul' => 'Proses retur penjualan',
            'langkah' => [
                '<span class="path">Penjualan → Retur Penjualan → Buat Retur</span>, pilih surat jalan yang sudah diposting.',
                'Isi jumlah retur per baris dan tandai kondisinya: <strong>Baik</strong> atau <strong>Rusak</strong>.',
                'Centang <strong>Terbitkan nota kredit</strong> dan pilih faktur yang dipotong.',
                'Simpan, lalu <strong>Posting</strong>.',
            ],
            'periksa' => [
                'Barang <strong>Baik</strong> kembali menambah stok; barang <strong>Rusak</strong> tidak, dan dibebankan sebagai kerugian.',
                'Sisa tagihan faktur berkurang sebesar nilai retur.',
                'Surat jalan asalnya tetap berstatus Diposting — riwayat pengiriman tidak terhapus.',
            ],
        ],
        [
            'peran' => 'Gudang',
            'modul' => 'inventory',
            'opsional' => true,
            'judul' => 'Rapikan persediaan',
            'langkah' => [
                '<span class="path">Stok & Gudang → Transfer Gudang</span> untuk memindahkan barang antar gudang. Nilai persediaan total tidak berubah, jadi tidak ada jurnal.',
                '<span class="path">Stok & Gudang → Penyesuaian Stok</span> untuk stok opname. Isi jumlah hasil hitung fisik; selisihnya dibukukan sebagai laba atau rugi persediaan.',
            ],
            'periksa' => ['Kartu Stok mencatat mutasi transfer dan penyesuaian sebagai baris tersendiri.'],
        ],
        [
            'peran' => 'Akuntansi',
            'modul' => 'accounting',
            'judul' => 'Baca pembukuan',
            'pengantar' => 'Seluruh jurnal berikut terbentuk sendiri dari dokumen yang diposting — tidak ada yang diketik manual.',
            'langkah' => [
                '<span class="path">Akuntansi → Jurnal Umum</span> — telusuri tiap jurnal ke dokumen sumbernya.',
                '<span class="path">Akuntansi → Buku Besar</span> — pilih akun Persediaan, cocokkan mutasinya dengan Kartu Stok.',
                '<span class="path">Akuntansi → Neraca Saldo</span> — debit harus sama dengan kredit.',
                '<span class="path">Akuntansi → Laba Rugi</span> lalu <span class="path">Neraca</span>.',
            ],
            'tabel' => [
                'kepala' => ['Dokumen diposting', 'Debit', 'Kredit'],
                'baris' => [
                    ['Penerimaan Barang', 'Persediaan', 'Penerimaan Belum Ditagih'],
                    ['Faktur Pembelian', 'Penerimaan Belum Ditagih, PPN Masukan', 'Utang Usaha'],
                    ['Pembayaran Supplier', 'Utang Usaha', 'Kas / Bank'],
                    ['Surat Jalan', 'Harga Pokok Penjualan', 'Persediaan'],
                    ['Faktur Penjualan', 'Piutang Usaha', 'Pendapatan, PPN Keluaran'],
                    ['Faktur Jasa', 'Piutang Usaha, Uang Muka PPh 23', 'Pendapatan, PPN Keluaran'],
                    ['Pembayaran Customer', 'Kas / Bank', 'Piutang Usaha'],
                    ['Retur Penjualan', 'Persediaan, Kerugian Barang Rusak', 'Harga Pokok Penjualan'],
                    ['Penyesuaian Stok', 'Persediaan atau Selisih Persediaan', 'Selisih Persediaan atau Persediaan'],
                ],
            ],
            'periksa' => [
                'Neraca Saldo seimbang — selisihnya nol.',
                'Nilai persediaan di laporan sama dengan yang tampil di Stok Barang.',
                'Umur Piutang menampilkan sisa tagihan yang belum dibayar customer.',
            ],
        ],
        [
            'peran' => 'Super Admin',
            'modul' => null,
            'judul' => 'Sebelum dipakai sungguhan',
            'langkah' => [
                'Ganti kata sandi seluruh akun, atau hapus yang tidak dipakai di <span class="path">Pengaturan → Pengguna</span>.',
                'Setel <strong>APP_ENV=production</strong> dan <strong>APP_DEBUG=false</strong> pada berkas <span class="path">.env</span> — panel akun di halaman login ikut hilang.',
                'Sesuaikan <span class="path">Pengaturan → Format Nomor</span> bila perusahaan punya pola penomoran sendiri.',
                'Kunci bulan yang sudah tutup buku lewat <span class="path">Akuntansi → Periode Fiskal</span>.',
                'Siapkan pencadangan basis data berkala.',
            ],
            'periksa' => ['Halaman login tidak lagi menampilkan daftar akun uji coba.'],
        ],
    ],
];
