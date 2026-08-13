@extends('layouts.app')

@section('title', 'Pergerakan Stok')
@section('pretitle', 'Stok & Gudang')

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Produk</label>
                    <select name="product_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($products as $id => $label)
                            <option value="{{ $id }}" @selected(request('product_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Gudang</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($warehouses as $id => $name)
                            <option value="{{ $id }}" @selected(request('warehouse_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Arah</label>
                    <select name="direction" class="form-select">
                        <option value="">Semua</option>
                        <option value="in" @selected(request('direction') === 'in')>Masuk</option>
                        <option value="out" @selected(request('direction') === 'out')>Keluar</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Jenis</label>
                    <select name="ref_type" class="form-select">
                        <option value="">Semua</option>
                        @foreach($refTypes as $type)
                            <option value="{{ $type }}" @selected(request('ref_type') === $type)>{{ str_replace('_', ' ', $type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Dari</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Sampai</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    @if(collect(request()->query())->filter()->isNotEmpty())
                        <a href="{{ route('stocks.movements') }}" class="btn btn-link">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($movements->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-history-off" title="Belum ada pergerakan stok"
                         message="Setiap posting penerimaan, pengiriman, transfer atau penyesuaian akan tercatat di sini." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Tanggal</th><th>Referensi</th><th>Produk</th><th>Gudang</th>
                        <th class="text-num">Masuk</th><th class="text-num">Keluar</th>
                        <th class="text-num">HPP</th><th class="text-num">Saldo</th>
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
                            <td>
                                <a href="{{ route('products.show', $movement->product_id) }}">{{ $movement->product?->name }}</a>
                                <div class="text-secondary small">{{ $movement->product?->sku }}</div>
                            </td>
                            <td class="text-secondary">{{ $movement->warehouse?->name }}</td>
                            <td class="text-num text-success">{{ $movement->isIn() ? fnum($movement->quantity) : '' }}</td>
                            <td class="text-num text-danger">{{ $movement->isIn() ? '' : fnum($movement->quantity) }}</td>
                            <td class="text-num">{{ rupiah($movement->unit_cost, 2) }}</td>
                            <td class="text-num fw-bold">{{ fnum($movement->balance_qty) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $movements->links() }}</div>
        @endif
    </x-card>
@endsection
