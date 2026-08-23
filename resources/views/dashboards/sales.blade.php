@extends('layouts.app')

@section('title', 'Dashboard Penjualan')
@section('pretitle', 'Penjualan · ' . now()->translatedFormat('F Y'))

@section('actions')
    <div class="btn-list">
        @can('sales-order.create')
            <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Pesanan Baru
            </a>
        @endcan
        @can('sales-invoice.create')
            <a href="{{ route('sales-invoices.create') }}" class="btn">
                <i class="ti ti-file-invoice me-1"></i> Faktur Baru
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
            <x-stat label="Omzet bulan ini" :value="rupiah($stats['omzet_bulan_ini'])"
                    icon="ti ti-trending-up" color="green" :href="$keLaporan('reports.sales')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Piutang belum tertagih" :value="rupiah($stats['piutang'])"
                    icon="ti ti-cash" color="orange" :href="$keLaporan('reports.receivable-aging')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Piutang lewat jatuh tempo" :value="rupiah($stats['piutang_jatuh_tempo'])"
                    icon="ti ti-alert-triangle" :color="$stats['piutang_jatuh_tempo'] > 0 ? 'red' : 'secondary'"
                    hint="Sudah melewati tanggal jatuh tempo"
                    :href="$keLaporan('reports.receivable-aging')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Pesanan berjalan" :value="fnum($stats['so_terbuka'], 0)"
                    icon="ti ti-clipboard-check" color="azure" hint="Belum selesai dikirim"
                    :href="route('sales-orders.index')" />
        </div>
    </div>

@endif

    <div class="mb-3">
        <x-pipeline title="Alur Penjualan" subtitle="Angka menunjukkan dokumen yang menunggu dikerjakan"
                    :steps="$alur" />
    </div>

@if($bolehUang)
    <div class="row row-cards mb-3">
        <div class="col-lg-8">
            <x-card title="Tren Penjualan" subtitle="12 bulan terakhir, faktur terposting">
                <div id="grafik-penjualan" style="height:16rem"></div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Customer Teratas" subtitle="Bulan ini">
                @if($customerTeratas->isEmpty())
                    <x-empty title="Belum ada penjualan bulan ini"
                             message="Faktur terposting akan muncul di sini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <tbody>
                            @foreach($customerTeratas as $c)
                                <tr>
                                    <td>
                                        <div>{{ $c->name }}</div>
                                        <div class="text-secondary small">
                                            @if($c->initial)<span class="font-monospace">{{ $c->initial }}</span> · @endif
                                            {{ $c->dokumen }} faktur
                                        </div>
                                    </td>
                                    <td class="text-num fw-bold">{{ rupiah($c->nilai) }}</td>
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
            <x-card title="Faktur Jatuh Tempo" subtitle="Sudah lewat atau dalam 7 hari ke depan">
                @if($jatuhTempo->isEmpty())
                    <x-empty title="Tidak ada tagihan mendesak" icon="ti ti-circle-check"
                             message="Semua faktur masih dalam tenggat." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Faktur</th>
                                <th>Customer</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-num">Sisa</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($jatuhTempo as $f)
                                <tr>
                                    <td><a href="{{ route('sales-invoices.show', $f) }}">{{ $f->invoice_no }}</a></td>
                                    <td>{{ $f->customer?->name ?? '—' }}</td>
                                    <td>
                                        {{ fdate($f->due_date) }}
                                        @if($f->isOverdue())
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

        <div class="col-lg-6">
            <x-card title="Produk Terlaris" subtitle="Bulan ini, berdasarkan nilai">
                @if($produkTeratas->isEmpty())
                    <x-empty title="Belum ada penjualan bulan ini"
                             message="Peringkat muncul setelah faktur diposting." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-num">Qty</th>
                                <th class="text-num">Nilai</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($produkTeratas as $p)
                                <tr>
                                    <td>
                                        <div>{{ $p->name }}</div>
                                        <div class="text-secondary small font-monospace">{{ $p->sku }}</div>
                                    </td>
                                    <td class="text-num">{{ fnum($p->qty) }}</td>
                                    <td class="text-num fw-bold">{{ rupiah($p->nilai) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        @endif

        <div class="col-12">
            <x-card title="Pesanan Penjualan Terbaru">
                @if($soTerbaru->isEmpty())
                    <x-empty title="Belum ada pesanan penjualan"
                             message="Pesanan yang dibuat akan tampil di sini.">
                        @can('sales-order.create')
                            <x-slot:action>
                                <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">Buat Pesanan</a>
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
                                <th>Customer</th>
                                <th>Status</th>
                                <th class="text-num">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($soTerbaru as $so)
                                <tr>
                                    <td><a href="{{ route('sales-orders.show', $so) }}">{{ $so->so_no }}</a></td>
                                    <td>{{ fdate($so->date) }}</td>
                                    <td>{{ $so->customer?->name ?? '—' }}</td>
                                    <td><x-status :value="$so->status" /></td>
                                    <td class="text-num">{{ rupiah($so->total) }}</td>
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
            const wadah = document.getElementById('grafik-penjualan');

            if (! wadah) {
                return;
            }

            // erp.gambarGrafik memuat ApexCharts secara malas; memanggil
            // ApexCharts langsung di sini akan gagal karena pustakanya belum ada
            // saat DOMContentLoaded.
            window.erp.gambarGrafik(wadah, {
                chart: { type: 'area', height: 256, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Penjualan', data: @json($grafik['series']['Penjualan']) }],
                xaxis: { categories: @json($grafik['labels']) },
                yaxis: { labels: { formatter: (v) => v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : new Intl.NumberFormat('id-ID').format(v) } },
                tooltip: { y: { formatter: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                colors: ['#2fb344'],
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { strokeDashArray: 4 },
            });
        });
    </script>
@endpush
