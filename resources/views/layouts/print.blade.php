<!doctype html>
{{-- Halaman cetak selalu terang: kertas tidak punya mode gelap. --}}
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dokumen') · {{ $company['name'] ?? config('app.name') }}</title>

    @vite(['resources/scss/app.scss'])

    <style>
        /*
         * Dokumen cetak — A4 potret, margin 12 mm, lebar terpakai 186 mm.
         *
         * Sengaja satu warna: hitam untuk isi, satu abu-abu untuk label dan
         * bidang. Dokumen pajak yang berwarna-warni terbaca tidak resmi, dan
         * warna latar memboroskan tinta pada cetakan massal. Keterbacaannya
         * datang dari hierarki ukuran huruf dan ruang kosong, bukan dari warna.
         *
         * Di layar, kertasnya digambar persis seukuran hasil cetak supaya yang
         * dilihat sama dengan yang keluar dari printer.
         */
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 14mm;
        }

        :root {
            --sheet-width: 186mm;
            --tinta: #111827;          /* hitam lembut; hitam murni terlihat kasar di kertas */
            --redup: #6b7280;          /* label dan keterangan */
            --garis: #d1d5db;          /* garis rambut antar baris */
            --bidang: #f3f4f6;         /* bidang kepala tabel & panel total */
        }

        body {
            background: #e9eaec;
            color: var(--tinta);
            font-size: 9.5pt;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
        }

        .sheet {
            width: var(--sheet-width);
            min-height: 273mm;                  /* 297 mm − margin atas & bawah */
            margin: 1.5rem auto;
            padding: 10mm;
            background: #fff;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .08), 0 .5rem 1.5rem rgba(0, 0, 0, .12);
        }

        .print-toolbar {
            width: var(--sheet-width);
            margin: 1.5rem auto 0;
            display: flex;
            gap: .5rem;
            align-items: center;
        }

        /* ----------------------------------------------------------- Kop surat */

        .letterhead {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8mm;
            padding-bottom: 5mm;
        }

        /*
         * Garis ganda tebal-tipis: perangkat kertas surat klasik yang langsung
         * membedakan dokumen resmi dari cetakan halaman web.
         */
        .letterhead-rule {
            border-top: 1.6pt solid var(--tinta);
            border-bottom: .5pt solid var(--tinta);
            height: 1.2mm;
            margin-bottom: 6mm;
        }

        .company-name {
            font-size: 12.5pt;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -.01em;
        }

        .company-detail {
            font-size: 8pt;
            line-height: 1.4;
            color: var(--redup);
            margin-top: 1mm;
        }

        /* Jenis dokumen adalah hal pertama yang dicari orang, jadi paling besar. */
        .doc-type {
            font-size: 19pt;
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .doc-no {
            font-size: 9.5pt;
            color: var(--redup);
            margin-top: 1mm;
            font-variant-numeric: tabular-nums;
        }

        /* ------------------------------------------------- Pihak & keterangan */

        .party {
            display: flex;
            gap: 8mm;
            margin-bottom: 6mm;
        }

        .party > * {
            flex: 1 1 0;
            min-width: 0;
        }

        .label {
            font-size: 7pt;
            font-weight: 600;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--redup);
        }

        .party-name {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.25;
            margin-top: 1mm;
        }

        .party-detail {
            font-size: 8.5pt;
            line-height: 1.4;
            color: var(--redup);
            margin-top: 1mm;
        }

        /* Keterangan dokumen: label redup kiri, nilai tegas kanan, dipisah
           garis rambut agar pasangannya tidak tertukar saat dibaca cepat. */
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1mm;
        }

        .meta td {
            padding: 1.1mm 0;
            border-bottom: .5pt solid var(--garis);
            vertical-align: baseline;
        }

        .meta tr:last-child td {
            border-bottom: 0;
        }

        .meta .k {
            color: var(--redup);
            font-size: 8.5pt;
            white-space: nowrap;
            padding-right: 3mm;
        }

        .meta .v {
            text-align: right;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        /* --------------------------------------------------------- Tabel item */

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            /* Lebih kecil dari teks lain di halaman: satu dokumen sering memuat
               puluhan baris, dan tabel yang padat memuat lebih banyak baris per
               lembar tanpa mengurangi keterbacaan angkanya. */
            font-size: 8pt;
            line-height: 1.3;
        }

        .items thead th {
            background: var(--bidang);
            border-top: .8pt solid var(--tinta);
            border-bottom: .8pt solid var(--tinta);
            padding: 1.2mm 1.6mm;
            font-size: 6.8pt;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--tinta);
            text-align: left;
        }

        .items tbody td {
            padding: 1.3mm 1.6mm;
            border-bottom: .5pt solid var(--garis);
            vertical-align: top;
        }

        /* Baris terakhir ditutup garis tegas: batas bawah tabel harus jelas
           sebelum mata pindah ke blok total. */
        .items tbody tr:last-child td {
            border-bottom: .8pt solid var(--tinta);
        }

        .items .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .items .item-name {
            font-weight: 600;
        }

        /* Abu-abu sendiri, bukan .text-secondary bawaan Tabler yang sedikit
           kebiruan — dalam satu dokumen semua teks redup harus sama persis. */
        .sheet .muted {
            color: var(--redup);
        }

        .items .item-sub {
            font-size: 6.8pt;
            color: var(--redup);
            line-height: 1.3;
        }

        /* ------------------------------------------------------------- Total */

        .totals {
            width: 100%;
            border-collapse: collapse;
            font-variant-numeric: tabular-nums;
        }

        .totals td {
            padding: 1.4mm 2mm;
        }

        .totals .t-label {
            color: var(--redup);
        }

        .totals .t-value {
            text-align: right;
            white-space: nowrap;
        }

        .totals .grand td {
            background: var(--bidang);
            border-top: .8pt solid var(--tinta);
            border-bottom: .8pt solid var(--tinta);
            font-weight: 700;
            font-size: 11pt;
            padding-top: 2mm;
            padding-bottom: 2mm;
        }

        /* Jumlah yang benar-benar ditransfer berbeda dari TOTAL bila ada PPh 23
           atau pembayaran sebagian — dibedakan agar tidak salah bayar. */
        .totals .due td {
            font-weight: 700;
            border-bottom: .8pt double var(--tinta);
        }

        .terbilang {
            border: .5pt solid var(--garis);
            border-left: 1.6pt solid var(--tinta);
            padding: 2mm 3mm;
            font-size: 8.5pt;
            margin-top: 3mm;
        }

        .note-block {
            font-size: 8.5pt;
            color: var(--redup);
            margin-bottom: 3mm;
        }

        .note-block strong {
            color: var(--tinta);
        }

        /* -------------------------------------------------------- Tanda tangan */

        /*
         * Penutup dokumen: tanda tangan di kiri, sisanya di kanan.
         *
         * Dokumen bertanda tangan tunggal (faktur, PO) hanya memakai separuh
         * kiri, sehingga separuh kanannya bisa dipakai informasi rekening.
         * Dokumen bertanda tangan banyak (surat jalan, penerimaan barang) tetap
         * melebar penuh seperti semula.
         */
        .closing {
            display: flex;
            gap: 8mm;
            margin-top: 10mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .closing > * {
            flex: 1 1 0;
            min-width: 0;
        }

        .signatures {
            display: flex;
            gap: 6mm;
            text-align: center;
        }

        .signatures > * {
            flex: 1 1 0;
        }

        /* Blok rekening diberi bingkai bergaris tebal di kiri, sama seperti
           Terbilang — keduanya informasi yang harus langsung ketemu. */
        .pay-box {
            border: .5pt solid var(--garis);
            border-left: 1.6pt solid var(--tinta);
            padding: 2.5mm 3mm;
        }

        .pay-box .meta td {
            padding: .8mm 0;
        }

        .sign-rule {
            border-top: .5pt solid var(--tinta);
            margin: 0 auto;
            width: 80%;
            padding-top: 1mm;
            font-size: 7.5pt;
            color: var(--redup);
        }

        .doc-footnote {
            margin-top: 8mm;
            padding-top: 2.5mm;
            border-top: .5pt solid var(--garis);
            font-size: 7.5pt;
            color: var(--redup);
        }

        /*
         * Kepala tabel diulang pada setiap halaman.
         *
         * table-header-group membuat peramban mencetak ulang <thead> di puncak
         * tiap halaman ketika tabelnya melewati batas kertas. Tanpa ini, halaman
         * kedua dan seterusnya hanya berisi deretan angka tanpa judul kolom —
         * pembaca harus bolak-balik ke halaman pertama untuk tahu kolom mana
         * yang mana.
         */
        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }

        /* Baris tidak boleh terbelah dua halaman. */

        tr,
        .avoid-break {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Di bawah lebar A4 pratinjaunya akan menggulir mendatar; hasil cetak
           tetap persis. */
        @media screen and (max-width: 800px) {
            .sheet,
            .print-toolbar {
                width: auto;
                max-width: 100%;
                margin-left: .75rem;
                margin-right: .75rem;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            /* Bidang abu-abu kepala tabel dan baris TOTAL harus tetap tercetak;
               tanpa ini sebagian printer menghilangkannya. */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="print-toolbar d-print-none">
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="ti ti-printer me-1"></i> Cetak
    </button>
    <a href="{{ url()->previous() }}" class="btn btn-link">Kembali</a>
    <span class="ms-auto text-secondary small">A4 potret &middot; margin 12 mm</span>
</div>

<div class="sheet">
    <div class="letterhead">
        <div class="d-flex align-items-start gap-3">
            @if(! empty($company['logo']))
                <img src="{{ asset('storage/'.$company['logo']) }}" alt="" style="max-height:15mm">
            @endif
            <div>
                <div class="company-name">{{ $company['name'] }}</div>
                <div class="company-detail">
                    {!! nl2br(e($company['address'])) !!}
                    @if($company['phone'])<br>Telp {{ $company['phone'] }}@endif
                    @if($company['email']) &middot; {{ $company['email'] }} @endif
                    @if($company['npwp'])<br>NPWP {{ $company['npwp'] }}@endif
                </div>
            </div>
        </div>
        <div class="text-end">
            <div class="doc-type">@yield('doc-title')</div>
            <div class="doc-no">@yield('doc-subtitle')</div>
        </div>
    </div>

    <div class="letterhead-rule"></div>

    @yield('content')

    <div class="doc-footnote">
        Dicetak dari {{ $company['name'] }} &middot; @yield('doc-subtitle')
        &middot; {{ now()->translatedFormat('d F Y H:i') }} WIB
    </div>
</div>

</body>
</html>
