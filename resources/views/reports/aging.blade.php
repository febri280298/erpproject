@extends('layouts.app')

@section('title', $title)
@section('pretitle', 'Laporan · per ' . fdate(now()))

@section('actions')
    <button type="button" class="btn d-print-none" onclick="window.print()">
        <i class="ti ti-printer me-1"></i> Cetak
    </button>
@endsection

@section('content')
    @php
        $labels = [
            'current' => 'Belum Jatuh Tempo',
            '1_30' => '1 – 30 hari',
            '31_60' => '31 – 60 hari',
            '61_90' => '61 – 90 hari',
            'over_90' => '> 90 hari',
        ];
        $colors = ['current' => 'green', '1_30' => 'yellow', '31_60' => 'orange', '61_90' => 'red', 'over_90' => 'dark'];
    @endphp

    <div class="row row-cards mb-3">
        @foreach($labels as $key => $label)
            <div class="col-6 col-md">
                <x-stat :label="$label" :value="rupiah($buckets[$key])" icon="ti ti-clock" :color="$colors[$key]" />
            </div>
        @endforeach
    </div>

    <x-card :title="$title" :subtitle="'Total outstanding: '.rupiah($total)" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>No. Faktur</th><th>{{ $partnerLabel }}</th><th>Tanggal</th><th>Jatuh Tempo</th>
                    <th class="text-num">Terlambat</th><th class="text-num">Total</th>
                    <th class="text-num">Dibayar</th><th class="text-num">Sisa</th><th>Umur</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['invoice_no'] }}</td>
                        <td>{{ $row['partner'] }}</td>
                        <td>{{ fdate($row['date']) }}</td>
                        <td class="{{ $row['days'] > 0 ? 'text-danger' : '' }}">{{ fdate($row['due_date']) }}</td>
                        <td class="text-num">{{ $row['days'] > 0 ? $row['days'].' hari' : '—' }}</td>
                        <td class="text-num">{{ rupiah($row['total']) }}</td>
                        <td class="text-num text-success">{{ rupiah($row['paid']) }}</td>
                        <td class="text-num fw-bold">{{ rupiah($row['outstanding']) }}</td>
                        <td><span class="badge bg-{{ $colors[$row['bucket']] }}-lt">{{ $labels[$row['bucket']] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-4">Tidak ada tagihan terbuka. 🎉</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td colspan="7" class="text-end">Total Outstanding</td>
                    <td class="text-num fs-4">{{ rupiah($total) }}</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
