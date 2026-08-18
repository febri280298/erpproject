@extends('layouts.app')

@section('title', $document->adjustment_no)
@section('pretitle', 'Penyesuaian Stok')

@section('actions')
    @if($document->isDraft())
        @can('stock-adjustment.edit')
            <a href="{{ route('stock-adjustments.edit', $document) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endcan
        @can('stock-adjustment.post')
            <x-action-form :action="route('stock-adjustments.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Posting penyesuaian ini? Stok dan jurnal selisih persediaan akan diperbarui." />
        @endcan
    @elseif($document->status === 'posted')
        @can('stock-adjustment.post')
            <x-action-form :action="route('stock-adjustments.cancel', $document)" label="Batalkan Posting" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger" confirm="Batalkan posting penyesuaian ini?" />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Rincian Perhitungan" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="width:2.5rem">#</th><th>Produk</th>
                            <th class="text-num">Stok Sistem</th><th class="text-num">Hasil Hitung</th>
                            <th class="text-num">Selisih</th><th class="text-num">HPP</th>
                            <th class="text-num">Nilai Selisih</th><th>Catatan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($document->items as $index => $item)
                            @php $difference = (float) $item->difference; @endphp
                            <tr>
                                <td class="text-secondary">{{ $index + 1 }}</td>
                                <td>
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }}</div>
                                </td>
                                <td class="text-num text-secondary">{{ fnum($item->system_qty) }}</td>
                                <td class="text-num">{{ fnum($item->actual_qty) }}</td>
                                <td class="text-num fw-bold {{ $difference > 0 ? 'text-success' : ($difference < 0 ? 'text-danger' : 'text-secondary') }}">
                                    {{ $difference > 0 ? '+' : '' }}{{ fnum($difference) }}
                                </td>
                                <td class="text-num text-secondary">{{ rupiah($item->unit_cost) }}</td>
                                <td class="text-num">{{ rupiah($difference * (float) $item->unit_cost) }}</td>
                                <td class="text-secondary">{{ $item->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total Nilai Penyesuaian</td>
                            <td class="text-num fs-4">{{ rupiah($document->valueImpact()) }}</td>
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

            @include('partials.doc-journals', ['journals' => $document->journals ?? collect()])
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Dokumen">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>
                    <dt class="col-5 text-secondary">Gudang</dt>
                    <dd class="col-7">{{ $document->warehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">Alasan</dt>
                    <dd class="col-7">{{ $document->reason ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Diposting</dt>
                    <dd class="col-7">{{ fdatetime($document->posted_at) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
