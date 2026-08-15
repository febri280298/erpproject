@props([
    'action',
    'method' => 'POST',
    'products' => [],
    'rows' => [],
    'priceField' => 'sale_price',
    'discountAmount' => 0,
    'shippingCost' => 0,
    'header' => null,
    'footer' => null,
    'submitLabel' => 'Simpan',
    'cancelUrl' => null,
    'itemsTitle' => 'Rincian Item',
    'showPrice' => true,
    'showDiscount' => true,
    'showTax' => true,
    'showShipping' => true,
])

{{--
    Shared editor for every line-item document (Penawaran, SO, PO, Faktur…).
    The Alpine component in resources/js/doc-items.js owns the arithmetic; the
    server recomputes the same figures in LineItemCalculator on save.
--}}
<form method="POST" action="{{ $action }}"
      x-data="docItems({
          products: {{ Js::from($products) }},
          rows: {{ Js::from($rows) }},
          priceField: '{{ $priceField }}',
          partnerLevels: {{ Js::from($partnerLevels ?? []) }},
          defaultLevelId: {{ Js::from($defaultPriceLevelId ?? null) }},
          dppRatio: {{ Js::from($dppRatio ?? 1) }},
          dppRatioLabel: {{ Js::from($dppRatioLabel ?? '') }},
          discountAmount: {{ (float) old('discount_amount', $discountAmount) }},
          shippingCost: {{ (float) old('shipping_cost', $shippingCost) }}
      })"
      @submit="validate($event)">
    @csrf
    @if(strtoupper($method) !== 'POST')
        @method($method)
    @endif

    @if($header)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Informasi Dokumen</h3></div>
            <div class="card-body">
                <div class="row g-3">{{ $header }}</div>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ $itemsTitle }}</h3>
            <div class="card-actions">
                <span class="text-secondary small me-2" x-show="priceSourceLabel" x-text="priceSourceLabel"></span>
                <button type="button" class="btn btn-sm" @click="repriceAll()" x-show="rows.some(r => r.product_id)"
                        title="Ambil ulang harga sesuai mitra yang dipilih">
                    <i class="ti ti-refresh me-1"></i> Perbarui Harga
                </button>
                <button type="button" class="btn btn-sm btn-primary" @click="addRow()">
                    <i class="ti ti-plus me-1"></i> Tambah Baris
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-items card-table mb-0">
                <thead>
                <tr>
                    <th style="width:2.5rem">#</th>
                    <th style="min-width:16rem">Produk</th>
                    <th style="min-width:12rem">Deskripsi</th>
                    <th class="col-qty text-end">Qty</th>
                    <th style="width:5rem">Satuan</th>
                    @if($showPrice)<th class="col-price text-end">Harga</th>@endif
                    @if($showDiscount)<th class="col-disc text-end">Disc %</th>@endif
                    @if($showTax)<th class="col-disc text-end">Pajak %</th>@endif
                    {{-- Menampilkan DPP (belum kena pajak) agar kolomnya berjumlah sama
                         dengan Subtotal di rekap; pajak ditambahkan sekali di bawah. --}}
                    @if($showPrice)<th class="col-total text-end">DPP</th>@endif
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
                        <td>
                            <input type="text" class="form-control" :name="`items[${index}][description]`"
                                   x-model="row.description" placeholder="Keterangan">
                        </td>
                        <td>
                            <input type="number" step="0.0001" min="0" class="form-control text-end"
                                   :name="`items[${index}][quantity]`" x-model.number="row.quantity" required>
                        </td>
                        <td class="text-secondary" x-text="row.uom || '—'"></td>
                        @if($showPrice)
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control text-end"
                                       :name="`items[${index}][unit_price]`" x-model.number="row.unit_price" required>
                            </td>
                        @else
                            <input type="hidden" :name="`items[${index}][unit_price]`" :value="row.unit_price">
                        @endif
                        @if($showDiscount)
                            <td>
                                <input type="number" step="0.01" min="0" max="100" class="form-control text-end"
                                       :name="`items[${index}][discount_percent]`" x-model.number="row.discount_percent">
                            </td>
                        @endif
                        @if($showTax)
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control text-end"
                                       :name="`items[${index}][tax_rate]`" x-model.number="row.tax_rate">
                            </td>
                        @endif
                        @if($showPrice)
                            <td class="text-num" x-text="money(lineSubtotal(row))"></td>
                        @endif
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
            <div class="row">
                <div class="col-md-6">
                    {{ $footer }}
                </div>
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-secondary">Total Kuantitas</td>
                            <td class="text-num" x-text="num(totalQuantity)"></td>
                        </tr>
                        @if($showPrice)
                        <tr>
                            <td class="text-secondary">Subtotal (DPP)</td>
                            <td class="text-num" x-text="money(subtotal)"></td>
                        </tr>
                        @if($showTax)
                            <tr x-show="usesDppOther" x-cloak>
                                <td class="text-secondary">
                                    DPP Nilai Lain <span class="text-muted" x-text="'(' + dppRatioLabel + ')'"></span>
                                </td>
                                <td class="text-num" x-text="money(dppOtherTotal)"></td>
                            </tr>
                        @endif
                        @if($showDiscount)
                            <tr>
                                <td class="text-secondary">Diskon Nota</td>
                                <td class="text-end" style="width:14rem">
                                    <input type="number" step="0.01" min="0" name="discount_amount"
                                           class="form-control form-control-sm text-end"
                                           x-model.number="discountAmount">
                                </td>
                            </tr>
                        @endif
                        @if($showShipping)
                            <tr>
                                <td class="text-secondary">Biaya Kirim</td>
                                <td class="text-end">
                                    <input type="number" step="0.01" min="0" name="shipping_cost"
                                           class="form-control form-control-sm text-end"
                                           x-model.number="shippingCost">
                                </td>
                            </tr>
                        @endif
                        @if($showTax)
                            <tr>
                                <td class="text-secondary">Total Pajak</td>
                                <td class="text-num" x-text="money(taxTotal)"></td>
                            </tr>
                        @endif
                        <tr class="fw-bold border-top">
                            <td>Total</td>
                            <td class="text-num fs-3" x-text="'Rp ' + money(grandTotal)"></td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end mb-4">
        @if($cancelUrl)
            <a href="{{ $cancelUrl }}" class="btn btn-link">Batal</a>
        @endif
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> {{ $submitLabel }}
        </button>
    </div>
</form>
