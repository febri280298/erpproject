@extends('layouts.app')

@section('title', 'Kartu Stok')
@section('pretitle', 'Stok & Gudang')

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label required" for="product_id">Produk</label>
                <select name="product_id" id="product_id" class="form-select" required>
                    <option value="">— Pilih produk —</option>
                    @foreach($products as $id => $label)
                        <option value="{{ $id }}" @selected($filters['productId'] == $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="warehouse_id">Gudang</label>
                <select name="warehouse_id" id="warehouse_id" class="form-select">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $id => $name)
                        <option value="{{ $id }}" @selected($filters['warehouseId'] == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="from">Dari</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">Sampai</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Lihat</button>
            </div>
        </form>
    </x-card>

    @if(! $product)
        <x-card>
            <x-empty icon="ti ti-history" title="Pilih produk"
                     message="Pilih produk di atas untuk menampilkan kartu stoknya." />
        </x-card>
    @else
        <x-card :title="'Kartu Stok — '.$product->label()" flush>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Tanggal</th><th>Referensi</th><th>Keterangan</th><th>Gudang</th>
                        <th class="text-num">Masuk</th><th class="text-num">Keluar</th>
                        <th class="text-num">Saldo</th><th class="text-num">HPP</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="table-light fw-bold">
                        <td colspan="6">Saldo awal per {{ fdate($filters['from']) }}</td>
                        <td class="text-num">{{ fnum($opening['quantity']) }}</td>
                        <td class="text-num">{{ rupiah($opening['value']) }}</td>
                    </tr>

                    @forelse($movements as $movement)
                        <tr>
                            <td>{{ fdate($movement->date) }}</td>
                            <td>{{ $movement->ref_no ?? '—' }}</td>
                            <td class="text-secondary">{{ $movement->refLabel() }}{{ $movement->notes ? ' · '.$movement->notes : '' }}</td>
                            <td class="text-secondary">{{ $movement->warehouse?->name }}</td>
                            <td class="text-num text-success">{{ $movement->isIn() ? fnum($movement->quantity) : '' }}</td>
                            <td class="text-num text-danger">{{ $movement->isIn() ? '' : fnum($movement->quantity) }}</td>
                            <td class="text-num fw-bold">{{ fnum($movement->balance_qty) }}</td>
                            <td class="text-num">{{ rupiah($movement->unit_cost) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">
                                Tidak ada pergerakan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="4">Total periode</td>
                        <td class="text-num text-success">{{ fnum($movements->where('direction', 'in')->sum('quantity')) }}</td>
                        <td class="text-num text-danger">{{ fnum($movements->where('direction', 'out')->sum('quantity')) }}</td>
                        <td class="text-num">{{ fnum($movements->last()->balance_qty ?? $opening['quantity']) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
    @endif
@endsection
