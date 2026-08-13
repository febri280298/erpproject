@extends('layouts.app')

@section('title', 'Valuasi Persediaan')
@section('pretitle', 'Stok & Gudang')

@section('actions')
    <button type="button" class="btn" onclick="window.print()"><i class="ti ti-printer me-1"></i> Cetak</button>
@endsection

@section('content')
    <x-card title="Nilai Persediaan per Gudang" subtitle="Metode: rata-rata bergerak (moving average)" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr><th>Gudang</th><th class="text-num">Total Kuantitas</th><th class="text-num">Nilai Persediaan</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->warehouse }}</td>
                        <td class="text-num">{{ fnum($row->qty) }}</td>
                        <td class="text-num fw-bold">{{ rupiah($row->value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada data persediaan.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td>Total</td>
                    <td class="text-num">{{ fnum($rows->sum('qty')) }}</td>
                    <td class="text-num fs-3">{{ rupiah($rows->sum('value')) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
