@extends('layouts.app')

@section('title', $document ? 'Ubah Permintaan Pembelian' : 'Buat Permintaan Pembelian')
@section('pretitle', 'Pembelian · ' . $nextNumber)

@section('content')
    {{--
        A requisition only needs product + quantity + indicative price, so the
        shared editor runs with tax and discount columns hidden.
    --}}
    <x-doc-form :action="$action" :method="$method" :products="$products" :rows="$rows"
                price-field="purchase_price"
                :show-discount="false" :show-tax="false" :show-shipping="false"
                :cancel-url="route('purchase-requisitions.index')"
                submit-label="Simpan Permintaan"
                items-title="Barang yang Diminta">

        <x-slot:header>
            <x-form.input name="date" label="Tanggal" type="date"
                          :value="optional($document?->date)->toDateString() ?? now()->toDateString()" required col="col-md-3" />
            <x-form.input name="required_date" label="Dibutuhkan Tanggal" type="date"
                          :value="optional($document?->required_date)->toDateString()" col="col-md-3" />
            <x-form.select name="department_id" label="Departemen" :options="$departments"
                           :value="$document->department_id ?? null" col="col-md-3" />
            <div class="col-md-3">
                <label class="form-label">Nomor Dokumen</label>
                <input type="text" class="form-control" value="{{ $nextNumber }}" disabled>
            </div>
        </x-slot:header>

        <x-slot:footer>
            <x-form.textarea name="notes" label="Catatan / Alasan Permintaan" :value="$document->notes ?? null" rows="3" />
        </x-slot:footer>
    </x-doc-form>
@endsection
