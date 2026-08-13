<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dokumen') · {{ $company['name'] ?? config('app.name') }}</title>

    @vite(['resources/scss/app.scss'])

    <style>
        /*
         * A4 portrait, 12 mm margins → 186 mm of usable width.
         * On screen the sheet is drawn at exactly that size so what the user
         * sees is what the printer produces.
         */
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 14mm;
        }

        :root {
            --sheet-width: 186mm;
        }

        body {
            background: #f2f3f5;
            color: #000;
            font-size: 10pt;
            line-height: 1.45;
        }

        .sheet {
            width: var(--sheet-width);
            min-height: 273mm;              /* 297 mm − margin atas & bawah */
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

        .doc-title {
            font-size: 15pt;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .letterhead {
            border-bottom: 2px solid #000;
            padding-bottom: 6mm;
            margin-bottom: 6mm;
        }

        .sheet table {
            font-size: 9.5pt;
            width: 100%;
        }

        .sheet .table > :not(caption) > * > * {
            padding: 1.6mm 2mm;
        }

        .sheet .table-bordered > :not(caption) > * > * {
            border: .5pt solid #adb5bd;
        }

        /* Keep rows, totals and the signature strip from splitting across pages. */
        thead {
            display: table-header-group;
        }

        tr,
        .avoid-break {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signatures {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-top: 12mm;
        }

        .doc-footnote {
            margin-top: 8mm;
            padding-top: 3mm;
            border-top: .5pt solid #dee2e6;
            font-size: 8pt;
            color: #6c757d;
        }

        /* Below A4 width the preview would scroll sideways; printing stays exact. */
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

            /* Keep table shading and badges legible on paper. */
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
    {{-- Kop surat --}}
    <div class="letterhead">
        <div class="row align-items-start">
            <div class="col-7">
                <div class="d-flex align-items-start gap-2">
                    @if(! empty($company['logo']))
                        <img src="{{ asset('storage/'.$company['logo']) }}" alt="" style="max-height:14mm">
                    @endif
                    <div>
                        <div class="doc-title">{{ $company['name'] }}</div>
                        <div style="font-size:8.5pt; line-height:1.35">
                            {!! nl2br(e($company['address'])) !!}
                            @if($company['phone'])<br>Telp: {{ $company['phone'] }}@endif
                            @if($company['email']) &middot; {{ $company['email'] }} @endif
                            @if($company['npwp'])<br>NPWP: {{ $company['npwp'] }}@endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="doc-title text-uppercase">@yield('doc-title')</div>
                <div style="font-size:10pt">@yield('doc-subtitle')</div>
            </div>
        </div>
    </div>

    @yield('content')

    <div class="doc-footnote">
        Dicetak dari {{ $company['name'] }} · @yield('doc-subtitle') · {{ now()->translatedFormat('d F Y H:i') }} WIB
    </div>
</div>

</body>
</html>
