@extends('layouts.app')

@section('title', $document ? 'Ubah Penawaran' : 'Buat Penawaran')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="sale_price"
                :discount-amount="$document->discount_amount ?? 0"
                :shipping-cost="$document->shipping_cost ?? 0"
                :cancel-url="route('quotations.index')"
                submit-label="Simpan Penawaran">

        <x-slot:header>
            <x-form.input name="date" label="Tanggal" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="valid_until" label="Berlaku Sampai" type="date"
                          :value="optional($document?->valid_until)->toDateString() ?? now()->addDays(14)->toDateString()" col="col-md-3" />
            <x-form.select name="partner_id" label="Pelanggan" :options="$customers"
                           :value="$document->partner_id ?? null" required col="col-md-6" />
        </x-slot:header>

        <x-slot:footer>
            <div class="row g-3">
                <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="2" />
                <x-form.textarea name="terms" label="Syarat & Ketentuan" :value="$document->terms ?? null" rows="2" />
            </div>
        </x-slot:footer>
    </x-doc-form>
@endsection
