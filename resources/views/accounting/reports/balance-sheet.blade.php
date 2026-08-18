@extends('layouts.app')

@section('title', 'Neraca')
@section('pretitle', 'Akuntansi · per ' . fdate($asOf))

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="as_of">Per Tanggal</label>
                <input type="date" name="as_of" id="as_of" value="{{ $asOf }}" class="form-control">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-md-2">
                <button type="button" class="btn w-100 d-print-none" onclick="window.print()">
                    <i class="ti ti-printer me-1"></i> Cetak
                </button>
            </div>
            <div class="col-md-5 text-end">
                @php $difference = $totalAssets - ($totalLiabilities + $totalEquity); @endphp
                @if(abs($difference) < 0.01)
                    <span class="badge bg-green-lt">Neraca seimbang</span>
                @else
                    <span class="badge bg-red-lt">Selisih {{ rupiah(abs($difference)) }}</span>
                @endif
            </div>
        </form>
    </x-card>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-card title="AKTIVA" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <tbody>
                        @forelse($assets as $row)
                            <tr>
                                <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                                <td class="text-num" style="width:14rem">{{ rupiah($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-secondary text-center py-3">Belum ada data.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold table-light">
                            <td>TOTAL AKTIVA</td>
                            <td class="text-num fs-3">{{ rupiah($totalAssets) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="col-lg-6">
            <x-card title="PASIVA" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <tbody>
                        <tr class="table-light fw-bold"><td colspan="2">Kewajiban</td></tr>
                        @forelse($liabilities as $row)
                            <tr>
                                <td class="ps-4">{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                                <td class="text-num" style="width:14rem">{{ rupiah($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="ps-4 text-secondary">Belum ada kewajiban.</td></tr>
                        @endforelse
                        <tr class="fw-bold">
                            <td>Total Kewajiban</td>
                            <td class="text-num">{{ rupiah($totalLiabilities) }}</td>
                        </tr>

                        <tr class="table-light fw-bold"><td colspan="2">Ekuitas</td></tr>
                        @foreach($equity as $row)
                            <tr>
                                <td class="ps-4">{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                                <td class="text-num">{{ rupiah($row['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="ps-4">{{ $netIncome >= 0 ? 'Laba' : 'Rugi' }} berjalan</td>
                            <td class="text-num">{{ rupiah($netIncome) }}</td>
                        </tr>
                        <tr class="fw-bold">
                            <td>Total Ekuitas</td>
                            <td class="text-num">{{ rupiah($totalEquity) }}</td>
                        </tr>
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold table-light">
                            <td>TOTAL PASIVA</td>
                            <td class="text-num fs-3">{{ rupiah($totalLiabilities + $totalEquity) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
@endsection
