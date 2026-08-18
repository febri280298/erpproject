@extends('layouts.app')

@section('title', 'Laporan Persediaan')
@section('pretitle', 'Laporan')

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="warehouse_id">Gudang</label>
                <select name="warehouse_id" id="warehouse_id" class="form-select">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $id => $name)
                        <option value="{{ $id }}" @selected($warehouseId == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-md-2">
                <button type="button" class="btn w-100 d-print-none" onclick="window.print()">
                    <i class="ti ti-printer me-1"></i> Cetak
                </button>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-secondary small">Total Nilai Persediaan</div>
                <div class="fs-2 fw-bold">{{ rupiah($totalValue) }}</div>
            </div>
        </form>
    </x-card>

    <x-card title="Daftar Persediaan" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>SKU</th><th>Produk</th><th>Gudang</th>
                    <th class="text-num">Qty</th><th>Satuan</th>
                    <th class="text-num">Min</th><th class="text-num">HPP</th><th class="text-num">Nilai</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="fw-bold">{{ $row->sku }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="text-secondary">{{ $row->warehouse }}</td>
                        <td class="text-num {{ $row->min_stock > 0 && $row->quantity < $row->min_stock ? 'text-danger fw-bold' : '' }}">
                            {{ fnum($row->quantity) }}
                        </td>
                        <td class="text-secondary">{{ $row->uom ?? '—' }}</td>
                        <td class="text-num text-secondary">{{ fnum($row->min_stock) }}</td>
                        <td class="text-num">{{ rupiah($row->avg_cost) }}</td>
                        <td class="text-num fw-bold">{{ rupiah($row->value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Belum ada persediaan.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td colspan="7" class="text-end">Total Nilai Persediaan</td>
                    <td class="text-num fs-4">{{ rupiah($totalValue) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
