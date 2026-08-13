@extends('layouts.app')

@section('title', 'Laporan Laba Rugi')
@section('pretitle', 'Akuntansi · ' . fdate($filters['from']) . ' – ' . fdate($filters['to']))

@section('content')
    @include('partials.period-filter')

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <x-stat label="Total Pendapatan" :value="rupiah($totalRevenue)" icon="ti ti-trending-up" color="green" />
        </div>
        <div class="col-md-4">
            <x-stat label="Total Beban" :value="rupiah($totalExpense)" icon="ti ti-trending-down" color="red" />
        </div>
        <div class="col-md-4">
            <x-stat :label="$netIncome >= 0 ? 'Laba Bersih' : 'Rugi Bersih'" :value="rupiah(abs($netIncome))"
                    icon="ti ti-report-money" :color="$netIncome >= 0 ? 'blue' : 'orange'" />
        </div>
    </div>

    <x-card title="Laporan Laba Rugi" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <tbody>
                <tr class="table-light fw-bold"><td colspan="2">PENDAPATAN</td></tr>
                @forelse($revenues as $row)
                    <tr>
                        <td class="ps-4">{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                        <td class="text-num" style="width:16rem">{{ rupiah($row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="ps-4 text-secondary" colspan="2">Belum ada pendapatan pada periode ini.</td></tr>
                @endforelse
                <tr class="fw-bold border-top">
                    <td>Total Pendapatan</td>
                    <td class="text-num">{{ rupiah($totalRevenue, 2) }}</td>
                </tr>

                <tr class="table-light fw-bold"><td colspan="2" class="pt-4">BEBAN</td></tr>
                @forelse($expenses as $row)
                    <tr>
                        <td class="ps-4">{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                        <td class="text-num">{{ rupiah($row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="ps-4 text-secondary" colspan="2">Belum ada beban pada periode ini.</td></tr>
                @endforelse
                <tr class="fw-bold border-top">
                    <td>Total Beban</td>
                    <td class="text-num">{{ rupiah($totalExpense, 2) }}</td>
                </tr>

                <tr class="fw-bold border-top {{ $netIncome >= 0 ? 'table-success' : 'table-danger' }}">
                    <td class="fs-3">{{ $netIncome >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</td>
                    <td class="text-num fs-3">{{ rupiah(abs($netIncome), 2) }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
