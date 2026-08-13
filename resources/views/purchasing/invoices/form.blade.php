@extends('layouts.app')

@section('title', $document ? 'Ubah Faktur Pembelian' : 'Buat Faktur Pembelian')
@section('pretitle', 'Pembelian · ' . $nextNumber)

@section('content')
    @isset($sourceOrder)
        <div class="alert alert-info">
            Dibuat dari pesanan
            <a href="{{ route('purchase-orders.show', $sourceOrder) }}" class="fw-bold">{{ $sourceOrder->po_no }}</a>.
        </div>
    @endisset

    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="purchase_price"
                :discount-amount="$document->discount_amount ?? 0"
                :shipping-cost="$document->shipping_cost ?? 0"
                :cancel-url="route('purchase-invoices.index')"
                submit-label="Simpan Faktur">

        <x-slot:header>
            <x-form.input name="date" label="Tanggal Faktur" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="due_date" label="Jatuh Tempo" type="date"
                          :value="optional($document?->due_date)->toDateString() ?? ($defaultDueDate ?? null)" col="col-md-3" />
            <x-form.select name="partner_id" label="Pemasok" :options="$suppliers"
                           :value="$document->partner_id ?? ($sourceOrder->partner_id ?? null)" required col="col-md-6" />

            <x-form.input name="supplier_invoice_no" label="No. Faktur Pemasok"
                          :value="$document->supplier_invoice_no ?? null" col="col-md-4" />
            <x-form.select name="purchase_order_id" label="Pesanan Pembelian" col="col-md-4"
                           :value="$document->purchase_order_id ?? ($sourceOrder->id ?? null)"
                           :options="$openOrders->mapWithKeys(fn($o) => [$o->id => $o->po_no.' — '.$o->supplier?->name])"
                           placeholder="— Tanpa pesanan —" />
            <div class="col-md-4">
                <label class="form-label">Nomor Dokumen</label>
                <input type="text" class="form-control" value="{{ $nextNumber }}" disabled>
            </div>
        </x-slot:header>

        <x-slot:footer>
            <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="3" />
        </x-slot:footer>
    </x-doc-form>
@endsection
