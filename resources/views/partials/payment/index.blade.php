@php
    /**
     * Shared payment list. Supplier payments and customer receipts differ only
     * in labels and the relation used for the counterparty.
     */
@endphp

<x-card flush>
    @include('partials.doc-filters', [
        'searchPlaceholder' => 'Nomor / referensi…',
        'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'],
        'selects' => [['name' => 'partner_id', 'label' => $partnerLabel, 'options' => $partners]],
    ])

    @if($documents->isEmpty())
        <div class="card-body">
            <x-empty icon="ti ti-cash-off" :title="'Belum ada '.strtolower($title)"
                     message="Catat pembayaran untuk melunasi faktur yang masih terbuka." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Nomor</th><th>Tanggal</th><th>{{ $partnerLabel }}</th>
                    <th>Akun Kas/Bank</th><th>Metode</th><th>Referensi</th>
                    <th class="text-num">Jumlah</th><th>Status</th><th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($documents as $document)
                    <tr>
                        <td><a href="{{ route($routeName.'.show', $document) }}" class="fw-bold">{{ $document->payment_no }}</a></td>
                        <td>{{ fdate($document->date) }}</td>
                        <td>{{ $document->{$partnerRelation}?->name }}</td>
                        <td class="text-secondary">{{ $document->account?->label() }}</td>
                        <td class="text-secondary">{{ $document->methodLabel() }}</td>
                        <td class="text-secondary">{{ $document->reference ?? '—' }}</td>
                        <td class="text-num fw-bold">{{ rupiah($document->amount) }}</td>
                        <td><x-status :value="$document->status" /></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route($routeName.'.show', $document) }}">
                                        <i class="ti ti-eye me-2"></i> Detail
                                    </a>
                                    @if($document->isDraft())
                                        <x-delete-form :action="route($routeName.'.destroy', $document)" label="Hapus" />
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
