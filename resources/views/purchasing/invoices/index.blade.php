@extends('layouts.app')

@section('title', 'Faktur Pembelian')
@section('pretitle', 'Pembelian')

@section('actions')
    @can('purchase-invoice.create')
        <a href="{{ route('purchase-invoices.select-orders') }}" class="btn btn-primary">
            <i class="ti ti-checklist me-1"></i> Dari Pesanan Pembelian
        </a>
        <a href="{{ route('purchase-invoices.create') }}" class="btn">
            <i class="ti ti-plus me-1"></i> Buat Faktur
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'No. faktur…',
            'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'partial' => 'Sebagian Dibayar', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'],
            'selects' => [['name' => 'partner_id', 'label' => 'Pemasok', 'options' => $suppliers]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-file-off" title="Belum ada faktur pembelian"
                         message="Buat faktur dari pesanan pembelian atau secara langsung." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. Faktur</th><th>Faktur Pemasok</th><th>Tanggal</th><th>Jatuh Tempo</th>
                        <th>Pemasok</th><th class="text-num">Total</th><th class="text-num">Sisa</th>
                        <th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('purchase-invoices.show', $document) }}" class="fw-bold">{{ $document->invoice_no }}</a></td>
                            <td class="text-secondary">{{ $document->supplier_invoice_no ?? '—' }}</td>
                            <td>{{ fdate($document->date) }}</td>
                            <td class="{{ $document->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                {{ fdate($document->due_date) }}
                                @if($document->isOverdue())
                                    <div class="small">{{ $document->daysOverdue() }} hari lewat</div>
                                @endif
                            </td>
                            <td>{{ $document->supplier?->name }}</td>
                            <td class="text-num">{{ rupiah($document->total) }}</td>
                            <td class="text-num {{ $document->outstandingAmount() > 0 ? 'text-danger' : 'text-success' }}">
                                {{ rupiah($document->outstandingAmount()) }}
                            </td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('purchase-invoices.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('purchase-invoices.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isEditable())
                                            @can('purchase-invoice.edit')
                                                <a class="dropdown-item" href="{{ route('purchase-invoices.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('purchase-invoice.delete')
                                                <x-delete-form :action="route('purchase-invoices.destroy', $document)" label="Hapus" />
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
