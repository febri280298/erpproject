@extends('layouts.app')

@section('title', $product->exists ? 'Ubah Produk' : 'Tambah Produk')
@section('pretitle', 'Data Master')

@section('content')
    <form method="POST" enctype="multipart/form-data"
          action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
        @csrf
        @if($product->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-card title="Informasi Produk">
                    <div class="row g-3">
                        <x-form.input name="sku" label="SKU" :value="$product->sku" required col="col-md-4"
                                      placeholder="SKU-0001" />
                        <x-form.input name="barcode" label="Barcode" :value="$product->barcode" col="col-md-4" />
                        <x-form.select name="type" label="Tipe" :value="$product->type" required col="col-md-4"
                                       :placeholder="false"
                                       :options="['stock' => 'Barang (dikelola stok)', 'service' => 'Jasa']" />

                        <x-form.input name="name" label="Nama Produk" :value="$product->name" required col="col-12" />

                        <x-form.select name="product_category_id" label="Kategori" :options="$categories"
                                       :value="$product->product_category_id" col="col-md-4" />
                        <x-form.select name="uom_id" label="Satuan" :options="$uoms" :value="$product->uom_id" col="col-md-4" />
                        <x-form.select name="tax_id" label="Pajak" :options="$taxes" :value="$product->tax_id" col="col-md-4"
                                       placeholder="— Tanpa pajak —" />

                        <x-form.textarea name="description" label="Deskripsi" :value="$product->description" />
                    </div>
                </x-card>
            </div>

            <div class="col-lg-4">
                <x-card title="Harga & Stok">
                    <div class="row g-3">
                        <x-form.input name="purchase_price" label="Harga Beli" type="number" step="0.01"
                                      :value="$product->purchase_price ?? 0" required col="col-12" prefix="Rp" />
                        <x-form.input name="sale_price" label="Harga Jual" type="number" step="0.01"
                                      :value="$product->sale_price ?? 0" required col="col-12" prefix="Rp" />
                        <x-form.input name="min_stock" label="Stok Minimum" type="number" step="0.0001"
                                      :value="$product->min_stock ?? 0" col="col-6"
                                      help="Peringatan muncul di dashboard." />
                        <x-form.input name="max_stock" label="Stok Maksimum" type="number" step="0.0001"
                                      :value="$product->max_stock ?? 0" col="col-6" />
                        <x-form.checkbox name="is_active" label="Produk aktif" :value="$product->exists ? $product->is_active : true" col="col-12" />
                    </div>
                </x-card>

                <x-card title="Gambar Produk" class="mt-3">
                    @if($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="img-fluid rounded mb-2">
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/*">
                    @error('image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <small class="form-hint">Maksimal 2 MB.</small>
                </x-card>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3 mb-4">
            <a href="{{ route('products.index') }}" class="btn btn-link">Batal</a>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Simpan Produk
            </button>
        </div>
    </form>
@endsection
