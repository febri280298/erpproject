@extends('layouts.app')

@section('title', 'Retur Penjualan')
@section('pretitle', 'Penjualan')

@section('actions')
    @can('sales-return.create')
        <a href="{{ route('sales-returns.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Retur
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="No. retur…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Customer</label>
                    <select name="partner_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($customers as $id => $name)
                            <option value="{{ $id }}" @selected(request('partner_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach(['draft' => 'Draft', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'] as $k => $v)
                            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Dari</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Sampai</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    @if(collect(request()->query())->filter()->isNotEmpty())
                        <a href="{{ route('sales-returns.index') }}" class="btn btn-link">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-arrow-back-up" title="Belum ada retur penjualan"
                         message="Retur dibuat dari surat jalan yang sudah diposting." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. Retur</th><th>Tanggal</th><th>Customer</th>
                        <th>Surat Jalan</th><th>Nota Kredit</th>
                        <th class="text-num">Item</th><th class="text-num">Nilai</th>
                        <th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('sales-returns.show', $document) }}" class="fw-bold">{{ $document->return_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ $document->customer?->name }}</td>
                            <td class="text-secondary">
                                @if($document->deliveryOrder)
                                    <a href="{{ route('delivery-orders.show', $document->delivery_order_id) }}">{{ $document->deliveryOrder->do_no }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-secondary">
                                @if($document->hasCreditNote() && $document->salesInvoice)
                                    <a href="{{ route('sales-invoices.show', $document->sales_invoice_id) }}">{{ $document->salesInvoice->invoice_no }}</a>
                                @else
                                    <span class="text-secondary">Tanpa nota kredit</span>
                                @endif
                            </td>
                            <td class="text-num">{{ $document->items_count }}</td>
                            <td class="text-num">{{ rupiah($document->total) }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <a href="{{ route('sales-returns.show', $document) }}" class="btn btn-sm btn-ghost-secondary">
                                    <i class="ti ti-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $documents->links() }}</div>
        @endif
    </x-card>
@endsection
