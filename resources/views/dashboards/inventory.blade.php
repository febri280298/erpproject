@extends('layouts.app')

@section('title', 'Dashboard Stok & Gudang')
@section('pretitle', 'Stok & Gudang · ' . now()->translatedFormat('d F Y'))

@section('actions')
    <div class="btn-list">
        @can('stock.view')
            <a href="{{ route('stocks.index') }}" class="btn btn-primary">
                <i class="ti ti-list me-1"></i> Stok Barang
            </a>
        @endcan
        @can('stock-adjustment.create')
            <a href="{{ route('stock-adjustments.create') }}" class="btn">
                <i class="ti ti-adjustments me-1"></i> Penyesuaian
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

    @if($stats['negatif'] > 0)
        <div class="alert alert-danger" role="alert">
            <div class="d-flex">
                <div><i class="ti ti-alert-triangle me-2"></i></div>
                <div>
                    <h4 class="alert-title">Ada {{ fnum($stats['negatif'], 0) }} baris stok bernilai negatif</h4>
                    <div>
                        Stok tidak boleh kurang dari nol. Biasanya berarti pengeluaran diposting sebelum
                        penerimaannya, atau ada dokumen yang terposting dua kali.
                    </div>
                    @can('stock.view')
                        <div class="mt-2">
                            <a href="{{ route('stocks.index') }}" class="btn btn-sm btn-danger">Periksa stok</a>
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Nilai persediaan" :value="rupiah($stats['nilai_persediaan'])"
                    icon="ti ti-building-warehouse" color="purple" hint="Harga pokok rata-rata"
                    :href="$keLaporan('reports.inventory')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Produk stok aktif" :value="fnum($stats['produk_aktif'], 0)"
                    icon="ti ti-box" color="blue" :href="route('products.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Di bawah stok minimum" :value="fnum($stats['menipis'], 0)"
                    icon="ti ti-arrow-down" :color="$stats['menipis'] > 0 ? 'orange' : 'secondary'"
                    hint="Perlu dipesan ulang" :href="route('stocks.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Stok negatif" :value="fnum($stats['negatif'], 0)"
                    icon="ti ti-alert-triangle" :color="$stats['negatif'] > 0 ? 'red' : 'green'"
                    hint="Seharusnya selalu nol" :href="route('stocks.index')" />
        </div>
    </div>

    <div class="mb-3">
        <x-pipeline title="Dokumen Stok Tertahan" subtitle="Draft yang belum diposting — stoknya belum bergerak"
                    :steps="$alur" />
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-7">
            <x-card title="Stok Menipis" subtitle="Sisa di bawah batas minimum">
                @if($stokMenipis->isEmpty())
                    <x-empty title="Semua stok aman" icon="ti ti-circle-check"
                             message="Tidak ada barang di bawah batas minimumnya." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Gudang</th>
                                <th class="text-num">Sisa</th>
                                <th class="text-num">Minimum</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($stokMenipis as $s)
                                <tr>
                                    <td>
                                        <div>{{ $s->product?->name ?? '—' }}</div>
                                        <div class="text-secondary small font-monospace">{{ $s->product?->sku }}</div>
                                    </td>
                                    <td class="text-secondary">{{ $s->warehouse?->name ?? '—' }}</td>
                                    <td class="text-num text-danger fw-bold">
                                        {{ fnum($s->quantity) }} {{ $s->product?->uom?->code }}
                                    </td>
                                    <td class="text-num text-secondary">{{ fnum($s->product?->min_stock) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card title="Nilai per Gudang">
                @if($perGudang->isEmpty())
                    <x-empty title="Belum ada stok tercatat"
                             message="Nilai muncul setelah penerimaan barang diposting." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Gudang</th>
                                <th class="text-num">Item</th>
                                <th class="text-num">Nilai</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($perGudang as $g)
                                <tr>
                                    <td>{{ $g->name }}</td>
                                    <td class="text-num text-secondary">{{ fnum($g->produk, 0) }}</td>
                                    <td class="text-num fw-bold">{{ rupiah($g->nilai) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-7">
            <x-card title="Pergerakan Stok Terakhir">
                @if($pergerakanTerakhir->isEmpty())
                    <x-empty title="Belum ada pergerakan stok"
                             message="Setiap penerimaan dan pengiriman akan tercatat di sini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Produk</th>
                                <th>Gudang</th>
                                <th>Referensi</th>
                                <th class="text-num">Qty</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($pergerakanTerakhir as $m)
                                <tr>
                                    <td class="text-secondary">{{ fdate($m->date) }}</td>
                                    <td>{{ $m->product?->name ?? '—' }}</td>
                                    <td class="text-secondary">{{ $m->warehouse?->name ?? '—' }}</td>
                                    <td class="text-secondary small">{{ $m->ref_no ?? '—' }}</td>
                                    <td class="text-num {{ $m->direction === 'in' ? 'text-success' : 'text-danger' }}">
                                        {{ $m->direction === 'in' ? '+' : '−' }}{{ fnum($m->quantity) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card title="Tidak Bergerak 90 Hari" subtitle="Modal yang mengendap di gudang">
                @if($tanpaPergerakan->isEmpty())
                    <x-empty title="Semua barang bergerak" icon="ti ti-circle-check"
                             message="Tidak ada stok yang diam lebih dari 90 hari." />
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
                            @foreach($tanpaPergerakan as $p)
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
    </div>
@endsection
