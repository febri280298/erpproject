@extends('layouts.app')

@section('title', 'Dashboard Pembelian')
@section('pretitle', 'Pembelian · ' . now()->translatedFormat('F Y'))

@section('actions')
    <div class="btn-list">
        @can('purchase-order.create')
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> PO Baru
            </a>
        @endcan
        @can('goods-receipt.create')
            <a href="{{ route('goods-receipts.create') }}" class="btn">
                <i class="ti ti-package-import me-1"></i> Terima Barang
            </a>
        @endcan
    </div>
@endsection

@section('content')
@php
    /**
     * Tautan ke Laporan hanya diberikan bila modul Laporan menyala DAN pengguna
     * berhak melihatnya. Tanpa penjagaan ini, mematikan modul Laporan
     * meninggalkan tautan yang berujung 404 di dashboard.
     */
    $keLaporan = fn (string $rute) => $modules->enabled('reports') && auth()->user()->can('report.view')
        ? route($rute)
        : null;
@endphp

@if($bolehUang)
    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Belanja bulan ini" :value="rupiah($stats['belanja_bulan_ini'])"
                    icon="ti ti-shopping-cart" color="azure" :href="$keLaporan('reports.purchasing')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Utang belum dibayar" :value="rupiah($stats['utang'])"
                    icon="ti ti-cash" color="orange" :href="$keLaporan('reports.payable-aging')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Utang lewat jatuh tempo" :value="rupiah($stats['utang_jatuh_tempo'])"
                    icon="ti ti-alert-triangle" :color="$stats['utang_jatuh_tempo'] > 0 ? 'red' : 'secondary'"
                    hint="Sudah melewati tanggal jatuh tempo"
                    :href="$keLaporan('reports.payable-aging')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="PO berjalan" :value="fnum($stats['po_terbuka'], 0)"
                    icon="ti ti-clipboard-check" color="blue" hint="Belum lengkap diterima"
                    :href="route('purchase-orders.index')" />
        </div>
    </div>

@endif

    <div class="mb-3">
        <x-pipeline title="Alur Pembelian" subtitle="Angka menunjukkan dokumen yang menunggu dikerjakan"
                    :steps="$alur" />
    </div>

@if($bolehUang)
    <div class="row row-cards mb-3">
        <div class="col-lg-8">
            <x-card title="Tren Pembelian" subtitle="12 bulan terakhir, faktur terposting">
                <div id="grafik-pembelian" style="height:16rem"></div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Supplier Teratas" subtitle="Bulan ini">
                @if($supplierTeratas->isEmpty())
                    <x-empty title="Belum ada pembelian bulan ini"
                             message="Faktur terposting akan muncul di sini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <tbody>
                            @foreach($supplierTeratas as $s)
                                <tr>
                                    <td>
                                        <div>{{ $s->name }}</div>
                                        <div class="text-secondary small">
                                            @if($s->initial)<span class="font-monospace">{{ $s->initial }}</span> · @endif
                                            {{ $s->dokumen }} faktur
                                        </div>
                                    </td>
                                    <td class="text-num fw-bold">{{ rupiah($s->nilai) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

@endif

    <div class="row row-cards">
        @if($bolehUang)
        <div class="col-lg-6">
            <x-card title="Tagihan Jatuh Tempo" subtitle="Sudah lewat atau dalam 7 hari ke depan">
                @if($jatuhTempo->isEmpty())
                    <x-empty title="Tidak ada tagihan mendesak" icon="ti ti-circle-check"
                             message="Semua tagihan supplier masih dalam tenggat." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Faktur</th>
                                <th>Supplier</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-num">Sisa</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($jatuhTempo as $f)
                                <tr>
                                    <td><a href="{{ route('purchase-invoices.show', $f) }}">{{ $f->invoice_no }}</a></td>
                                    <td>{{ $f->supplier?->name ?? '—' }}</td>
                                    <td>
                                        {{ fdate($f->due_date) }}
                                        @if($f->due_date && $f->due_date->isPast())
                                            <span class="badge bg-red-lt ms-1">telat</span>
                                        @endif
                                    </td>
                                    <td class="text-num">{{ rupiah($f->total - $f->paid_amount) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        @endif

        <div class="{{ $bolehUang ? 'col-lg-6' : 'col-12' }}">
            <x-card title="Pesanan Pembelian Terbaru">
                @if($poTerbaru->isEmpty())
                    <x-empty title="Belum ada pesanan pembelian"
                             message="PO yang dibuat akan tampil di sini.">
                        @can('purchase-order.create')
                            <x-slot:action>
                                <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">Buat PO</a>
                            </x-slot:action>
                        @endcan
                    </x-empty>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th class="text-num">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($poTerbaru as $po)
                                <tr>
                                    <td><a href="{{ route('purchase-orders.show', $po) }}">{{ $po->po_no }}</a></td>
                                    <td>{{ fdate($po->date) }}</td>
                                    <td>{{ $po->supplier?->name ?? '—' }}</td>
                                    <td><x-status :value="$po->status" /></td>
                                    <td class="text-num">{{ rupiah($po->total) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wadah = document.getElementById('grafik-pembelian');

            if (! wadah) {
                return;
            }

            // erp.gambarGrafik memuat ApexCharts secara malas; memanggil
            // ApexCharts langsung di sini akan gagal karena pustakanya belum ada
            // saat DOMContentLoaded.
            window.erp.gambarGrafik(wadah, {
                chart: { type: 'area', height: 256, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Pembelian', data: @json($grafik['series']['Pembelian']) }],
                xaxis: { categories: @json($grafik['labels']) },
                yaxis: { labels: { formatter: (v) => v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : new Intl.NumberFormat('id-ID').format(v) } },
                tooltip: { y: { formatter: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                colors: ['#4299e1'],
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { strokeDashArray: 4 },
            });
        });
    </script>
@endpush
