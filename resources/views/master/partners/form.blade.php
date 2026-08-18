@extends('layouts.app')

@section('title', $partner->exists ? 'Ubah Mitra' : 'Tambah Customer / Supplier')
@section('pretitle', 'Data Master')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <form method="POST" action="{{ $partner->exists ? route('partners.update', $partner) : route('partners.store') }}">
                @csrf
                @if($partner->exists) @method('PUT') @endif

                <x-card title="Identitas">
                    <div class="row g-3">
                        <x-form.input name="code" label="Kode" :value="$partner->code" required col="col-md-2"
                                      placeholder="CUS-0001" />
                        <x-form.input name="name" label="Nama Perusahaan / Perorangan" :value="$partner->name" required col="col-md-5"
                                      data-sumber-inisial />
                        <x-form.input name="initial" label="Inisial" :value="$partner->initial" col="col-md-2"
                                      placeholder="RIM" maxlength="10" style="text-transform:uppercase"
                                      help="Singkatan nama, terisi otomatis dan boleh diubah." />
                        <x-form.select name="type" label="Tipe" :value="$partner->type" required col="col-md-3" :placeholder="false"
                                       :options="['customer' => 'Pelanggan', 'supplier' => 'Pemasok', 'both' => 'Pelanggan & Pemasok']" />

                        <x-form.input name="contact_person" label="Nama Kontak" :value="$partner->contact_person" col="col-md-4" />
                        <x-form.input name="phone" label="Telepon" :value="$partner->phone" col="col-md-4" />
                        <x-form.input name="email" label="Email" type="email" :value="$partner->email" col="col-md-4" />

                        <x-form.textarea name="address" label="Alamat" :value="$partner->address" col="col-md-8" />
                        <x-form.input name="city" label="Kota" :value="$partner->city" col="col-md-4" />
                    </div>
                </x-card>

                <x-card title="Ketentuan Keuangan" class="mt-3">
                    <div class="row g-3">
                        <x-form.input name="npwp" label="NPWP" :value="$partner->npwp" col="col-md-4" />
                        <x-form.select name="payment_term_id" label="Termin Pembayaran" :options="$paymentTerms"
                                       :value="$partner->payment_term_id" col="col-md-4" />
                        <x-form.select name="price_level_id" label="Tingkat Harga" :options="$priceLevels"
                                       :value="$partner->price_level_id" col="col-md-4"
                                       placeholder="— Pakai tingkat default —"
                                       help="Harga jual terisi otomatis dari tingkat ini saat membuat SO / faktur." />
                        <x-form.input name="credit_limit" label="Batas Kredit" type="number" step="0.01"
                                      :value="$partner->credit_limit ?? 0" col="col-md-4" prefix="Rp" />
                        <x-form.input name="opening_balance" label="Saldo Awal" type="number" step="0.01"
                                      :value="$partner->opening_balance ?? 0" col="col-md-4" prefix="Rp"
                                      help="Saldo piutang/utang sebelum sistem digunakan." />
                        <x-form.checkbox name="is_active" label="Mitra aktif" :value="$partner->exists ? $partner->is_active : true" col="col-md-4" />
                        <x-form.textarea name="notes" label="Catatan" :value="$partner->notes" />
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('partners.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Simpan
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
@endsection
