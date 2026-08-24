@extends('layouts.app')

@section('title', 'Pilih Pesanan Pembelian')
@section('pretitle', 'Pembelian · Faktur dari beberapa PO')

@section('content')
    <x-card title="Pilih Supplier"
            subtitle="Pesanan yang masih menyisakan barang belum ditagih akan tampil di bawah.">
        <form method="GET" class="row g-2 align-items-end">
            <x-form.select name="partner_id" label="Supplier" :options="$suppliers" :value="$partnerId"
                           required col="col-md-6" placeholder="— Pilih supplier —" />
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-search me-1"></i> Tampilkan Pesanan
                </button>
            </div>
        </form>
    </x-card>

    @if($partnerId)
        <form method="GET" action="{{ route('purchase-invoices.from-orders') }}" class="mt-3">
            <x-card title="Pesanan yang Bisa Ditagih"
                    subtitle="Centang beberapa sekaligus untuk digabung menjadi satu faktur.">
                @if($orders->isEmpty())
                    <x-empty icon="ti ti-file-off" title="Tidak ada pesanan yang menunggu ditagih"
                             message="Semua pesanan supplier ini sudah ditagih penuh, atau belum disetujui." />
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th class="w-1">
                                    {{-- Centang semua: daftar bisa panjang dan mencentang satu
                                         per satu adalah pekerjaan yang tidak perlu. --}}
                                    <input type="checkbox" class="form-check-input m-0" id="centang-semua"
                                           aria-label="Centang semua pesanan">
                                </th>
                                <th>Nomor PO</th>
                                <th>Tanggal</th>
                                <th class="text-num">Item Belum Ditagih</th>
                                <th class="text-num">Nilai Belum Ditagih</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($orders as $order)
                                @php
                                    $sisaItem = $order->items->filter(fn ($i) => $i->uninvoicedQty() > 0);
                                    $sisaNilai = $sisaItem->sum(fn ($i) => $i->uninvoicedQty() * (float) $i->unit_price);
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" name="purchase_order_ids[]" value="{{ $order->id }}"
                                               class="form-check-input m-0 pilih-po"
                                               aria-label="Pilih {{ $order->po_no }}">
                                    </td>
                                    <td class="fw-bold">
                                        <a href="{{ route('purchase-orders.show', $order) }}">{{ $order->po_no }}</a>
                                    </td>
                                    <td class="text-secondary">{{ fdate($order->date) }}</td>
                                    <td class="text-num">
                                        {{ fnum($sisaItem->count(), 0) }} dari {{ fnum($order->items->count(), 0) }}
                                    </td>
                                    <td class="text-num fw-bold">{{ rupiah($sisaNilai) }}</td>
                                    <td><x-status :value="$order->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('purchase-invoices.create') }}" class="btn btn-link">
                            Buat faktur tanpa pesanan
                        </a>
                        <button type="submit" class="btn btn-primary" @disabled($orders->isEmpty())>
                            <i class="ti ti-arrow-right me-1"></i> Lanjut ke Faktur
                        </button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const semua = document.getElementById('centang-semua');

            if (! semua) {
                return;
            }

            const baris = [...document.querySelectorAll('.pilih-po')];

            semua.addEventListener('change', () => {
                baris.forEach((c) => { c.checked = semua.checked; });
            });

            // Centang induk mengikuti isinya: tercentang penuh, sebagian, atau kosong.
            baris.forEach((c) => c.addEventListener('change', () => {
                const jumlah = baris.filter((x) => x.checked).length;

                semua.checked = jumlah === baris.length;
                semua.indeterminate = jumlah > 0 && jumlah < baris.length;
            }));
        });
    </script>
@endpush
