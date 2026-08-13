@extends('layouts.app')

@section('title', 'Pesanan Penjualan')
@section('pretitle', 'Penjualan')

@section('actions')
    @can('sales-order.create')
        <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Pesanan
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'No. SO / PO pelanggan…',
            'statuses' => [
                'draft' => 'Draft', 'confirmed' => 'Dikonfirmasi', 'partial' => 'Sebagian Dikirim',
                'delivered' => 'Terkirim', 'closed' => 'Ditutup', 'cancelled' => 'Dibatalkan',
            ],
            'selects' => [['name' => 'partner_id', 'label' => 'Pelanggan', 'options' => $customers]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-file-off" title="Belum ada pesanan penjualan"
                         message="Buat pesanan untuk memulai proses pengiriman dan penagihan." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. SO</th><th>Tanggal</th><th>Pelanggan</th><th>Gudang</th>
                        <th class="text-num">Total</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('sales-orders.show', $document) }}" class="fw-bold">{{ $document->so_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ $document->customer?->name }}</td>
                            <td class="text-secondary">{{ $document->warehouse?->name }}</td>
                            <td class="text-num">{{ rupiah($document->total) }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('sales-orders.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('sales-orders.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isEditable())
                                            @can('sales-order.edit')
                                                <a class="dropdown-item" href="{{ route('sales-orders.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('sales-order.delete')
                                                <x-delete-form :action="route('sales-orders.destroy', $document)" label="Hapus" />
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
