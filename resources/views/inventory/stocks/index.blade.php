@extends('layouts.app')

@section('title', 'Stok Barang')
@section('pretitle', 'Stok & Gudang')

@section('actions')
    <a href="{{ route('stocks.movements') }}" class="btn"><i class="ti ti-list me-1"></i> Pergerakan Stok</a>
    <a href="{{ route('stocks.valuation') }}" class="btn"><i class="ti ti-report-money me-1"></i> Valuasi</a>
    @can('stock-adjustment.create')
        <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary">
            <i class="ti ti-adjustments me-1"></i> Penyesuaian
        </a>
    @endcan
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-md-6">
            <x-stat label="Total kuantitas tersimpan" :value="fnum($summary->total_qty ?? 0)" icon="ti ti-package" color="blue" />
        </div>
        <div class="col-md-6">
            <x-stat label="Nilai persediaan" :value="rupiah($summary->total_value ?? 0)" icon="ti ti-report-money" color="green" />
        </div>
    </div>

    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari produk</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="SKU / nama…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Gudang</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">Semua gudang</option>
                        @foreach($warehouses as $id => $name)
                            <option value="{{ $id }}" @selected(request('warehouse_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Kategori</label>
                    <select name="category_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" @selected(request('category_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-check mt-4">
                        <input type="checkbox" name="low" value="1" class="form-check-input" @checked(request('low') === '1')>
                        <span class="form-check-label">Di bawah minimum</span>
                    </label>
                </div>
                <div class="col-auto">
                    <label class="form-check mt-4">
                        <input type="checkbox" name="nonzero" value="1" class="form-check-input" @checked(request('nonzero') === '1')>
                        <span class="form-check-label">Hanya yang ada stok</span>
                    </label>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    @if(collect(request()->query())->filter()->isNotEmpty())
                        <a href="{{ route('stocks.index') }}" class="btn btn-link">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($stocks->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-package-off" title="Belum ada stok"
                         message="Stok akan muncul setelah penerimaan barang atau penyesuaian diposting." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>SKU</th><th>Produk</th><th>Gudang</th>
                        <th class="text-num">Kuantitas</th><th class="text-num">Min</th>
                        <th class="text-num">HPP Rata-rata</th><th class="text-num">Nilai</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($stocks as $stock)
                        @php $below = $stock->product?->min_stock > 0 && (float) $stock->quantity < (float) $stock->product->min_stock; @endphp
                        <tr>
                            <td class="fw-bold">{{ $stock->product?->sku }}</td>
                            <td><a href="{{ route('products.show', $stock->product_id) }}">{{ $stock->product?->name }}</a></td>
                            <td class="text-secondary">{{ $stock->warehouse?->name }}</td>
                            <td class="text-num {{ $below ? 'text-danger fw-bold' : 'fw-bold' }}">
                                {{ fnum($stock->quantity) }} {{ $stock->product?->uom?->code }}
                            </td>
                            <td class="text-num text-secondary">{{ fnum($stock->product?->min_stock) }}</td>
                            <td class="text-num">{{ rupiah($stock->avg_cost) }}</td>
                            <td class="text-num">{{ rupiah($stock->value()) }}</td>
                            <td class="text-end">
                                <a href="{{ route('stocks.card', ['product_id' => $stock->product_id, 'warehouse_id' => $stock->warehouse_id]) }}"
                                   class="btn btn-sm btn-ghost-secondary" title="Kartu stok">
                                    <i class="ti ti-history"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $stocks->links() }}</div>
        @endif
    </x-card>
@endsection
