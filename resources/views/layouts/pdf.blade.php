<!doctype html>
{{--
    Layout untuk unduhan PDF (dompdf).

    Isinya sengaja sama persis dengan layouts/print — gaya dari partial yang
    sama, isi dari partials/print-body yang sama — supaya berkas PDF dan hasil
    cetak peramban tidak pernah berbeda rupa.

    Tiga hal yang berbeda, ketiganya karena keterbatasan dompdf:

    1. Bundel Vite tidak dimuat. dompdf akan mengurai seluruh CSS Tabler
       764 KB untuk dokumen yang sebenarnya hanya memakai gaya di
       partials/print-css: lambat, dan sebagian aturannya justru salah
       diterjemahkan.

    2. Flexbox diganti tabel. dompdf tidak mengenal display:flex sama sekali
       dan mengabaikannya tanpa peringatan — bidang yang seharusnya
       berdampingan akan menumpuk ke bawah, dan itu baru ketahuan setelah
       PDF-nya dibuka.

    3. Logo dibaca dari jalur berkas, bukan URL. dompdf menolak sumber jarak
       jauh kecuali isRemoteEnabled dinyalakan, dan menyalakannya berarti
       pembuat dokumen boleh menarik berkas dari alamat mana pun.
--}}
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Dokumen')</title>

    <style>
        @include('partials.print-css')

        /* ==================== penyesuaian untuk dompdf ==================== */

        /*
         * Halaman PDF sudah berukuran A4 bermargin, jadi .sheet tidak perlu
         * lagi menggambar lembar kertas di atas latar abu-abu seperti di layar.
         */
        body {
            background: #fff;
        }

        .sheet {
            width: auto;
            min-height: 0;
            margin: 0;
            padding: 0;
            background: transparent;
            box-shadow: none;
        }

        /* Satu-satunya utilitas Bootstrap yang dipakai partials/print-body. */
        .mt-3 {
            margin-top: 1rem;
        }
    </style>
</head>
<body>

@php
    /*
     * Jalur berkas, bukan URL — lihat catatan (3) di atas. Keberadaannya
     * diperiksa karena logo yang sudah dihapus dari Pengaturan tetap
     * meninggalkan nama berkasnya, dan dompdf menggagalkan seluruh dokumen
     * bila sumber gambarnya tidak ada.
     */
    $logoPdf = ! empty($company['logo']) && is_file(public_path('storage/'.$company['logo']))
        ? public_path('storage/'.$company['logo'])
        : null;
@endphp

<div class="sheet">
    <table class="letterhead">
        <tr>
            <td>
                <table class="letterhead-brand">
                    <tr>
                        @if($logoPdf)
                            <td class="letterhead-logo">
                                <img src="{{ $logoPdf }}" alt="">
                            </td>
                        @endif
                        <td>
                            <div class="company-name">{{ $company['name'] }}</div>
                            <div class="company-detail">
                                {!! nl2br(e($company['address'])) !!}
                                @if($company['phone'])<br>Telp {{ $company['phone'] }}@endif
                                @if($company['email']) &middot; {{ $company['email'] }} @endif
                                @if($company['npwp'])<br>NPWP {{ $company['npwp'] }}@endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td>
                <div class="doc-type">@yield('doc-title')</div>
                <div class="doc-no">@yield('doc-subtitle')</div>
            </td>
        </tr>
    </table>

    <div class="letterhead-rule"></div>

    @yield('content')

    <div class="doc-footnote">
        Dicetak dari {{ $company['name'] }} &middot; @yield('doc-subtitle')
        &middot; {{ now()->translatedFormat('d F Y H:i') }} WIB
    </div>
</div>

</body>
</html>
