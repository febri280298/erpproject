@extends('layouts.app')

@section('title', 'Produk Terlaris')
@section('pretitle', 'Laporan · ' . fdate($filters['from']) . ' – ' . fdate($filters['to']))

@section('content')
    @include('partials.period-filter')

    <x-card title="Peringkat Produk Berdasarkan Pendapatan" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th style="width:3rem">#</th><th>SKU</th><th>Produk</th>
                    <th class="text-num">Qty Terjual</th><th class="text-num">Jumlah Faktur</th>
                    <th class="text-num">Pendapatan</th><th style="width:10rem">Kontribusi</th>
                </tr>
                </thead>
                <tbody>
                @php $maxRevenue = (float) ($rows->max('revenue') ?: 1); @endphp
                @forelse($rows as $index => $row)
                    <tr>
                        <td class="text-secondary">{{ $index + 1 }}</td>
                        <td class="fw-bold">{{ $row->sku }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="text-num">{{ fnum($row->qty) }}</td>
                        <td class="text-num">{{ $row->invoices }}</td>
                        <td class="text-num fw-bold">{{ rupiah($row->revenue) }}</td>
                        <td>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: {{ round((float) $row->revenue / $maxRevenue * 100) }}%"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada penjualan pada periode ini.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td colspan="5" class="text-end">Total Pendapatan</td>
                    <td class="text-num fs-4">{{ rupiah($rows->sum('revenue')) }}</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
