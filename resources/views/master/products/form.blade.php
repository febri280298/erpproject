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
                <x-card title="Harga Dasar & Stok"
                        subtitle="Dipakai bila produk belum punya harga per tingkat / per supplier.">
                    <div class="row g-3">
                        <x-form.input name="purchase_price" label="Harga Beli Dasar" type="number" step="0.01"
                                      :value="$product->purchase_price ?? 0" required col="col-12" prefix="Rp"
                                      help="Terisi otomatis dari supplier utama bila diatur di bawah." />
                        <x-form.input name="sale_price" label="Harga Jual Dasar" type="number" step="0.01"
                                      :value="$product->sale_price ?? 0" required col="col-12" prefix="Rp"
                                      help="Terisi otomatis dari tingkat harga default." />
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

        {{-- Harga jual per tingkat --}}
        <x-card title="Harga Jual per Tingkat" class="mt-3"
                subtitle="Kosongkan harga bila produk ini tidak dijual pada tingkat tersebut." flush>
            <x-slot:actions>
                @can('price-level.view')
                    <a href="{{ route('price-levels.index') }}" class="btn btn-sm">
                        <i class="ti ti-settings me-1"></i> Atur Tingkat
                    </a>
                @endcan
            </x-slot:actions>

            @if($priceLevels->isEmpty())
                <div class="card-body">
                    <x-empty icon="ti ti-tag-off" title="Belum ada tingkat harga"
                             message="Buat tingkat harga seperti Eceran, Grosir, atau Proyek terlebih dahulu." />
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>Tingkat Harga</th>
                            <th style="width:16rem">Harga Jual</th>
                            <th style="width:12rem">Minimal Qty</th>
                            <th style="width:10rem" class="text-num">Margin</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($priceLevels as $i => $level)
                            @php $row = $salePrices[$level->id] ?? null; @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="prices[{{ $i }}][price_level_id]" value="{{ $level->id }}">
                                    <strong>{{ $level->name }}</strong>
                                    @if($level->is_default)<span class="badge bg-blue-lt ms-1">Default</span>@endif
                                    @if($level->description)
                                        <div class="text-secondary small">{{ $level->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" step="0.01" min="0" class="form-control text-end"
                                               name="prices[{{ $i }}][price]"
                                               value="{{ old("prices.$i.price", $row?->price) }}"
                                               placeholder="0">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0" class="form-control text-end"
                                           name="prices[{{ $i }}][min_qty]"
                                           value="{{ old("prices.$i.min_qty", $row?->min_qty ?? 0) }}">
                                </td>
                                <td class="text-num text-secondary">
                                    @if($row && (float) $row->price > 0 && (float) $product->purchase_price > 0)
                                        {{ fnum($row->marginPercent()) }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- Harga beli per supplier --}}
        <x-card title="Harga Beli per Supplier" class="mt-3"
                subtitle="Bandingkan penawaran tiap supplier. Centang satu sebagai supplier utama." flush>
            @if($suppliers->isEmpty())
                <div class="card-body">
                    <x-empty icon="ti ti-truck-off" title="Belum ada supplier"
                             message="Tambahkan mitra bertipe Pemasok terlebih dahulu." />
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>Supplier</th>
                            <th style="width:14rem">Harga Beli</th>
                            <th style="width:9rem">Kode Supplier</th>
                            <th style="width:8rem">Lead Time</th>
                            <th style="width:8rem">Min Order</th>
                            <th style="width:6rem" class="text-center">Utama</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($suppliers as $id => $name)
                            @php
                                $i = $loop->index;
                                $row = $supplierRows[$id] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="supplier_prices[{{ $i }}][partner_id]" value="{{ $id }}">
                                    {{ $name }}
                                    @if($row?->last_purchased_at)
                                        <div class="text-secondary small">
                                            Terakhir dibeli {{ fdate($row->last_purchased_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" step="0.01" min="0" class="form-control text-end"
                                               name="supplier_prices[{{ $i }}][price]"
                                               value="{{ old("supplier_prices.$i.price", $row?->price) }}"
                                               placeholder="0">
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="supplier_prices[{{ $i }}][supplier_sku]"
                                           value="{{ old("supplier_prices.$i.supplier_sku", $row?->supplier_sku) }}">
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control text-end"
                                               name="supplier_prices[{{ $i }}][lead_time_days]"
                                               value="{{ old("supplier_prices.$i.lead_time_days", $row?->lead_time_days ?? 0) }}">
                                        <span class="input-group-text">hari</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0" class="form-control text-end"
                                           name="supplier_prices[{{ $i }}][min_order_qty]"
                                           value="{{ old("supplier_prices.$i.min_order_qty", $row?->min_order_qty ?? 0) }}">
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="supplier_prices[{{ $i }}][is_preferred]" value="0">
                                    <input type="checkbox" class="form-check-input m-0"
                                           name="supplier_prices[{{ $i }}][is_preferred]" value="1"
                                           @checked(old("supplier_prices.$i.is_preferred", $row?->is_preferred))>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-secondary small">
                    Setiap perubahan harga otomatis tercatat di riwayat harga produk.
                </div>
            @endif
        </x-card>

        <div class="d-flex gap-2 justify-content-end mt-3 mb-4">
            <a href="{{ route('products.index') }}" class="btn btn-link">Batal</a>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Simpan Produk
            </button>
        </div>
    </form>
@endsection
