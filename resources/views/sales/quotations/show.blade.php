@extends('layouts.app')

@section('title', $document->quotation_no)
@section('pretitle', 'Penawaran')

@section('actions')
    <a href="{{ route('quotations.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    <a href="{{ route('quotations.pdf', $document) }}" class="btn">
        <i class="ti ti-file-type-pdf me-1"></i> PDF
    </a>

    <a href="{{ route('quotations.excel', $document) }}" class="btn">
        <i class="ti ti-file-spreadsheet me-1"></i> Excel
    </a>

    @can('quotation.edit')
        @if($document->isEditable())
            <a href="{{ route('quotations.edit', $document) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endif

        @if(in_array($document->status, ['draft', 'sent']))
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">Ubah Status</button>
                <div class="dropdown-menu dropdown-menu-end">
                    @if($document->status === 'draft')
                        <x-action-form :action="route('quotations.transition', $document)" label="Tandai Terkirim" icon="ti ti-send">
                            <input type="hidden" name="status" value="sent">
                        </x-action-form>
                    @endif
                    <x-action-form :action="route('quotations.transition', $document)" label="Diterima Pelanggan" icon="ti ti-check">
                        <input type="hidden" name="status" value="accepted">
                    </x-action-form>
                    <x-action-form :action="route('quotations.transition', $document)" label="Ditolak" icon="ti ti-x"
                                   class="dropdown-item text-danger">
                        <input type="hidden" name="status" value="rejected">
                    </x-action-form>
                </div>
            </div>
        @endif
    @endcan

    @if($document->canConvert())
        @can('sales-order.create')
            <a href="{{ route('sales-orders.from-quotation', $document) }}" class="btn btn-primary">
                <i class="ti ti-file-invoice me-1"></i> Buat Pesanan Penjualan
            </a>
        @endcan
    @endif
@endsection

@section('content')
    @if($document->isExpired())
        <div class="alert alert-warning">Penawaran ini sudah melewati masa berlaku ({{ fdate($document->valid_until) }}).</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Rincian Item" flush>
                @include('partials.doc-items-table', ['items' => $document->items])

                <div class="card-body border-top">
                    <div class="row">
                        <div class="col-md-6">
                            @if($document->notes)
                                <div class="mb-2"><strong>Catatan</strong><div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div></div>
                            @endif
                            @if($document->terms)
                                <div><strong>Syarat & Ketentuan</strong><div class="text-secondary">{!! nl2br(e($document->terms)) !!}</div></div>
                            @endif
                        </div>
                        <div class="col-md-6">@include('partials.doc-totals')</div>
                    </div>
                </div>
            </x-card>

            @if($document->salesOrders->isNotEmpty())
                <x-card title="Pesanan Penjualan Terkait" flush class="mt-3">
                    @include('partials.doc-mini-table', [
                        'rows' => $document->salesOrders,
                        'numberField' => 'so_no',
                        'partnerField' => 'customer',
                        'routeName' => 'sales-orders',
                        'emptyMessage' => '',
                    ])
                </x-card>
            @endif
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Dokumen">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>
                    <dt class="col-5 text-secondary">Berlaku Sampai</dt>
                    <dd class="col-7">{{ fdate($document->valid_until) }}</dd>
                    <dt class="col-5 text-secondary">Pelanggan</dt>
                    <dd class="col-7"><a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->customer?->name }}</a></dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
