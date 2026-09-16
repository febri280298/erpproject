<!doctype html>
{{-- Halaman cetak selalu terang: kertas tidak punya mode gelap. --}}
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dokumen') · {{ $company['name'] ?? config('app.name') }}</title>

    @vite(['resources/scss/app.scss'])

    <style>
        @include('partials.print-css')
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
    <table class="letterhead">
        <tr>
            <td>
                <table class="letterhead-brand">
                    <tr>
                        @if(! empty($company['logo']))
                            <td class="letterhead-logo">
                                <img src="{{ asset('storage/'.$company['logo']) }}" alt="">
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
