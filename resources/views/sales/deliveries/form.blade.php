@extends('layouts.app')

@section('title', 'Buat Surat Jalan')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    {{-- Langkah pertama: pilih sumbernya — dari pesanan penjualan atau lepas. --}}
    @if(! $order && ! $manual)
        <div class="row row-cards">
            <div class="col-md-6">
                <x-card title="Dari Pesanan Penjualan"
                        subtitle="Item dan sisa kirim ditarik otomatis dari pesanan.">
                    @if($openOrders->isEmpty())
                        <x-empty icon="ti ti-file-off" title="Tidak ada pesanan menunggu pengiriman"
                                 message="Konfirmasi sebuah pesanan penjualan terlebih dahulu, atau buat surat jalan manual." />
                    @else
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-12">
                                <label class="form-label required" for="sales_order_id">Pesanan Penjualan</label>
                                <select name="sales_order_id" id="sales_order_id" class="form-select" required>
                                    <option value="">— Pilih pesanan —</option>
                                    @foreach($openOrders as $openOrder)
                                        <option value="{{ $openOrder->id }}">
                                            {{ $openOrder->so_no }} · {{ $openOrder->customer?->name }} · {{ fdate($openOrder->date) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti ti-arrow-right me-1"></i> Lanjut
                                </button>
                            </div>
                        </form>
                    @endif
                </x-card>
            </div>

            <div class="col-md-6">
                <x-card title="Tanpa Pesanan Penjualan"
                        subtitle="Pilih customer dan produknya sendiri.">
                    <p class="text-secondary">
                        Dipakai untuk kiriman contoh barang, penggantian barang, atau penjualan
                        langsung yang tidak melewati pesanan penjualan.
                    </p>
                    <a href="{{ route('delivery-orders.create', ['mode' => 'manual']) }}" class="btn btn-outline-primary w-100">
                        <i class="ti ti-pencil-plus me-1"></i> Buat Surat Jalan Manual
                    </a>
                </x-card>
            </div>
        </div>

    {{-- Jalur A: menarik sisa item dari pesanan penjualan --}}
    @elseif($order)
        <form method="POST" action="{{ route('delivery-orders.store') }}">
            @csrf
            <input type="hidden" name="sales_order_id" value="{{ $order->id }}">

            <x-card title="Informasi Pengiriman">
                <div class="row g-3">
                    <x-form.input name="date" label="Tanggal Kirim" type="date" :value="now()->toDateString()" required col="col-md-3" />
                    <x-form.input name="driver_name" label="Nama Pengemudi" col="col-md-3" />
                    <x-form.input name="vehicle_no" label="No. Kendaraan" col="col-md-3" />
                    <div class="col-md-3">
                        <label class="form-label">Gudang</label>
                        <input type="text" class="form-control" value="{{ $order->warehouse?->name }}" disabled>
                    </div>
                    <x-form.input name="customer_po_no" label="No. PO Pelanggan" col="col-md-4"
                                  :value="$order->customer_po_no"
                                  help="Terisi dari pesanan penjualan. Nomor ini tercetak di surat jalan supaya bagian penerimaan customer bisa mencocokkannya dengan PO mereka sendiri." />
                    <x-form.textarea name="shipping_address" label="Tujuan Pengiriman"
                                     :value="$order->customer?->address" rows="2"
                                     help="Terisi dari alamat customer. Ubah bila barang dikirim ke tempat lain — lokasi proyek, gudang cabang, atau alamat titip. Alamat inilah yang tercetak di surat jalan." />
                </div>
            </x-card>

            <x-card title="Item Pesanan {{ $order->so_no }}" flush class="mt-3">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="text-num">Dipesan</th>
                            <th class="text-num">Sudah Dikirim</th>
                            <th class="text-num">Sisa</th>
                            {{-- Stok gudang asal: yang menentukan surat jalan ini
                                 bisa diposting atau tidak. --}}
                            <th class="text-num">Stok {{ $order->warehouse?->name }}</th>
                            <th style="width:10rem" class="text-end">Kirim Sekarang</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($order->items as $index => $item)
                            @continue($item->outstandingQty() <= 0)
                            <tr>
                                <td>
                                    <input type="hidden" name="items[{{ $index }}][sales_order_item_id]" value="{{ $item->id }}">
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }}</div>
                                </td>
                                <td class="text-num">{{ fnum($item->quantity) }} {{ $item->product?->uom?->code }}</td>
                                <td class="text-num">{{ fnum($item->delivered_qty) }}</td>
                                <td class="text-num fw-bold text-orange">{{ fnum($item->outstandingQty()) }}</td>
                                @php
                                    $sisaStok = $stok[$order->warehouse_id][$item->product_id] ?? 0;
                                    $kurang = $sisaStok < $item->outstandingQty();
                                @endphp
                                <td class="text-num {{ $kurang ? 'text-danger fw-bold' : 'text-secondary' }}">
                                    {{ fnum($sisaStok) }}
                                    @if($kurang)
                                        <div class="small">kurang {{ fnum($item->outstandingQty() - $sisaStok) }}</div>
                                    @endif
                                </td>
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
                        <a href="{{ route('delivery-orders.create') }}" class="btn btn-link">Kembali</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan sebagai Draft
                        </button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>

    {{-- Jalur B: surat jalan lepas, produk dipilih bebas --}}
    @else
        <form method="POST" action="{{ route('delivery-orders.store') }}"
              x-data="deliveryItems({ products: {{ Js::from($products) }}, stok: {{ Js::from($stok) }} })"
              @submit="validate($event)">
            @csrf

            <div class="alert alert-info">
                <i class="ti ti-info-circle me-1"></i>
                Surat jalan ini tidak terhubung ke pesanan penjualan. Stok tetap berkurang saat
                diposting, dan HPP tercatat seperti pengiriman biasa.
            </div>

            <x-card title="Informasi Pengiriman">
                <div class="row g-3">
                    <x-form.select name="partner_id" label="Customer" :options="$customers" required col="col-md-4"
                                   placeholder="— Pilih customer —" />
                    {{-- x-model mengikat gudang ke Alpine supaya kolom Stok ikut
                         berubah begitu gudangnya diganti, tanpa memuat ulang halaman. --}}
                    <x-form.select name="warehouse_id" label="Gudang Asal" :options="$warehouses"
                                   :value="$defaultWarehouse" required col="col-md-4" :placeholder="false"
                                   x-model="warehouseId" />
                    <x-form.input name="date" label="Tanggal Kirim" type="date" :value="now()->toDateString()" required col="col-md-4" />
                    <x-form.input name="driver_name" label="Nama Pengemudi" col="col-md-4" />
                    <x-form.input name="vehicle_no" label="No. Kendaraan" col="col-md-4" />
                    <x-form.input name="customer_po_no" label="No. PO Pelanggan" col="col-md-4"
                                  help="Nomor PO dari customer, bila ada. Tercetak di surat jalan supaya bagian penerimaan mereka bisa mencocokkannya." />
                    <x-form.textarea name="shipping_address" label="Tujuan Pengiriman" rows="2"
                                     help="Terisi sendiri dari alamat customer begitu customernya dipilih. Ubah bila barang dikirim ke tempat lain — lokasi proyek, gudang cabang, atau alamat titip. Alamat inilah yang tercetak di surat jalan." />
                </div>
            </x-card>

            <x-card title="Barang yang Dikirim" flush class="mt-3">
                <x-slot:actions>
                    <button type="button" class="btn btn-sm btn-primary" @click="addRow()">
                        <i class="ti ti-plus me-1"></i> Tambah Baris
                    </button>
                </x-slot:actions>

                <div class="table-responsive">
                    <table class="table table-vcenter table-items card-table mb-0">
                        <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th style="min-width:18rem">Produk</th>
                            <th style="width:6rem">Satuan</th>
                            <th class="text-num" style="width:8rem">Stok</th>
                            <th class="col-qty text-end">Jumlah Kirim</th>
                            <th style="min-width:12rem">Keterangan</th>
                            <th class="col-action"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <template x-for="(row, index) in rows" :key="index">
                            <tr>
                                <td class="text-secondary" x-text="index + 1"></td>
                                <td>
                                    <select class="form-select" :name="`items[${index}][product_id]`"
                                            x-model="row.product_id" @change="onProductChange(index)" required>
                                        <option value="">— Pilih produk —</option>
                                        <template x-for="p in products" :key="p.id">
                                            <option :value="p.id" x-text="`${p.sku} — ${p.name}`"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="text-secondary" x-text="row.uom || '—'"></td>
                                {{-- Merah bila jumlah kirim melebihi stok: posting
                                     akan ditolak, dan lebih baik ketahuan sekarang. --}}
                                <td class="text-num"
                                    :class="kurangStok(row) ? 'text-danger fw-bold' : 'text-secondary'"
                                    x-text="stokBaris(row)"></td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" class="form-control text-end"
                                           :name="`items[${index}][quantity]`" x-model.number="row.quantity" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control" maxlength="255"
                                           :name="`items[${index}][notes]`" x-model="row.notes">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-icon btn-ghost-danger" @click="removeRow(index)"
                                            aria-label="Hapus baris">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <div class="card-body border-top">
                    <x-form.textarea name="notes" label="Catatan" rows="2" />
                </div>

                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('delivery-orders.create') }}" class="btn btn-link">Kembali</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan sebagai Draft
                        </button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>

        @push('scripts')
            <script>
                /**
                 * Tujuan pengiriman terisi sendiri dari alamat customer.
                 *
                 * Jalur dari pesanan penjualan sudah terisi dari sisi server,
                 * tetapi di jalur manual customernya baru dipilih di halaman ini
                 * — jadi alamatnya dibawa serta sebagai peta dan dipasang saat
                 * pilihan berubah.
                 *
                 * Yang sudah diketik orang tidak pernah ditimpa. Alamat kiriman
                 * sering memang bukan alamat customer (lokasi proyek, gudang
                 * cabang, alamat titip), dan menghapus ketikan orang karena ia
                 * mengganti customer adalah kehilangan yang tidak terlihat
                 * sampai surat jalannya tercetak salah.
                 *
                 * Peristiwa 'change' cukup: TomSelect melepasnya pada <select>
                 * aslinya, jadi pilihan lewat kotak cari pun ikut terbaca.
                 */
                (function () {
                    const alamat = @json($customerAddresses);
                    const pilihan = document.getElementById('partner_id');
                    const tujuan = document.getElementById('shipping_address');

                    if (! pilihan || ! tujuan) {
                        return;
                    }

                    pilihan.addEventListener('change', function () {
                        const diisiOrang = tujuan.value.trim() !== ''
                            && tujuan.dataset.dariCustomer !== '1';

                        if (diisiOrang) {
                            return;
                        }

                        tujuan.value = alamat[this.value] ?? '';
                        tujuan.dataset.dariCustomer = '1';
                    });

                    // Sekali orang menyuntingnya sendiri, isinya jadi miliknya.
                    tujuan.addEventListener('input', function () {
                        delete tujuan.dataset.dariCustomer;
                    });
                })();
            </script>
        @endpush
    @endif
@endsection
