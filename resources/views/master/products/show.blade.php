@extends('layouts.app')

@section('title', $product->name)
@section('pretitle', 'Produk · ' . $product->sku)

@section('actions')
    <a href="{{ route('stocks.card', ['product_id' => $product->id]) }}" class="btn">
        <i class="ti ti-history me-1"></i> Kartu Stok
    </a>
    @can('product.edit')
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">
            <i class="ti ti-edit me-1"></i> Ubah
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <x-card>
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="img-fluid rounded mb-3">
                @endif

                <h3 class="mb-1">{{ $product->name }}</h3>
                <div class="text-secondary mb-3">{{ $product->sku }}</div>

                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Tipe</dt>
                    <dd class="col-7">{{ $product->isStockable() ? 'Barang' : 'Jasa' }}</dd>
                    <dt class="col-5 text-secondary">Kategori</dt>
                    <dd class="col-7">{{ $product->category?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Satuan</dt>
                    <dd class="col-7">{{ $product->uom?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Pajak</dt>
                    <dd class="col-7">{{ $product->tax ? $product->tax->name.' ('.fnum($product->tax->rate).'%)' : '—' }}</dd>
                    <dt class="col-5 text-secondary">Harga Beli</dt>
                    <dd class="col-7">{{ rupiah($product->purchase_price) }}</dd>
                    <dt class="col-5 text-secondary">Harga Jual</dt>
                    <dd class="col-7 fw-bold">{{ rupiah($product->sale_price) }}</dd>
                    <dt class="col-5 text-secondary">Stok Min.</dt>
                    <dd class="col-7">{{ fnum($product->min_stock) }}</dd>
                    <dt class="col-5 text-secondary">Barcode</dt>
                    <dd class="col-7">{{ $product->barcode ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$product->is_active ? 'active' : 'cancelled'" :label="$product->is_active ? 'Aktif' : 'Nonaktif'" /></dd>
                </dl>

                @if($product->description)
                    <hr>
                    <div class="text-secondary">{!! nl2br(e($product->description)) !!}</div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-8">
            <x-card title="Stok per Gudang" flush>
                @if($product->stocks->isEmpty())
                    <div class="card-body">
                        <x-empty icon="ti ti-package-off" title="Belum ada stok"
                                 message="Stok akan muncul setelah penerimaan barang diposting." />
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Gudang</th>
                                <th class="text-num">Kuantitas</th>
                                <th class="text-num">Harga Pokok Rata-rata</th>
                                <th class="text-num">Nilai Persediaan</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($product->stocks as $stock)
                                <tr>
                                    <td>{{ $stock->warehouse?->name }}</td>
                                    <td class="text-num fw-bold">{{ fnum($stock->quantity) }} {{ $product->uom?->code }}</td>
                                    <td class="text-num">{{ rupiah($stock->avg_cost, 2) }}</td>
                                    <td class="text-num">{{ rupiah($stock->value()) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-num">{{ fnum($product->stocks->sum('quantity')) }}</td>
                                <td></td>
                                <td class="text-num">{{ rupiah($product->stocks->sum(fn($s) => $s->value())) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card title="Pergerakan Stok Terakhir" flush class="mt-3">
                @if($movements->isEmpty())
                    <div class="card-body">
                        <x-empty icon="ti ti-history-off" title="Belum ada pergerakan" message="Riwayat masuk/keluar akan tampil di sini." />
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Referensi</th>
                                <th>Gudang</th>
                                <th class="text-num">Masuk</th>
                                <th class="text-num">Keluar</th>
                                <th class="text-num">Saldo</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($movements as $movement)
                                <tr>
                                    <td>{{ fdate($movement->date) }}</td>
                                    <td>
                                        <div>{{ $movement->ref_no ?? '—' }}</div>
                                        <div class="text-secondary small">{{ $movement->refLabel() }}</div>
                                    </td>
                                    <td class="text-secondary">{{ $movement->warehouse?->name }}</td>
                                    <td class="text-num text-success">{{ $movement->isIn() ? fnum($movement->quantity) : '' }}</td>
                                    <td class="text-num text-danger">{{ $movement->isIn() ? '' : fnum($movement->quantity) }}</td>
                                    <td class="text-num fw-bold">{{ fnum($movement->balance_qty) }}</td>
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
