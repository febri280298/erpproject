@extends('layouts.app')

@section('title', 'Dashboard')
@section('pretitle', 'Ringkasan ' . now()->translatedFormat('F Y'))

@section('content')
    @if(count($missingAccounts) > 0 && $modules->enabled('accounting'))
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-title">Pemetaan akun belum lengkap</h4>
            <p class="mb-2">{{ count($missingAccounts) }} pemetaan akun belum diatur, sehingga posting jurnal akan gagal.</p>
            @can('setting.edit')
                <a href="{{ route('settings.edit') }}#accounting" class="btn btn-sm btn-warning">Atur sekarang</a>
            @endcan
        </div>
    @endif

    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Penjualan bulan ini" :value="rupiah($stats['sales_month'])" icon="ti ti-trending-up" color="green"
                    :href="route('reports.sales')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Pembelian bulan ini" :value="rupiah($stats['purchase_month'])" icon="ti ti-shopping-cart" color="azure"
                    :href="route('reports.purchasing')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Piutang belum tertagih" :value="rupiah($stats['receivable'])" icon="ti ti-cash" color="orange"
                    :href="route('reports.receivable-aging')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Utang belum dibayar" :value="rupiah($stats['payable'])" icon="ti ti-receipt-2" color="red"
                    :href="route('reports.payable-aging')" />
        </div>
    </div>

    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Nilai persediaan" :value="rupiah($stats['inventory_value'])" icon="ti ti-package" color="purple"
                    :href="route('stocks.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="SO berjalan" :value="$stats['open_sales_orders']" icon="ti ti-file-invoice" color="blue"
                    :href="route('sales-orders.index', ['status' => 'confirmed'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="PO berjalan" :value="$stats['open_purchase_orders']" icon="ti ti-truck-delivery" color="indigo"
                    :href="route('purchase-orders.index', ['status' => 'approved'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Produk aktif" :value="$stats['products']" icon="ti ti-box" color="cyan"
                    :href="route('products.index')" hint="{{ $stats['employees'] }} karyawan aktif" />
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-8">
            <x-card title="Tren Penjualan vs Pembelian" subtitle="12 bulan terakhir">
                <div id="trend-chart" style="min-height:280px"></div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Perlu Tindakan">
                <div class="list-group list-group-flush">
                    @php
                        // Only list queues belonging to modules this installation runs.
                        $approvals = collect([
                            ['purchase_requisition', 'Permintaan pembelian diajukan', $pendingApprovals['requisitions'], 'purchase-requisitions.index', ['status' => 'submitted'], 'ti ti-clipboard-list'],
                            ['purchasing', 'PO menunggu persetujuan', $pendingApprovals['purchase_orders'], 'purchase-orders.index', ['status' => 'draft'], 'ti ti-shopping-cart'],
                            ['sales', 'SO menunggu konfirmasi', $pendingApprovals['sales_orders'], 'sales-orders.index', ['status' => 'draft'], 'ti ti-file-invoice'],
                            ['purchasing', 'Penerimaan barang draft', $pendingApprovals['draft_receipts'], 'goods-receipts.index', ['status' => 'draft'], 'ti ti-package-import'],
                            ['sales', 'Surat jalan draft', $pendingApprovals['draft_deliveries'], 'delivery-orders.index', ['status' => 'draft'], 'ti ti-truck'],
                            ['hr', 'Pengajuan cuti', $pendingApprovals['leaves'], 'leaves.index', ['status' => 'pending'], 'ti ti-beach'],
                        ])->filter(fn ($row) => $modules->enabled($row[0]))
                          ->map(fn ($row) => [$row[1], $row[2], route($row[3], $row[4]), $row[5]]);
                    @endphp

                    @foreach($approvals as [$label, $count, $url, $icon])
                        <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
                            <span class="avatar avatar-sm bg-{{ $count > 0 ? 'orange' : 'secondary' }}-lt me-2"><i class="{{ $icon }}"></i></span>
                            <span class="flex-fill">{{ $label }}</span>
                            <span class="badge bg-{{ $count > 0 ? 'orange' : 'secondary' }}-lt">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </x-card>
        </div>

        <div class="col-lg-6">
            <x-card title="Produk Terlaris Bulan Ini" flush>
                @if($topProducts->isEmpty())
                    <div class="card-body"><x-empty icon="ti ti-chart-bar" title="Belum ada penjualan" message="Data akan muncul setelah ada faktur penjualan bulan ini." /></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-num">Qty</th>
                                <th class="text-num">Pendapatan</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($topProducts as $row)
                                <tr>
                                    <td>
                                        <div>{{ $row->name }}</div>
                                        <div class="text-secondary small">{{ $row->sku }}</div>
                                    </td>
                                    <td class="text-num">{{ fnum($row->qty) }}</td>
                                    <td class="text-num">{{ rupiah($row->revenue) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-6">
            <x-card title="Stok Menipis" flush>
                @if($lowStock->isEmpty())
                    <div class="card-body"><x-empty icon="ti ti-check" title="Semua stok aman" message="Tidak ada produk di bawah stok minimum." /></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Gudang</th>
                                <th class="text-num">Stok</th>
                                <th class="text-num">Min</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($lowStock as $stock)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.show', $stock->product_id) }}">{{ $stock->product?->name }}</a>
                                        <div class="text-secondary small">{{ $stock->product?->sku }}</div>
                                    </td>
                                    <td>{{ $stock->warehouse?->name }}</td>
                                    <td class="text-num text-danger fw-bold">{{ fnum($stock->quantity) }} {{ $stock->product?->uom?->code }}</td>
                                    <td class="text-num text-secondary">{{ fnum($stock->product?->min_stock) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-6">
            <x-card title="Pesanan Penjualan Terbaru" flush>
                @include('partials.doc-mini-table', [
                    'rows' => $recentSales,
                    'numberField' => 'so_no',
                    'partnerField' => 'customer',
                    'routeName' => 'sales-orders',
                    'emptyMessage' => 'Belum ada pesanan penjualan.',
                ])
            </x-card>
        </div>

        <div class="col-lg-6">
            <x-card title="Pesanan Pembelian Terbaru" flush>
                @include('partials.doc-mini-table', [
                    'rows' => $recentPurchases,
                    'numberField' => 'po_no',
                    'partnerField' => 'supplier',
                    'routeName' => 'purchase-orders',
                    'emptyMessage' => 'Belum ada pesanan pembelian.',
                ])
            </x-card>
        </div>

        @if($overdueReceivables->isNotEmpty())
            <div class="col-12">
                <x-card title="Piutang Jatuh Tempo" flush>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Faktur</th>
                                <th>Pelanggan</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-num">Terlambat</th>
                                <th class="text-num">Sisa Tagihan</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($overdueReceivables as $invoice)
                                <tr>
                                    <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
                                    <td>{{ $invoice->customer?->name }}</td>
                                    <td>{{ fdate($invoice->due_date) }}</td>
                                    <td class="text-num"><span class="badge bg-red-lt">{{ $invoice->daysOverdue() }} hari</span></td>
                                    <td class="text-num">{{ rupiah($invoice->outstandingAmount()) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('trend-chart');
            if (!el || typeof ApexCharts === 'undefined') return;

            new ApexCharts(el, {
                chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: 'Penjualan', data: @json($salesTrend['sales']) },
                    { name: 'Pembelian', data: @json($salesTrend['purchases']) }
                ],
                xaxis: { categories: @json($salesTrend['labels']) },
                yaxis: {
                    labels: {
                        formatter: function (v) {
                            return v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : new Intl.NumberFormat('id-ID').format(v);
                        }
                    }
                },
                tooltip: {
                    y: { formatter: function (v) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(v); } }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                colors: ['#2fb344', '#4299e1'],
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { strokeDashArray: 4 }
            }).render();
        });
    </script>
@endpush
