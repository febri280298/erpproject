@extends('layouts.app')

@section('title', $partner->name)
@section('pretitle', $partner->typeLabel() . ' · ' . $partner->code)

@section('actions')
    @can('partner.edit')
        <a href="{{ route('partners.edit', $partner) }}" class="btn btn-primary">
            <i class="ti ti-edit me-1"></i> Ubah
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <x-card title="Informasi Mitra">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Kontak</dt>
                    <dd class="col-7">{{ $partner->contact_person ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Telepon</dt>
                    <dd class="col-7">{{ $partner->phone ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Email</dt>
                    <dd class="col-7">{{ $partner->email ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Kota</dt>
                    <dd class="col-7">{{ $partner->city ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">NPWP</dt>
                    <dd class="col-7">{{ $partner->npwp ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Termin</dt>
                    <dd class="col-7">{{ $partner->paymentTerm?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Batas Kredit</dt>
                    <dd class="col-7">{{ rupiah($partner->credit_limit) }}</dd>
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$partner->is_active ? 'active' : 'cancelled'" :label="$partner->is_active ? 'Aktif' : 'Nonaktif'" /></dd>
                </dl>

                @if($partner->address)
                    <hr>
                    <div class="text-secondary">{!! nl2br(e($partner->address)) !!}</div>
                @endif
            </x-card>

            <div class="row g-3 mt-0">
                @if($partner->isCustomer())
                    <div class="col-12">
                        <x-stat label="Piutang belum tertagih" :value="rupiah($openReceivables->sum(fn($i) => $i->outstandingAmount()))"
                                icon="ti ti-cash" color="orange" />
                    </div>
                @endif
                @if($partner->isSupplier())
                    <div class="col-12">
                        <x-stat label="Utang belum dibayar" :value="rupiah($openPayables->sum(fn($i) => $i->outstandingAmount()))"
                                icon="ti ti-receipt-2" color="red" />
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            @if($partner->isCustomer())
                <x-card title="Pesanan Penjualan Terakhir" flush>
                    @include('partials.doc-mini-table', [
                        'rows' => $salesOrders,
                        'numberField' => 'so_no',
                        'partnerField' => 'customer',
                        'routeName' => 'sales-orders',
                        'emptyMessage' => 'Belum ada pesanan penjualan untuk mitra ini.',
                    ])
                </x-card>

                <x-card title="Faktur Penjualan Belum Lunas" flush class="mt-3">
                    @if($openReceivables->isEmpty())
                        <div class="card-body"><x-empty icon="ti ti-check" title="Tidak ada piutang" message="Semua faktur pelanggan ini sudah lunas." /></div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                <tr>
                                    <th>Faktur</th><th>Tanggal</th><th>Jatuh Tempo</th>
                                    <th class="text-num">Total</th><th class="text-num">Sisa</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($openReceivables as $invoice)
                                    <tr>
                                        <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
                                        <td>{{ fdate($invoice->date) }}</td>
                                        <td class="{{ $invoice->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ fdate($invoice->due_date) }}</td>
                                        <td class="text-num">{{ rupiah($invoice->total) }}</td>
                                        <td class="text-num fw-bold">{{ rupiah($invoice->outstandingAmount()) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endif

            @if($partner->isSupplier())
                <x-card title="Pesanan Pembelian Terakhir" flush class="{{ $partner->isCustomer() ? 'mt-3' : '' }}">
                    @include('partials.doc-mini-table', [
                        'rows' => $purchaseOrders,
                        'numberField' => 'po_no',
                        'partnerField' => 'supplier',
                        'routeName' => 'purchase-orders',
                        'emptyMessage' => 'Belum ada pesanan pembelian untuk mitra ini.',
                    ])
                </x-card>

                <x-card title="Faktur Pembelian Belum Lunas" flush class="mt-3">
                    @if($openPayables->isEmpty())
                        <div class="card-body"><x-empty icon="ti ti-check" title="Tidak ada utang" message="Semua faktur pemasok ini sudah lunas." /></div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                <tr>
                                    <th>Faktur</th><th>Tanggal</th><th>Jatuh Tempo</th>
                                    <th class="text-num">Total</th><th class="text-num">Sisa</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($openPayables as $invoice)
                                    <tr>
                                        <td><a href="{{ route('purchase-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
                                        <td>{{ fdate($invoice->date) }}</td>
                                        <td class="{{ $invoice->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ fdate($invoice->due_date) }}</td>
                                        <td class="text-num">{{ rupiah($invoice->total) }}</td>
                                        <td class="text-num fw-bold">{{ rupiah($invoice->outstandingAmount()) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
@endsection
