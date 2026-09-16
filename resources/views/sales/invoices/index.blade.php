@extends('layouts.app')

@section('title', 'Faktur Penjualan')
@section('pretitle', 'Penjualan')

@section('actions')
    @can('sales-invoice.create')
        <a href="{{ route('sales-invoices.select-deliveries') }}" class="btn">
            <i class="ti ti-truck-delivery me-1"></i> Dari Surat Jalan
        </a>
    @endcan
    @can('sales-invoice.create')
        <a href="{{ route('sales-invoices.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Faktur
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'No. faktur / PO pelanggan…',
            'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'partial' => 'Sebagian Dibayar', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'],
            'selects' => [['name' => 'partner_id', 'label' => 'Pelanggan', 'options' => $customers]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-file-off" title="Belum ada faktur penjualan"
                         message="Buat faktur dari pesanan penjualan atau secara langsung." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. Faktur</th><th>Tanggal</th><th>Jatuh Tempo</th><th>No. PO Pelanggan</th><th>Pelanggan</th>
                        <th class="text-num">Total</th><th class="text-num">Sisa</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('sales-invoices.show', $document) }}" class="fw-bold">{{ $document->invoice_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td class="{{ $document->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                {{ fdate($document->due_date) }}
                                @if($document->isOverdue())<div class="small">{{ $document->daysOverdue() }} hari lewat</div>@endif
                            </td>
                            <td class="text-secondary">{{ $document->customer_po_no ?? '—' }}</td>
                            <td>{{ $document->customer?->name }}</td>
                            <td class="text-num">{{ rupiah($document->total) }}</td>
                            <td class="text-num {{ $document->outstandingAmount() > 0 ? 'text-danger' : 'text-success' }}">
                                {{ rupiah($document->outstandingAmount()) }}
                            </td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('sales-invoices.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('sales-invoices.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isEditable())
                                            @can('sales-invoice.edit')
                                                <a class="dropdown-item" href="{{ route('sales-invoices.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('sales-invoice.delete')
                                                <x-delete-form :action="route('sales-invoices.destroy', $document)" label="Hapus" />
                                            @endcan
                                        @endif
                                    </div>
                                </div>
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
