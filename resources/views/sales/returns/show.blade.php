@extends('layouts.app')

@section('title', $document->return_no)
@section('pretitle', 'Retur Penjualan')

@section('actions')
    <a href="{{ route('sales-returns.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @can('sales-return.post')
        @if($document->isDraft())
            <x-action-form :action="route('sales-returns.post', $document)" label="Posting"
                           icon="ti ti-checks" class="btn btn-primary"
                           confirm="Posting retur ini? Stok akan bertambah dan pembukuan diperbarui." />
        @elseif($document->status === 'posted')
            <x-action-form :action="route('sales-returns.cancel', $document)" label="Batalkan"
                           icon="ti ti-x" class="btn btn-outline-danger"
                           confirm="Batalkan retur ini? Stok, HPP, dan nota kredit akan dikembalikan." />
        @endif
    @endcan

    @can('sales-return.delete')
        @if($document->isDraft())
            <x-delete-form :action="route('sales-returns.destroy', $document)" label="Hapus"
                           class="btn btn-outline-danger" />
        @endif
    @endcan
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-lg-4">
            <x-card title="Informasi Retur">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary fw-normal">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>

                    <dt class="col-5 text-secondary fw-normal">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Customer</dt>
                    <dd class="col-7">
                        <a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->customer?->name }}</a>
                    </dd>

                    <dt class="col-5 text-secondary fw-normal">Gudang</dt>
                    <dd class="col-7">{{ $document->warehouse?->name }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Surat Jalan</dt>
                    <dd class="col-7">
                        @if($document->deliveryOrder)
                            <a href="{{ route('delivery-orders.show', $document->delivery_order_id) }}">{{ $document->deliveryOrder->do_no }}</a>
                        @else — @endif
                    </dd>

                    <dt class="col-5 text-secondary fw-normal">Alasan</dt>
                    <dd class="col-7">{{ $document->reason ?? '—' }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>

                    @if($document->notes)
                        <dt class="col-5 text-secondary fw-normal">Catatan</dt>
                        <dd class="col-7">{{ $document->notes }}</dd>
                    @endif
                </dl>
            </x-card>

            <x-card title="Nota Kredit" class="mt-3">
                @if($document->hasCreditNote() && $document->salesInvoice)
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary fw-normal">Faktur</dt>
                        <dd class="col-7">
                            <a href="{{ route('sales-invoices.show', $document->sales_invoice_id) }}">{{ $document->salesInvoice->invoice_no }}</a>
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Nilai kredit</dt>
                        <dd class="col-7 fw-bold">{{ rupiah($document->total) }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Sisa tagihan</dt>
                        <dd class="col-7">{{ rupiah($document->salesInvoice->outstandingAmount()) }}</dd>
                    </dl>
                @else
                    <div class="text-secondary">
                        Tanpa nota kredit — hanya stok dan HPP yang terpengaruh.
                    </div>
                @endif
            </x-card>

            @if($document->status === 'posted')
                <x-card title="Dampak Persediaan" class="mt-3">
                    <dl class="row mb-0">
                        <dt class="col-7 text-secondary fw-normal">Masuk kembali ke stok</dt>
                        <dd class="col-5 text-end">{{ rupiah($document->cost_returned) }}</dd>
                        <dt class="col-7 text-secondary fw-normal">Dibebankan sebagai rusak</dt>
                        <dd class="col-5 text-end text-danger">{{ rupiah($document->cost_damaged) }}</dd>
                    </dl>
                </x-card>
            @endif
        </div>

        <div class="col-lg-8">
            <x-card title="Barang Dikembalikan" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>Produk</th><th class="text-num">Qty</th><th>Kondisi</th>
                            <th class="text-num">Harga</th><th class="text-num">Disc</th>
                            <th class="text-num">Jumlah</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($document->items as $item)
                            <tr>
                                <td>
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">
                                        {{ $item->product?->sku }}{{ $item->notes ? ' · '.$item->notes : '' }}
                                    </div>
                                </td>
                                <td class="text-num">{{ fnum($item->quantity) }} {{ $item->product?->uom?->code }}</td>
                                <td>
                                    <span class="badge bg-{{ $item->conditionColor() }}-lt">{{ $item->conditionLabel() }}</span>
                                </td>
                                <td class="text-num">{{ rupiah($item->unit_price) }}</td>
                                <td class="text-num">{{ fnum($item->discount_percent) }}%</td>
                                <td class="text-num">{{ rupiah($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr>
                            <td colspan="5" class="text-end text-secondary">Subtotal</td>
                            <td class="text-num">{{ rupiah($document->subtotal) }}</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-end text-secondary">PPN</td>
                            <td class="text-num">{{ rupiah($document->tax_amount) }}</td>
                        </tr>
                        <tr class="fw-bold">
                            <td colspan="5" class="text-end">Nilai Retur</td>
                            <td class="text-num fs-3">{{ rupiah($document->total) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            @include('partials.doc-journals', ['journals' => $document->journals])
        </div>
    </div>
@endsection
