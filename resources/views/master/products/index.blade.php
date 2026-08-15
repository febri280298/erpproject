@extends('layouts.app')

@section('title', 'Produk')
@section('pretitle', 'Data Master')

@section('actions')
    <a href="{{ route('products.export') }}" class="btn">
        <i class="ti ti-file-spreadsheet me-1"></i> Ekspor
    </a>
    @can('product.create')
        <a href="{{ route('products.import') }}" class="btn">
            <i class="ti ti-upload me-1"></i> Upload Produk
        </a>
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Tambah Produk
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="SKU, nama, barcode…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Kategori</label>
                    <select name="category" class="form-select">
                        <option value="">Semua</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" @selected(request('category') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Tipe</label>
                    <select name="type" class="form-select">
                        <option value="">Semua</option>
                        <option value="stock" @selected(request('type') === 'stock')>Barang</option>
                        <option value="service" @selected(request('type') === 'service')>Jasa</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-check mt-4">
                        <input type="checkbox" name="low" value="1" class="form-check-input" @checked(request('low') === '1')>
                        <span class="form-check-label">Stok menipis</span>
                    </label>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    @if(collect(request()->query())->filter()->isNotEmpty())
                        <a href="{{ route('products.index') }}" class="btn btn-link">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($products->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-box-off" title="Belum ada produk"
                         message="Tambahkan produk untuk mulai mencatat pembelian dan penjualan." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Tipe</th>
                        <th>PPN</th>
                        <th class="text-num">Harga Beli</th>
                        <th class="text-num">Harga Jual</th>
                        <th class="text-num">Stok</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td class="fw-bold">{{ $product->sku }}</td>
                            <td>
                                <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
                            </td>
                            <td class="text-secondary">{{ $product->category?->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $product->isStockable() ? 'blue' : 'purple' }}-lt">
                                    {{ $product->isStockable() ? 'Barang' : 'Jasa' }}
                                </span>
                            </td>
                            <td>
                                @php $tarif = (float) ($product->tax?->rate ?? 0); @endphp
                                <span class="badge bg-{{ $tarif > 0 ? 'green' : 'secondary' }}-lt">
                                    {{ $tarif > 0 ? 'PPN '.fnum($tarif).'%' : 'Bebas PPN' }}
                                </span>
                            </td>
                            <td class="text-num">{{ rupiah($product->purchase_price) }}</td>
                            <td class="text-num">{{ rupiah($product->sale_price) }}</td>
                            <td class="text-num">
                                @if($product->isStockable())
                                    @php $onHand = (float) ($product->on_hand ?? 0); @endphp
                                    <span class="{{ $product->min_stock > 0 && $onHand < (float) $product->min_stock ? 'text-danger fw-bold' : '' }}">
                                        {{ fnum($onHand) }} {{ $product->uom?->code }}
                                    </span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td><x-status :value="$product->is_active ? 'active' : 'cancelled'" :label="$product->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('products.show', $product) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('stocks.card', ['product_id' => $product->id]) }}">
                                            <i class="ti ti-history me-2"></i> Kartu Stok
                                        </a>
                                        @can('product.edit')
                                            <a class="dropdown-item" href="{{ route('products.edit', $product) }}">
                                                <i class="ti ti-edit me-2"></i> Ubah
                                            </a>
                                        @endcan
                                        @can('product.delete')
                                            <x-delete-form :action="route('products.destroy', $product)" label="Hapus" />
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $products->links() }}</div>
        @endif
    </x-card>
@endsection
