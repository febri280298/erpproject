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

    @isset($sourceOrders)
        <div class="alert alert-info">
            Menagih {{ $sourceOrders->count() }} pesanan sekaligus:
            @foreach($sourceOrders as $po)
                <a href="{{ route('purchase-orders.show', $po) }}" class="fw-bold">{{ $po->po_no }}</a>@if(! $loop->last), @endif
            @endforeach
            <div class="small mt-1">
                Baris dengan produk, harga, dan diskon yang sama sudah digabung. Yang harganya
                berbeda tetap terpisah, karena menggabungkannya akan mengubah nilai tagihan.
            </div>
        </div>
    @endisset

    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="purchase_price"
                :discount-amount="$document->discount_amount ?? 0"
                :shipping-cost="$document->shipping_cost ?? 0"
                :cancel-url="route('purchase-invoices.index')"
                submit-label="Simpan Faktur">

        <x-slot:header>
            {{-- Daftar pesanan yang ditagih ikut terkirim; kolom tunggal
                 purchase_order_id di bawah hanya dipakai jalur satu pesanan. --}}
            @isset($sourceOrders)
                @foreach($sourceOrders as $po)
                    <input type="hidden" name="purchase_order_ids[]" value="{{ $po->id }}">
                @endforeach
            @endisset
            @if(isset($document) && $document?->exists)
                @foreach($document->purchaseOrders as $po)
                    <input type="hidden" name="purchase_order_ids[]" value="{{ $po->id }}">
                @endforeach
            @endif

            <x-form.input name="date" label="Tanggal Faktur" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="due_date" label="Jatuh Tempo" type="date"
                          :value="optional($document?->due_date)->toDateString() ?? ($defaultDueDate ?? null)" col="col-md-3" />
            @php
                /*
                 * Pemasok terisi baik dari satu pesanan maupun dari beberapa.
                 * Tanpa cabang $sourceOrders, isian ini kosong pada jalur banyak
                 * pesanan dan validasi peramban memblokir simpan tanpa pesan
                 * yang terlihat di layar.
                 *
                 * Dihitung di sini, bukan dirangkai dengan ?? di dalam atribut:
                 * ?? meredam variabel tak terdefinisi hanya untuk akses properti,
                 * TIDAK untuk pemanggilan method. $sourceOrders->first() tetap
                 * meledak di halaman Buat Faktur biasa, tempat variabel itu
                 * memang tidak pernah ada.
                 */
                $pemasokTerpilih = $document->partner_id
                    ?? (isset($sourceOrder) ? $sourceOrder->partner_id : null)
                    ?? (isset($sourceOrders) ? $sourceOrders->first()?->partner_id : null);
            @endphp

            <x-form.select name="partner_id" label="Pemasok" :options="$suppliers"
                           :value="$pemasokTerpilih" required col="col-md-6" />

            <x-form.input name="supplier_invoice_no" label="No. Faktur Pemasok"
                          :value="$document->supplier_invoice_no ?? null" col="col-md-4" />
            @empty($sourceOrders)
            <x-form.select name="purchase_order_id" label="Pesanan Pembelian" col="col-md-4"
                           :value="$document->purchase_order_id ?? ($sourceOrder->id ?? null)"
                           :options="$openOrders->mapWithKeys(fn($o) => [$o->id => $o->po_no.' — '.$o->supplier?->name])"
                           placeholder="— Tanpa pesanan —" />
            @endempty
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
