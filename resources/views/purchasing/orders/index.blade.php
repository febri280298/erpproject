@extends('layouts.app')

@section('title', 'Pesanan Pembelian')
@section('pretitle', 'Pembelian')

@section('actions')
    @can('purchase-order.create')
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Pesanan
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor PO…',
            'statuses' => [
                'draft' => 'Draft', 'approved' => 'Disetujui', 'partial' => 'Sebagian Diterima',
                'received' => 'Diterima', 'closed' => 'Ditutup', 'cancelled' => 'Dibatalkan',
            ],
            'selects' => [['name' => 'partner_id', 'label' => 'Pemasok', 'options' => $suppliers]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-shopping-cart-off" title="Belum ada pesanan pembelian"
                         message="Buat pesanan untuk memulai proses pengadaan." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. PO</th>
                        <th>Tanggal</th>
                        <th>Pemasok</th>
                        <th>Gudang</th>
                        <th class="text-num">Total</th>
                        <th>Progres Terima</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('purchase-orders.show', $document) }}" class="fw-bold">{{ $document->po_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ $document->supplier?->name }}</td>
                            <td class="text-secondary">{{ $document->warehouse?->name }}</td>
                            <td class="text-num">{{ rupiah($document->total) }}</td>
                            <td style="min-width:8rem">
                                @php $percent = $document->relationLoaded('items') ? $document->receivedPercent() : null; @endphp
                                @if($document->status === 'received')
                                    <span class="badge bg-green-lt">100%</span>
                                @elseif($document->status === 'partial')
                                    <span class="badge bg-orange-lt">Sebagian</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('purchase-orders.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('purchase-orders.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isEditable())
                                            @can('purchase-order.edit')
                                                <a class="dropdown-item" href="{{ route('purchase-orders.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('purchase-order.delete')
                                                <x-delete-form :action="route('purchase-orders.destroy', $document)" label="Hapus" />
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
