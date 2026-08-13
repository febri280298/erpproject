@extends('layouts.app')

@section('title', 'Buat Perintah Produksi')
@section('pretitle', 'Produksi · ' . $nextNumber)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @if($boms->isEmpty())
                <x-card>
                    <x-empty icon="ti ti-tools-off" title="Belum ada BOM aktif"
                             message="Buat Bill of Materials terlebih dahulu sebelum membuat perintah produksi." />
                    <div class="text-center"><a href="{{ route('boms.create') }}" class="btn btn-primary">Buat BOM</a></div>
                </x-card>
            @else
                <form method="POST" action="{{ route('production-orders.store') }}"
                      x-data="{ bomId: '{{ request('bom_id') }}', detail: null,
                                async load() {
                                    if (!this.bomId) { this.detail = null; return; }
                                    const r = await fetch(`/api/boms/${this.bomId}/detail`, { headers: { Accept: 'application/json' } });
                                    this.detail = r.ok ? await r.json() : null;
                                } }"
                      x-init="load()">
                    @csrf

                    <x-card title="Informasi Produksi">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="bom_id">Bill of Materials</label>
                                <select name="bom_id" id="bom_id" class="form-select" required x-model="bomId" @change="load()">
                                    <option value="">— Pilih BOM —</option>
                                    @foreach($boms as $bom)
                                        <option value="{{ $bom->id }}">{{ $bom->bom_no }} — {{ $bom->name }} ({{ $bom->product?->name }})</option>
                                    @endforeach
                                </select>
                                @error('bom_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <x-form.input name="quantity" label="Jumlah Produksi" type="number" step="0.0001"
                                          value="1" required col="col-md-3" />
                            <x-form.select name="warehouse_id" label="Gudang" :options="$warehouses"
                                           :value="$defaultWarehouse" required col="col-md-3" />

                            <x-form.input name="date" label="Tanggal" type="date" :value="now()->toDateString()" required col="col-md-3" />
                            <x-form.input name="due_date" label="Target Selesai" type="date" col="col-md-3" />
                            <x-form.textarea name="notes" label="Catatan" rows="2" col="col-md-6" />
                        </div>

                        <template x-if="detail">
                            <div class="mt-3">
                                <h4>Komponen per Batch (<span x-text="detail.quantity"></span> unit)</h4>
                                <ul class="list-group list-group-flush">
                                    <template x-for="item in detail.items" :key="item.product">
                                        <li class="list-group-item d-flex justify-content-between px-0">
                                            <span x-text="item.product"></span>
                                            <span class="text-num" x-text="`${item.quantity} ${item.uom}`"></span>
                                        </li>
                                    </template>
                                </ul>
                                <small class="form-hint">
                                    Kebutuhan bahan akan dihitung otomatis sesuai jumlah produksi yang dimasukkan.
                                </small>
                            </div>
                        </template>

                        <x-slot:footer>
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="{{ route('production-orders.index') }}" class="btn btn-link">Batal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-device-floppy me-1"></i> Simpan
                                </button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </form>
            @endif
        </div>
    </div>
@endsection
