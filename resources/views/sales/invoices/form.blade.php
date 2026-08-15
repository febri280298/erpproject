@extends('layouts.app')

@section('title', $document ? 'Ubah Faktur Penjualan' : 'Buat Faktur Penjualan')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    @isset($sourceOrder)
        <div class="alert alert-info">
            Dibuat dari pesanan
            <a href="{{ route('sales-orders.show', $sourceOrder) }}" class="fw-bold">{{ $sourceOrder->so_no }}</a>.
        </div>
    @endisset

    @isset($sourceDeliveries)
        <div class="alert alert-info">
            <h4 class="alert-title">
                Menagih {{ $sourceDeliveries->count() }} surat jalan
            </h4>
            <div class="mt-1">
                @foreach($sourceDeliveries as $sj)
                    <a href="{{ route('delivery-orders.show', $sj) }}" class="fw-bold">{{ $sj->do_no }}</a>
                    <span class="text-secondary">({{ fdate($sj->date) }})</span>{{ ! $loop->last ? ' · ' : '' }}
                @endforeach
            </div>
            <div class="text-secondary small mt-1">
                Barang yang sama dengan harga sama digabung menjadi satu baris.
                Barang yang sudah diretur tidak ikut ditagih.
            </div>
        </div>
    @endisset

    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="sale_price"
                :discount-amount="$document->discount_amount ?? 0"
                :shipping-cost="$document->shipping_cost ?? 0"
                :cancel-url="route('sales-invoices.index')"
                submit-label="Simpan Faktur">

        <x-slot:header>
            @isset($sourceDeliveries)
                @foreach($sourceDeliveries as $sj)
                    <input type="hidden" name="delivery_order_ids[]" value="{{ $sj->id }}">
                @endforeach
            @endisset

            <x-form.input name="date" label="Tanggal Faktur" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="due_date" label="Jatuh Tempo" type="date"
                          :value="optional($document?->due_date)->toDateString() ?? ($defaultDueDate ?? null)" col="col-md-3" />
            <x-form.select name="partner_id" label="Pelanggan" :options="$customers"
                           :value="$document->partner_id ?? ($sourceOrder->partner_id ?? ($sourceDeliveries[0]->partner_id ?? null))" required col="col-md-6" />

            <x-form.select name="sales_order_id" label="Pesanan Penjualan" col="col-md-6"
                           :value="$document->sales_order_id ?? ($sourceOrder->id ?? ($lockedSalesOrderId ?? null))"
                           :options="$openOrders->mapWithKeys(fn($o) => [$o->id => $o->so_no.' — '.$o->customer?->name])"
                           placeholder="— Tanpa pesanan —" />
            <div class="col-md-6">
                <label class="form-label">Nomor Dokumen</label>
                <input type="text" class="form-control" value="{{ $nextNumber }}" disabled>
            </div>
        </x-slot:header>

        <x-slot:footer>
            <div class="row g-3">
                <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="2" />
                <x-form.textarea name="terms" label="Syarat Pembayaran"
                                 :value="$document->terms ?? setting('invoice_footer_note')" rows="2" />
            </div>
        </x-slot:footer>
    </x-doc-form>
@endsection
