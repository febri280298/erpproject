@extends('layouts.app')

@section('title', 'Buku Panduan')
@section('pretitle', 'Bantuan')
@section('subtitle', 'Alur kerja sistem dari data master sampai laporan keuangan')

@section('actions')
    <button type="button" class="btn" onclick="window.print()">
        <i class="ti ti-printer me-1"></i> Cetak
    </button>
@endsection

@push('styles')
    <style>
        .path {
            font-family: var(--tblr-font-monospace);
            font-size: .85em;
            background: var(--tblr-bg-surface-secondary);
            border: 1px solid var(--tblr-border-color);
            border-radius: 3px;
            padding: .05em .35em;
        }
        .doc { font-family: var(--tblr-font-monospace); font-weight: 600; }
        .tahap-nomor {
            font-family: var(--tblr-font-monospace);
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        @media print {
            .panduan-daftar { display: none !important; }
            .card { break-inside: avoid; }
        }
    </style>
@endpush

@section('content')
    @php
        // Tahap yang modulnya dimatikan disembunyikan, lalu sisanya dinomori ulang
        // supaya urutannya tetap rapat tanpa nomor yang bolong.
        $tahapan = collect(config('manual.tahap'))
            ->filter(fn ($t) => $modules->enabled($t['modul'] ?? null))
            ->values();

        $peranMeta = config('manual.peran');
        $warna = fn (string $peran) => $peranMeta[$peran]['warna'] ?? 'secondary';
    @endphp

    <div class="row row-cards">
        <div class="col-lg-3 panduan-daftar">
            <div class="card sticky-top" style="top:5rem">
                <div class="card-header"><h3 class="card-title">Daftar Tahap</h3></div>
                <div class="list-group list-group-flush">
                    @foreach($tahapan as $i => $tahap)
                        <a href="#tahap-{{ $i + 1 }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-start py-2">
                            <span class="badge bg-{{ $warna($tahap['peran']) }}-lt">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="flex-fill lh-sm">
                                {{ $tahap['judul'] }}
                                <span class="d-block text-secondary" style="font-size:.72rem">{{ $tahap['peran'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <x-card title="Akun & Pembagian Wewenang" class="mb-3"
                    subtitle="Sistem memisahkan wewenang: satu peran tidak bisa menyelesaikan seluruh alur sendirian.">
                <div class="row g-2">
                    @foreach($peranMeta as $nama => $meta)
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100" style="border-left:3px solid var(--tblr-{{ $meta['warna'] }}) !important">
                                <div class="fw-bold">{{ $nama }}</div>
                                <div class="text-secondary small font-monospace">{{ $meta['email'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            @foreach($tahapan as $i => $tahap)
                <div class="card mb-3" id="tahap-{{ $i + 1 }}">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-start mb-3">
                            <div class="tahap-nomor text-{{ $warna($tahap['peran']) }}">
                                {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                            </div>
                            <div class="flex-fill">
                                <h3 class="mb-1">{{ $tahap['judul'] }}</h3>
                                <span class="badge bg-{{ $warna($tahap['peran']) }}-lt">{{ $tahap['peran'] }}</span>
                                @if($tahap['opsional'] ?? false)
                                    <span class="badge bg-secondary-lt">opsional</span>
                                @endif
                            </div>
                        </div>

                        @isset($tahap['pengantar'])
                            <p class="text-secondary">{{ $tahap['pengantar'] }}</p>
                        @endisset

                        <ol class="mt-2 mb-0 ps-3">
                            @foreach($tahap['langkah'] as $langkah)
                                {{-- Isi panduan ditulis sendiri di config, bukan masukan pengguna --}}
                                <li class="mb-2 text-secondary">{!! $langkah !!}</li>
                            @endforeach
                        </ol>

                        @isset($tahap['tabel'])
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                    <tr>
                                        @foreach($tahap['tabel']['kepala'] as $kepala)
                                            <th>{{ $kepala }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($tahap['tabel']['baris'] as $baris)
                                        <tr>
                                            @foreach($baris as $sel)
                                                <td>{{ $sel }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endisset

                        @isset($tahap['catat'])
                            <div class="alert alert-warning mt-3 mb-0 py-2">{!! $tahap['catat'] !!}</div>
                        @endisset

                        @isset($tahap['periksa'])
                            <div class="alert alert-success mt-3 mb-0">
                                <h4 class="alert-title">Pastikan sebelum lanjut</h4>
                                <ul class="mb-0 mt-1">
                                    @foreach($tahap['periksa'] as $periksa)
                                        <li>{!! $periksa !!}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endisset
                    </div>
                </div>
            @endforeach

            <div class="text-secondary small">
                Panduan ini mengikuti modul yang sedang aktif — tahap milik modul yang dimatikan
                tidak ditampilkan. SEMI ERP v{{ config('erp.version') }}.
            </div>
        </div>
    </div>
@endsection
