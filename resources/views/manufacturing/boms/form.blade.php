@extends('layouts.app')

@section('title', $bom ? 'Ubah BOM' : 'Buat BOM')
@section('pretitle', 'Produksi · ' . $nextNumber)

@section('content')
    <form method="POST" action="{{ $action }}"
          x-data="bomEditor({ rows: {{ Js::from($rows) }}, products: {{ Js::from($products) }} })">
        @csrf
        @if($method !== 'POST') @method('PUT') @endif

        <x-card title="Informasi BOM">
            <div class="row g-3">
                <x-form.input name="name" label="Nama BOM" :value="$bom->name ?? null" required col="col-md-6"
                              placeholder="Rakitan Server 2U" />
                <x-form.select name="product_id" label="Produk Jadi" required col="col-md-6"
                               :value="$bom->product_id ?? null"
                               :options="collect($products)->mapWithKeys(fn($p) => [$p['id'] => $p['sku'].' — '.$p['name']])" />

                <x-form.input name="quantity" label="Output per Batch" type="number" step="0.0001"
                              :value="$bom->quantity ?? 1" required col="col-md-3" />
                <x-form.select name="uom_id" label="Satuan Output" :options="$uoms" :value="$bom->uom_id ?? null" col="col-md-3" />
                <x-form.checkbox name="is_active" label="BOM aktif" :value="$bom ? $bom->is_active : true" col="col-md-3" />
                <div class="col-md-3">
                    <label class="form-label">Nomor</label>
                    <input type="text" class="form-control" value="{{ $nextNumber }}" disabled>
                </div>
            </div>
        </x-card>

        <x-card title="Komponen / Bahan Baku" flush class="mt-3">
            <x-slot:actions>
                <button type="button" class="btn btn-sm btn-primary" @click="addRow()">
                    <i class="ti ti-plus me-1"></i> Tambah Komponen
                </button>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-vcenter table-items card-table mb-0">
                    <thead>
                    <tr>
                        <th style="width:2.5rem">#</th>
                        <th style="min-width:18rem">Komponen</th>
                        <th class="text-num" style="width:9rem">Qty</th>
                        <th style="width:5rem">Satuan</th>
                        <th class="text-num" style="width:8rem">Waste %</th>
                        <th class="text-num" style="width:9rem">Qty Efektif</th>
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
                                    <option value="">— Pilih komponen —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="`${p.sku} — ${p.name}`"></option>
                                    </template>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.0001" min="0" class="form-control text-end"
                                       :name="`items[${index}][quantity]`" x-model.number="row.quantity" required>
                            </td>
                            <td class="text-secondary" x-text="row.uom || '—'"></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" class="form-control text-end"
                                       :name="`items[${index}][waste_percent]`" x-model.number="row.waste_percent">
                            </td>
                            <td class="text-num fw-bold" x-text="num(effectiveQty(row))"></td>
                            <td>
                                <input type="text" class="form-control" :name="`items[${index}][description]`" x-model="row.description">
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
                        <td colspan="5" class="text-end">Estimasi Biaya Bahan per Batch</td>
                        <td colspan="3" class="text-num fs-4" x-text="'Rp ' + money(materialCost)"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-body border-top">
                <x-form.textarea name="notes" label="Catatan Proses" :value="$bom->notes ?? null" rows="2" />
            </div>

            <x-slot:footer>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('boms.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan BOM</button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bomEditor', (config) => ({
                products: config.products ?? [],
                rows: [],

                init() {
                    this.rows = (config.rows ?? []).map((r) => this.normalize(r));
                    if (this.rows.length === 0) this.addRow();
                },

                normalize(r = {}) {
                    return {
                        product_id: r.product_id ?? '',
                        quantity: parseFloat(r.quantity ?? 1) || 0,
                        waste_percent: parseFloat(r.waste_percent ?? 0) || 0,
                        description: r.description ?? '',
                        uom: r.uom ?? '',
                        purchase_price: parseFloat(r.purchase_price ?? 0) || 0,
                    };
                },

                addRow() { this.rows.push(this.normalize({})); },

                removeRow(index) {
                    this.rows.splice(index, 1);
                    if (this.rows.length === 0) this.addRow();
                },

                onProductChange(index) {
                    const row = this.rows[index];
                    const product = this.products.find((p) => String(p.id) === String(row.product_id));
                    if (!product) return;
                    row.uom = product.uom ?? '';
                    row.purchase_price = product.purchase_price ?? 0;
                },

                effectiveQty(row) {
                    return Math.round((row.quantity || 0) * (1 + (row.waste_percent || 0) / 100) * 10000) / 10000;
                },

                get materialCost() {
                    return this.rows.reduce((sum, r) => {
                        const product = this.products.find((p) => String(p.id) === String(r.product_id));
                        const price = product ? product.purchase_price : 0;
                        return sum + this.effectiveQty(r) * price;
                    }, 0);
                },

                num: (v) => window.erp.fmtNumber(v),
                money: (v) => window.erp.fmtMoney(v),
            }));
        });
    </script>
@endpush
