@extends('layouts.app')

@section('title', 'Surat Jalan')
@section('pretitle', 'Penjualan')

@section('actions')
    @can('delivery-order.create')
        <a href="{{ route('delivery-orders.create') }}" class="btn btn-primary">
            <i class="ti ti-truck me-1"></i> Buat Surat Jalan
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor surat jalan…',
            'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'],
            'selects' => [
                ['name' => 'partner_id', 'label' => 'Pelanggan', 'options' => $customers],
                ['name' => 'warehouse_id', 'label' => 'Gudang', 'options' => $warehouses],
            ],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-truck-off" title="Belum ada surat jalan"
                         message="Surat jalan dibuat dari pesanan penjualan yang sudah dikonfirmasi." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. SJ</th><th>Tanggal</th><th>No. SO</th><th>Pelanggan</th>
                        <th>Gudang</th><th>Kendaraan</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('delivery-orders.show', $document) }}" class="fw-bold">{{ $document->do_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>
                                @if($document->salesOrder)
                                    <a href="{{ route('sales-orders.show', $document->sales_order_id) }}">{{ $document->salesOrder->so_no }}</a>
                                @else — @endif
                            </td>
                            <td>{{ $document->customer?->name }}</td>
                            <td class="text-secondary">{{ $document->warehouse?->name }}</td>
                            <td class="text-secondary">{{ $document->vehicle_no ?? '—' }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('delivery-orders.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('delivery-orders.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isDraft())
                                            @can('delivery-order.delete')
                                                <x-delete-form :action="route('delivery-orders.destroy', $document)" label="Hapus" />
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
