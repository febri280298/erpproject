@extends('layouts.app')

@section('title', 'Faktur dari Surat Jalan')
@section('pretitle', 'Penjualan')

@section('content')
    <x-card title="Pilih Customer">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label required" for="partner_id">Customer</label>
                <select name="partner_id" id="partner_id" class="form-select" required data-filter-submit>
                    <option value="">— Pilih customer —</option>
                    @foreach($customers as $id => $name)
                        <option value="{{ $id }}" @selected($partnerId == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-search me-1"></i> Tampilkan
                </button>
            </div>
        </form>
    </x-card>

    @if($partnerId)
        @if($deliveries->isEmpty())
            <x-card class="mt-3">
                <x-empty icon="ti ti-file-check" title="Tidak ada surat jalan yang menunggu ditagih"
                         message="Semua pengiriman customer ini sudah difakturkan, atau belum ada yang diposting." />
            </x-card>
        @else
            {{--
                Centang beberapa surat jalan lalu lanjut; barisnya digabung
                menjadi satu faktur pada langkah berikutnya.
            --}}
            <form method="GET" action="{{ route('sales-invoices.from-deliveries') }}"
                  x-data="{ dipilih: [] }">
                <x-card title="Surat Jalan Belum Ditagih" flush class="mt-3"
                        subtitle="Centang yang akan digabung ke dalam satu faktur.">
                    <x-slot:actions>
                        <span class="text-secondary small me-2">
                            <span x-text="dipilih.length"></span> dari {{ $deliveries->count() }} dipilih
                        </span>
                        <button type="button" class="btn btn-sm"
                                @click="dipilih = $root.querySelectorAll('[name=&quot;delivery_order_ids[]&quot;]').length === dipilih.length
                                        ? [] : Array.from($root.querySelectorAll('[name=&quot;delivery_order_ids[]&quot;]')).map(e => e.value)">
                            Pilih Semua
                        </button>
                    </x-slot:actions>

                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th class="w-1"></th>
                                <th>No. Surat Jalan</th>
                                <th>Tanggal</th>
                                <th>Pesanan</th>
                                <th>Barang</th>
                                <th class="text-num">Nilai</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($deliveries as $delivery)
                                @php
                                    $nilai = $delivery->billableItems()->sum(fn ($i) =>
                                        $i->returnableQty() * (float) ($i->orderItem?->unit_price ?? $i->product?->sale_price ?? 0));
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" name="delivery_order_ids[]" value="{{ $delivery->id }}"
                                               class="form-check-input m-0" x-model="dipilih">
                                    </td>
                                    <td>
                                        <a href="{{ route('delivery-orders.show', $delivery) }}" class="fw-bold">{{ $delivery->do_no }}</a>
                                        @if($delivery->items->sum('returned_qty') > 0)
                                            <span class="badge bg-orange-lt ms-1">ada retur</span>
                                        @endif
                                    </td>
                                    <td>{{ fdate($delivery->date) }}</td>
                                    <td class="text-secondary">{{ $delivery->salesOrder?->so_no ?? 'Tanpa SO' }}</td>
                                    <td class="text-secondary small">
                                        @foreach($delivery->billableItems() as $item)
                                            <div>{{ $item->product?->name }} — {{ fnum($item->returnableQty()) }} {{ $item->product?->uom?->code }}</div>
                                        @endforeach
                                    </td>
                                    <td class="text-num">{{ rupiah($nilai) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end align-items-center">
                            <a href="{{ route('sales-invoices.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary" :disabled="dipilih.length === 0">
                                <i class="ti ti-file-invoice me-1"></i> Buat Faktur
                                <span x-show="dipilih.length > 1" x-cloak>
                                    (<span x-text="dipilih.length"></span> surat jalan)
                                </span>
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        @endif
    @endif
@endsection
