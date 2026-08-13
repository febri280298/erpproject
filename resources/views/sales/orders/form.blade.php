@extends('layouts.app')

@section('title', $document ? 'Ubah Pesanan Penjualan' : 'Buat Pesanan Penjualan')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    @isset($sourceQuotation)
        <div class="alert alert-info">
            Dibuat dari penawaran
            <a href="{{ route('quotations.show', $sourceQuotation) }}" class="fw-bold">{{ $sourceQuotation->quotation_no }}</a>.
        </div>
    @endisset

    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="sale_price"
                :discount-amount="$document->discount_amount ?? 0"
                :shipping-cost="$document->shipping_cost ?? 0"
                :cancel-url="route('sales-orders.index')"
                submit-label="Simpan Pesanan">

        <x-slot:header>
            <input type="hidden" name="quotation_id"
                   value="{{ old('quotation_id', $document->quotation_id ?? ($sourceQuotation->id ?? null)) }}">

            <x-form.input name="date" label="Tanggal" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="delivery_date" label="Rencana Kirim" type="date"
                          :value="optional($document?->delivery_date)->toDateString()" col="col-md-3" />
            <x-form.select name="partner_id" label="Pelanggan" :options="$customers"
                           :value="$document->partner_id ?? ($sourceQuotation->partner_id ?? null)" required col="col-md-6" />

            <x-form.select name="warehouse_id" label="Gudang Pengirim" :options="$warehouses"
                           :value="$document->warehouse_id ?? $defaultWarehouse" required col="col-md-4" />
            <x-form.select name="payment_term_id" label="Termin Pembayaran" :options="$paymentTerms"
                           :value="$document->payment_term_id ?? null" col="col-md-4" />
            <x-form.input name="customer_po_no" label="No. PO Pelanggan" :value="$document->customer_po_no ?? null" col="col-md-4" />
        </x-slot:header>

        <x-slot:footer>
            <div class="row g-3">
                <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="2" />
                <x-form.textarea name="terms" label="Syarat & Ketentuan" :value="$document->terms ?? null" rows="2" />
            </div>
        </x-slot:footer>
    </x-doc-form>
@endsection
