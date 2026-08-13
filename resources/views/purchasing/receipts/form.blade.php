@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('pretitle', 'Pembelian · ' . $nextNumber)

@section('content')
    @if(! $order)
        {{-- Step 1: choose which purchase order is being received against. --}}
        <x-card title="Pilih Pesanan Pembelian">
            @if($openOrders->isEmpty())
                <x-empty icon="ti ti-shopping-cart-off" title="Tidak ada pesanan menunggu penerimaan"
                         message="Setujui sebuah pesanan pembelian terlebih dahulu." />
            @else
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label required" for="purchase_order_id">Pesanan Pembelian</label>
                        <select name="purchase_order_id" id="purchase_order_id" class="form-select" required>
                            <option value="">— Pilih pesanan —</option>
                            @foreach($openOrders as $openOrder)
                                <option value="{{ $openOrder->id }}">
                                    {{ $openOrder->po_no }} · {{ $openOrder->supplier?->name }} · {{ fdate($openOrder->date) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Lanjut</button>
                    </div>
                </form>
            @endif
        </x-card>
    @else
        {{-- Step 2: enter received quantities per outstanding PO line. --}}
        <form method="POST" action="{{ route('goods-receipts.store') }}">
            @csrf
            <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">

            <x-card title="Informasi Penerimaan">
                <div class="row g-3">
                    <x-form.input name="date" label="Tanggal Terima" type="date" :value="now()->toDateString()" required col="col-md-3" />
                    <x-form.input name="supplier_do_no" label="No. Surat Jalan Pemasok" col="col-md-3" />
                    <div class="col-md-3">
                        <label class="form-label">Pemasok</label>
                        <input type="text" class="form-control" value="{{ $order->supplier?->name }}" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gudang Tujuan</label>
                        <input type="text" class="form-control" value="{{ $order->warehouse?->name }}" disabled>
                    </div>
                </div>
            </x-card>

            <x-card title="Item Pesanan {{ $order->po_no }}" flush class="mt-3">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="text-num">Dipesan</th>
                            <th class="text-num">Sudah Diterima</th>
                            <th class="text-num">Sisa</th>
                            <th style="width:10rem" class="text-end">Terima Sekarang</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($order->items as $index => $item)
                            @continue($item->outstandingQty() <= 0)
                            <tr>
                                <td>
                                    <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }}</div>
                                </td>
                                <td class="text-num">{{ fnum($item->quantity) }} {{ $item->product?->uom?->code }}</td>
                                <td class="text-num">{{ fnum($item->received_qty) }}</td>
                                <td class="text-num fw-bold text-orange">{{ fnum($item->outstandingQty()) }}</td>
                                <td>
                                    <input type="number" step="0.0001" min="0" max="{{ $item->outstandingQty() }}"
                                           name="items[{{ $index }}][quantity]" value="{{ $item->outstandingQty() }}"
                                           class="form-control text-end">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-body border-top">
                    <x-form.textarea name="notes" label="Catatan" rows="2" />
                </div>

                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('goods-receipts.index') }}" class="btn btn-link">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan sebagai Draft
                        </button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    @endif
@endsection
