@extends('layouts.app')

@section('title', $document ? 'Ubah Transfer Gudang' : 'Buat Transfer Gudang')
@section('pretitle', 'Persediaan · ' . $nextNumber)

@section('content')
    {{-- A transfer only moves quantities; price columns are switched off. --}}
    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                :show-price="false" :show-discount="false" :show-tax="false" :show-shipping="false"
                :cancel-url="route('stock-transfers.index')"
                submit-label="Simpan Transfer"
                items-title="Barang yang Dipindahkan">

        <x-slot:header>
            <x-form.input name="date" label="Tanggal" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.select name="from_warehouse_id" label="Dari Gudang" :options="$warehouses"
                           :value="$document->from_warehouse_id ?? null" required col="col-md-4" />
            <x-form.select name="to_warehouse_id" label="Ke Gudang" :options="$warehouses"
                           :value="$document->to_warehouse_id ?? null" required col="col-md-4" />
            <div class="col-md-1">
                <label class="form-label">Nomor</label>
                <input type="text" class="form-control" value="{{ $nextNumber }}" disabled>
            </div>
        </x-slot:header>

        <x-slot:footer>
            <x-form.textarea name="notes" label="Catatan" :value="$document->notes ?? null" rows="3" />
        </x-slot:footer>
    </x-doc-form>
@endsection
