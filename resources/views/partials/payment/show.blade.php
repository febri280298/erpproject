<div class="row g-3">
    <div class="col-lg-8">
        <x-card title="Faktur yang Dibayar" flush>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. Faktur</th><th>Tanggal Faktur</th>
                        <th class="text-num">Total Faktur</th><th class="text-num">Dialokasikan</th><th>Status Faktur</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($document->items as $item)
                        <tr>
                            <td><a href="{{ route($invoiceRoute.'.show', $item->purchase_invoice_id ?? $item->sales_invoice_id) }}">
                                {{ $item->invoice?->invoice_no }}
                            </a></td>
                            <td>{{ fdate($item->invoice?->date) }}</td>
                            <td class="text-num">{{ rupiah($item->invoice?->total, 2) }}</td>
                            <td class="text-num fw-bold">{{ rupiah($item->amount, 2) }}</td>
                            <td><x-status :value="$item->invoice?->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">Total</td>
                        <td class="text-num fs-3">{{ rupiah($document->amount, 2) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            @if($document->notes)
                <div class="card-body border-top">
                    <strong>Catatan</strong>
                    <div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div>
                </div>
            @endif
        </x-card>

        @include('partials.doc-journals', ['journals' => $document->journals])
    </div>

    <div class="col-lg-4">
        <x-card title="Informasi Pembayaran">
            <dl class="row mb-0">
                <dt class="col-5 text-secondary">Status</dt>
                <dd class="col-7"><x-status :value="$document->status" /></dd>
                <dt class="col-5 text-secondary">Tanggal</dt>
                <dd class="col-7">{{ fdate($document->date) }}</dd>
                <dt class="col-5 text-secondary">{{ $partnerLabel }}</dt>
                <dd class="col-7">
                    <a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->{$partnerRelation}?->name }}</a>
                </dd>
                <dt class="col-5 text-secondary">Akun Kas/Bank</dt>
                <dd class="col-7">{{ $document->account?->label() }}</dd>
                <dt class="col-5 text-secondary">Metode</dt>
                <dd class="col-7">{{ $document->methodLabel() }}</dd>
                <dt class="col-5 text-secondary">Referensi</dt>
                <dd class="col-7">{{ $document->reference ?? '—' }}</dd>
                <dt class="col-5 text-secondary">Jumlah</dt>
                <dd class="col-7 fw-bold">{{ rupiah($document->amount, 2) }}</dd>
                <dt class="col-5 text-secondary">Dibuat oleh</dt>
                <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                <dt class="col-5 text-secondary">Diposting</dt>
                <dd class="col-7">{{ fdatetime($document->posted_at) }}</dd>
            </dl>
        </x-card>
    </div>
</div>
