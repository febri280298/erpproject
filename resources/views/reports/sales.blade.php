@extends('layouts.app')

@section('title', 'Laporan Penjualan')
@section('pretitle', 'Laporan · ' . fdate($filters['from']) . ' – ' . fdate($filters['to']))

@section('content')
    @include('partials.period-filter', ['extra' => new \Illuminate\Support\HtmlString('
        <div class="col-md-4">
            <label class="form-label" for="partner_id">Pelanggan</label>
            <select name="partner_id" id="partner_id" class="form-select">
                <option value="">Semua pelanggan</option>
                '.collect($customers)->map(fn($name, $id) => '<option value="'.$id.'"'.(request('partner_id') == $id ? ' selected' : '').'>'.e($name).'</option>')->implode('').'
            </select>
        </div>
    ')])

    <div class="row row-cards mb-3">
        <div class="col-6 col-md-3">
            <x-stat label="Jumlah Faktur" :value="$summary['count']" icon="ti ti-file-invoice" color="blue" />
        </div>
        <div class="col-6 col-md-3">
            <x-stat label="Subtotal" :value="rupiah($summary['subtotal'])" icon="ti ti-receipt" color="azure" />
        </div>
        <div class="col-6 col-md-3">
            <x-stat label="Total Penjualan" :value="rupiah($summary['total'])" icon="ti ti-trending-up" color="green" />
        </div>
        <div class="col-6 col-md-3">
            <x-stat label="Sudah Dibayar" :value="rupiah($summary['paid'])" icon="ti ti-cash" color="teal" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <x-card title="Penjualan per Pelanggan" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Pelanggan</th><th class="text-num">Faktur</th><th class="text-num">Total</th></tr></thead>
                        <tbody>
                        @forelse($byCustomer as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-num">{{ $row['count'] }}</td>
                                <td class="text-num fw-bold">{{ rupiah($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada penjualan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="col-lg-7">
            <x-card title="Daftar Faktur" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr><th>No. Faktur</th><th>Tanggal</th><th>Pelanggan</th><th class="text-num">Total</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
                                <td>{{ fdate($invoice->date) }}</td>
                                <td class="text-truncate" style="max-width:12rem">{{ $invoice->customer?->name }}</td>
                                <td class="text-num">{{ rupiah($invoice->total) }}</td>
                                <td><x-status :value="$invoice->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Tidak ada faktur pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="3">Total</td>
                            <td class="text-num fs-4">{{ rupiah($summary['total']) }}</td>
                            <td></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
@endsection
