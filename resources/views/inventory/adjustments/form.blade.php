@extends('layouts.app')

@section('title', $document ? 'Ubah Penyesuaian Stok' : 'Buat Penyesuaian Stok')
@section('pretitle', 'Persediaan · ' . $nextNumber)

@section('content')
    {{--
        Stock opname sheet. The system quantity shown here is informational —
        the server re-reads it on save so a stale page cannot skew the difference.
    --}}
    <form method="POST" action="{{ $action }}"
          x-data="opname({
              rows: {{ Js::from($rows) }},
              warehouseId: {{ (int) ($warehouseId ?? 0) }},
              products: {{ Js::from($products) }},
              lookupUrl: '{{ route('stock-adjustments.lookup') }}',
              loadUrl: '{{ route('stock-adjustments.load') }}'
          })">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <x-card title="Informasi Penyesuaian">
            <div class="row g-3">
                <x-form.input name="date" label="Tanggal" type="date"
                              :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
                <div class="col-md-3">
                    <label class="form-label required" for="warehouse_id">Gudang</label>
                    <select name="warehouse_id" id="warehouse_id" class="form-select" required x-model.number="warehouseId">
                        @foreach($warehouses as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-form.input name="reason" label="Alasan" :value="$document->reason ?? null" col="col-md-6"
                              placeholder="Stok opname bulanan / barang rusak / koreksi" />
            </div>
        </x-card>

        <x-card title="Rincian Perhitungan" flush class="mt-3">
            <x-slot:actions>
                <button type="button" class="btn btn-sm" @click="loadCurrentStock()" :disabled="loading">
                    <span x-show="!loading"><i class="ti ti-download me-1"></i> Muat Stok Gudang</span>
                    <span x-show="loading">Memuat…</span>
                </button>
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
                        <th class="text-num" style="width:9rem">Stok Sistem</th>
                        <th class="text-num" style="width:9rem">Hasil Hitung</th>
                        <th class="text-num" style="width:9rem">Selisih</th>
                        <th class="text-num" style="width:10rem">HPP</th>
                        <th class="text-num" style="width:11rem">Nilai Selisih</th>
                        <th style="min-width:10rem">Catatan</th>
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
                            <td class="text-num text-secondary" x-text="num(row.system_qty)"></td>
                            <td>
                                <input type="number" step="0.0001" min="0" class="form-control text-end"
                                       :name="`items[${index}][actual_qty]`" x-model.number="row.actual_qty" required>
                            </td>
                            <td class="text-num fw-bold"
                                :class="difference(row) > 0 ? 'text-success' : (difference(row) < 0 ? 'text-danger' : 'text-secondary')"
                                x-text="num(difference(row))"></td>
                            <td class="text-num text-secondary" x-text="money(row.unit_cost)"></td>
                            <td class="text-num" x-text="money(difference(row) * row.unit_cost)"></td>
                            <td>
                                <input type="text" class="form-control" :name="`items[${index}][notes]`" x-model="row.notes">
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
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="6" class="text-end">Total Nilai Penyesuaian</td>
                        <td class="text-num fs-4" x-text="'Rp ' + money(totalValue)"></td>
                        <td colspan="2"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-body border-top">
                <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="2" />
            </div>

            <x-slot:footer>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan sebagai Draft
                    </button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('opname', (config) => ({
                products: config.products ?? [],
                rows: [],
                warehouseId: config.warehouseId,
                loading: false,

                init() {
                    this.rows = (config.rows ?? []).map((r) => this.normalize(r));
                    if (this.rows.length === 0) this.addRow();
                },

                normalize(r = {}) {
                    return {
                        product_id: r.product_id ?? '',
                        system_qty: parseFloat(r.system_qty ?? 0) || 0,
                        actual_qty: parseFloat(r.actual_qty ?? 0) || 0,
                        unit_cost: parseFloat(r.unit_cost ?? 0) || 0,
                        notes: r.notes ?? '',
                    };
                },

                addRow() { this.rows.push(this.normalize({})); },

                removeRow(index) {
                    this.rows.splice(index, 1);
                    if (this.rows.length === 0) this.addRow();
                },

                difference(row) {
                    return Math.round(((row.actual_qty || 0) - (row.system_qty || 0)) * 10000) / 10000;
                },

                get totalValue() {
                    return this.rows.reduce((sum, r) => sum + this.difference(r) * (r.unit_cost || 0), 0);
                },

                /* Ask the server what the current on-hand and cost are for this product. */
                async onProductChange(index) {
                    const row = this.rows[index];
                    if (!row.product_id || !this.warehouseId) return;

                    const url = `${config.lookupUrl}?product_id=${row.product_id}&warehouse_id=${this.warehouseId}`;
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;

                    const data = await response.json();
                    row.system_qty = data.system_qty;
                    row.actual_qty = data.system_qty;
                    row.unit_cost = data.unit_cost;
                },

                async loadCurrentStock() {
                    if (!this.warehouseId) return;
                    this.loading = true;

                    try {
                        const response = await fetch(`${config.loadUrl}?warehouse_id=${this.warehouseId}`, {
                            headers: { Accept: 'application/json' },
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.rows = data.length ? data.map((r) => this.normalize(r)) : [this.normalize({})];
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                num: (v) => window.erp.fmtNumber(v),
                money: (v) => window.erp.fmtMoney(v),
            }));
        });
    </script>
@endpush
