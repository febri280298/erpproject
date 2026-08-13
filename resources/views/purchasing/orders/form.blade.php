@extends('layouts.app')

@section('title', $document ? 'Ubah Pesanan Pembelian' : 'Buat Pesanan Pembelian')
@section('pretitle', 'Pembelian · ' . $nextNumber)

@section('content')
    @module('purchase_requisition')
        @isset($requisition)
            <div class="alert alert-info">
                Dibuat dari permintaan pembelian
                <a href="{{ route('purchase-requisitions.show', $requisition) }}" class="fw-bold">{{ $requisition->pr_no }}</a>.
            </div>
        @endisset
    @endmodule

    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="purchase_price"
                :discount-amount="$document->discount_amount ?? 0"
                :shipping-cost="$document->shipping_cost ?? 0"
                :cancel-url="route('purchase-orders.index')"
                submit-label="Simpan Pesanan">

        <x-slot:header>
            <input type="hidden" name="purchase_requisition_id"
                   value="{{ old('purchase_requisition_id', $document->purchase_requisition_id ?? ($requisition->id ?? null)) }}">

            <x-form.input name="date" label="Tanggal" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="expected_date" label="Perkiraan Tiba" type="date"
                          :value="optional($document?->expected_date)->toDateString()" col="col-md-3" />
            <x-form.select name="partner_id" label="Pemasok" :options="$suppliers"
                           :value="$document->partner_id ?? null" required col="col-md-6" />

            <x-form.select name="warehouse_id" label="Gudang Tujuan" :options="$warehouses"
                           :value="$document->warehouse_id ?? $defaultWarehouse" required col="col-md-4" />
            <x-form.select name="payment_term_id" label="Termin Pembayaran" :options="$paymentTerms"
                           :value="$document->payment_term_id ?? null" col="col-md-4" />
            <div class="col-md-4">
                <label class="form-label">Nomor Dokumen</label>
                <input type="text" class="form-control" value="{{ $nextNumber }}" disabled>
                <small class="form-hint">Dibuat otomatis saat disimpan.</small>
            </div>
        </x-slot:header>

        <x-slot:footer>
            <div class="row g-3">
                <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="2" />
                <x-form.textarea name="terms" label="Syarat & Ketentuan" :value="$document->terms ?? null" rows="2" />
            </div>
        </x-slot:footer>
    </x-doc-form>
@endsection
