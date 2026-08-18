@extends('layouts.print')

@section('title', 'Slip Gaji ' . $item->employee?->name)
@section('doc-title', 'Slip Gaji')
@section('doc-subtitle', $payroll->periodLabel())

@section('content')
    <div class="row mb-4">
        <div class="col-6">
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-secondary p-1" style="width:40%">Nama</td><td class="p-1">: {{ $item->employee?->name }}</td></tr>
                <tr><td class="text-secondary p-1">NIK</td><td class="p-1">: {{ $item->employee?->nik }}</td></tr>
                <tr><td class="text-secondary p-1">Departemen</td><td class="p-1">: {{ $item->employee?->department?->name ?? '—' }}</td></tr>
                <tr><td class="text-secondary p-1">Jabatan</td><td class="p-1">: {{ $item->employee?->position?->name ?? '—' }}</td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-secondary p-1" style="width:40%">Periode</td><td class="p-1">: {{ $payroll->periodLabel() }}</td></tr>
                <tr><td class="text-secondary p-1">No. Payroll</td><td class="p-1">: {{ $payroll->payroll_no }}</td></tr>
                <tr><td class="text-secondary p-1">Tanggal Bayar</td><td class="p-1">: {{ fdate($payroll->payment_date) }}</td></tr>
                <tr><td class="text-secondary p-1">Hari Hadir</td><td class="p-1">: {{ $item->present_days }} hari</td></tr>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <table class="table table-bordered table-sm">
                <thead class="table-light"><tr><th colspan="2">PENERIMAAN</th></tr></thead>
                <tbody>
                <tr><td>Gaji Pokok</td><td class="text-num">{{ rupiah($item->basic_salary, null, false) }}</td></tr>
                <tr><td>Tunjangan</td><td class="text-num">{{ rupiah($item->allowance, null, false) }}</td></tr>
                <tr><td>Lembur</td><td class="text-num">{{ rupiah($item->overtime, null, false) }}</td></tr>
                <tr><td>Bonus</td><td class="text-num">{{ rupiah($item->bonus, null, false) }}</td></tr>
                </tbody>
                <tfoot>
                <tr class="fw-bold"><td>Total Penerimaan</td><td class="text-num">{{ rupiah($item->gross_salary, null, false) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-bordered table-sm">
                <thead class="table-light"><tr><th colspan="2">POTONGAN</th></tr></thead>
                <tbody>
                <tr><td>BPJS</td><td class="text-num">{{ rupiah($item->bpjs, null, false) }}</td></tr>
                <tr><td>PPh 21</td><td class="text-num">{{ rupiah($item->tax_pph21, null, false) }}</td></tr>
                <tr><td>Potongan Lain</td><td class="text-num">{{ rupiah($item->other_deduction, null, false) }}</td></tr>
                <tr><td>&nbsp;</td><td></td></tr>
                </tbody>
                <tfoot>
                <tr class="fw-bold"><td>Total Potongan</td><td class="text-num">{{ rupiah($item->total_deduction, null, false) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <table class="table table-bordered">
        <tr class="fw-bold">
            <td class="fs-3">GAJI BERSIH DITERIMA</td>
            <td class="text-num fs-3" style="width:16rem">{{ rupiah($item->net_salary) }}</td>
        </tr>
    </table>

    <p class="small"><strong>Terbilang:</strong> <em>{{ terbilang((float) $item->net_salary) }}</em></p>

    @if($item->employee?->bank_name)
        <p class="small text-secondary">
            Ditransfer ke {{ $item->employee->bank_name }} — {{ $item->employee->bank_account }}
        </p>
    @endif

    <div class="row signatures text-center">
        <div class="col">
            <div class="text-secondary" style="font-size:8.5pt">Diterima oleh</div>
            <div style="height:18mm"></div>
            <div style="border-top:.5pt solid #666; width:75%; margin:0 auto"></div>
            <div class="mt-1" style="font-size:8.5pt">{{ $item->employee?->name }}</div>
        </div>
        <div class="col">
            <div class="text-secondary" style="font-size:8.5pt">Disetujui oleh</div>
            <div style="height:18mm"></div>
            <div style="border-top:.5pt solid #666; width:75%; margin:0 auto"></div>
            <div class="mt-1" style="font-size:8.5pt">HRD</div>
        </div>
    </div>
@endsection
