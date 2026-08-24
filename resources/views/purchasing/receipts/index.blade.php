@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('pretitle', 'Pembelian')

@section('actions')
    @can('goods-receipt.create')
        <a href="{{ route('goods-receipts.create') }}" class="btn btn-primary">
            <i class="ti ti-package-import me-1"></i> Terima Barang
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'No. GRN / SJ pemasok…',
            'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'],
            'selects' => [
                ['name' => 'partner_id', 'label' => 'Pemasok', 'options' => $suppliers],
                ['name' => 'warehouse_id', 'label' => 'Gudang', 'options' => $warehouses],
            ],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-package-off" title="Belum ada penerimaan barang"
                         message="Penerimaan dibuat dari pesanan pembelian yang sudah disetujui." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        {{-- Kolom sempit untuk tombol buka rincian --}}
                        <th class="w-1"></th>
                        <th>No. GRN</th><th>Tanggal</th><th>No. PO</th><th>Pemasok</th>
                        <th>Gudang</th><th>SJ Pemasok</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    {{-- Satu <tbody> per dokumen: baris utama dan baris rinciannya
                         harus berbagi satu keadaan buka/tutup, dan dua <tr> bersaudara
                         tidak bisa berbagi satu x-data tanpa pembungkus. HTML memang
                         membolehkan lebih dari satu <tbody> dalam satu tabel. --}}
                    @foreach($documents as $document)
                    <tbody x-data="{ buka: false }">
                        <tr>
                            <td>
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon"
                                        @click="buka = ! buka"
                                        :aria-expanded="buka ? 'true' : 'false'"
                                        aria-label="Lihat barang {{ $document->grn_no }}">
                                    <i class="ti" :class="buka ? 'ti-chevron-down' : 'ti-chevron-right'"></i>
                                </button>
                            </td>
                            <td><a href="{{ route('goods-receipts.show', $document) }}" class="fw-bold">{{ $document->grn_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>
                                @if($document->purchaseOrder)
                                    <a href="{{ route('purchase-orders.show', $document->purchase_order_id) }}">{{ $document->purchaseOrder->po_no }}</a>
                                @else — @endif
                            </td>
                            <td>{{ $document->supplier?->name }}</td>
                            <td class="text-secondary">{{ $document->warehouse?->name }}</td>
                            <td class="text-secondary">{{ $document->supplier_do_no ?? '—' }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('goods-receipts.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('goods-receipts.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isDraft())
                                            @can('goods-receipt.delete')
                                                <x-delete-form :action="route('goods-receipts.destroy', $document)" label="Hapus" />
                                            @endcan
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr x-show="buka" x-cloak>
                            {{-- bg-surface-secondary, BUKAN bg-light-lt: utilitas
                                 *-lt memaksa color: var(--tblr-light) !important,
                                 sehingga teks tanpa kelas warna sendiri menjadi
                                 putih di atas latar terang dan hilang. --}}
                            <td colspan="9" class="bg-surface-secondary">
                                @if($document->items->isEmpty())
                                    <div class="text-secondary py-2">Dokumen ini tidak memuat barang.</div>
                                @else
                                    <table class="table table-sm table-vcenter mb-0">
                                        <thead>
                                        <tr>
                                            <th style="width:2.5rem">#</th>
                                            <th>Produk</th>
                                            <th class="text-num" style="width:10rem">Diterima</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($document->items as $i => $item)
                                            <tr>
                                                <td class="text-secondary">{{ $i + 1 }}</td>
                                                <td>
                                                    {{ $item->product?->name ?? '—' }}
                                                    <span class="text-secondary small font-monospace ms-1">{{ $item->product?->sku }}</span>
                                                </td>
                                                <td class="text-num fw-bold">
                                                    {{ fnum($item->quantity) }} {{ $item->product?->uom?->code }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                    @endforeach
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $documents->links() }}</div>
        @endif
    </x-card>
@endsection
