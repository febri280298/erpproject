@extends('layouts.app')

@section('title', 'Dashboard Data Master')
@section('pretitle', 'Data Master')

@section('actions')
    <div class="btn-list">
        @can('product.create')
            <a href="{{ route('products.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Produk Baru
            </a>
        @endcan
        @can('partner.create')
            <a href="{{ route('partners.create') }}" class="btn">
                <i class="ti ti-users me-1"></i> Mitra Baru
            </a>
        @endcan
    </div>
@endsection

@section('content')
@php
    /**
     * Tautan hanya diberikan kepada yang berhak membuka tujuannya. Peran Gudang
     * memegang product.view tetapi tidak partner.view; tanpa penjagaan ini
     * kartunya menaut ke halaman yang menolaknya dengan 403.
     */
    $pengguna = auth()->user();
    $keMitra = $pengguna->can('partner.view') ? route('partners.index') : null;
    $keGudang = $pengguna->can('warehouse.view') ? route('warehouses.index') : null;
@endphp

    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Produk aktif" :value="fnum($stats['produk'], 0)"
                    icon="ti ti-box" color="blue" :href="route('products.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Customer" :value="fnum($stats['customer'], 0)"
                    icon="ti ti-user-check" color="green" :href="$keMitra" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Supplier" :value="fnum($stats['supplier'], 0)"
                    icon="ti ti-truck-delivery" color="azure" :href="$keMitra" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Gudang aktif" :value="fnum($stats['gudang'], 0)"
                    icon="ti ti-building-warehouse" color="purple" :href="$keGudang" />
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-7">
            <x-card title="Kelengkapan Data"
                    subtitle="Data yang belum lengkap baru terasa saat dipakai membuat dokumen">
                @php $adaMasalah = collect($kelengkapan)->sum('count') > 0; @endphp

                @if(! $adaMasalah)
                    <x-empty title="Data master sudah lengkap" icon="ti ti-circle-check"
                             message="Tidak ada isian penting yang kosong." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <tbody>
                            @foreach($kelengkapan as $k)
                                <tr class="{{ $k['count'] > 0 ? '' : 'text-secondary' }}">
                                    <td>
                                        <div>{{ $k['label'] }}</div>
                                        <div class="text-secondary small">{{ $k['catatan'] }}</div>
                                    </td>
                                    <td class="text-num" style="width:6rem">
                                        @if($k['count'] > 0 && $k['href'])
                                            <a href="{{ $k['href'] }}" class="badge bg-orange-lt">
                                                {{ fnum($k['count'], 0) }}
                                            </a>
                                        @elseif($k['count'] > 0)
                                            <span class="badge bg-orange-lt">{{ fnum($k['count'], 0) }}</span>
                                        @else
                                            <span class="text-green"><i class="ti ti-check"></i></span>
                                        @endif
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
            <x-card title="Produk per Kategori">
                @if($perKategori->isEmpty())
                    <x-empty title="Belum ada produk"
                             message="Tambahkan produk untuk melihat sebarannya." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <tbody>
                            @php $terbanyak = max(1, $perKategori->max('jumlah')); @endphp
                            @foreach($perKategori as $k)
                                <tr>
                                    <td style="width:40%">{{ $k->nama }}</td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-primary"
                                                 style="width: {{ round($k->jumlah / $terbanyak * 100) }}%"
                                                 role="progressbar"
                                                 aria-valuenow="{{ $k->jumlah }}"
                                                 aria-valuemin="0" aria-valuemax="{{ $terbanyak }}"
                                                 aria-label="{{ $k->nama }}"></div>
                                        </div>
                                    </td>
                                    <td class="text-num" style="width:3rem">{{ fnum($k->jumlah, 0) }}</td>
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
        <div class="{{ $pengguna->can('partner.view') ? 'col-lg-7' : 'col-12' }}">
            <x-card title="Produk Terbaru">
                @if($produkTerbaru->isEmpty())
                    <x-empty title="Belum ada produk"
                             message="Produk yang ditambahkan akan tampil di sini.">
                        @can('product.create')
                            <x-slot:action>
                                <a href="{{ route('products.create') }}" class="btn btn-primary">Tambah Produk</a>
                            </x-slot:action>
                        @endcan
                    </x-empty>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th class="text-num">Harga Jual</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($produkTerbaru as $p)
                                <tr>
                                    <td class="font-monospace">{{ $p->sku }}</td>
                                    <td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td>
                                    <td class="text-secondary">{{ $p->category?->name ?? '—' }}</td>
                                    <td class="text-num">{{ rupiah($p->sale_price) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        @can('partner.view')
        <div class="col-lg-5">
            <x-card title="Mitra Terbaru">
                @if($mitraTerbaru->isEmpty())
                    <x-empty title="Belum ada mitra"
                             message="Customer dan supplier akan tampil di sini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Tipe</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($mitraTerbaru as $m)
                                <tr>
                                    <td class="font-monospace">
                                        {{ $m->code }}
                                        @if($m->initial)
                                            <span class="badge bg-secondary-lt ms-1">{{ $m->initial }}</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ route('partners.show', $m) }}">{{ $m->name }}</a></td>
                                    <td class="text-secondary">{{ $m->typeLabel() }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
        @endcan
    </div>
@endsection
