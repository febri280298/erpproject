@extends('layouts.app')

@section('title', 'Permintaan Pembelian')
@section('pretitle', 'Pembelian')

@section('actions')
    @can('purchase-requisition.create')
        <a href="{{ route('purchase-requisitions.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Permintaan
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor PR…',
            'statuses' => ['draft' => 'Draft', 'submitted' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'closed' => 'Ditutup'],
            'selects' => [['name' => 'department_id', 'label' => 'Departemen', 'options' => $departments]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-clipboard-off" title="Belum ada permintaan pembelian"
                         message="Ajukan permintaan agar tim pembelian dapat memprosesnya." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. PR</th><th>Tanggal</th><th>Dibutuhkan</th><th>Departemen</th>
                        <th>Pemohon</th><th class="text-num">Item</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('purchase-requisitions.show', $document) }}" class="fw-bold">{{ $document->pr_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ fdate($document->required_date) }}</td>
                            <td class="text-secondary">{{ $document->department?->name ?? '—' }}</td>
                            <td class="text-secondary">{{ $document->requester?->name ?? '—' }}</td>
                            <td class="text-num">{{ $document->items_count }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('purchase-requisitions.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @if($document->isEditable())
                                            @can('purchase-requisition.edit')
                                                <a class="dropdown-item" href="{{ route('purchase-requisitions.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('purchase-requisition.delete')
                                                <x-delete-form :action="route('purchase-requisitions.destroy', $document)" label="Hapus" />
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
