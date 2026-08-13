@extends('layouts.app')

@section('title', $account->exists ? 'Ubah Akun' : 'Tambah Akun')
@section('pretitle', 'Akuntansi')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}">
                @csrf
                @if($account->exists) @method('PUT') @endif

                <x-card title="Detail Akun">
                    <div class="row g-3">
                        <x-form.input name="code" label="Kode Akun" :value="$account->code" required col="col-md-4"
                                      placeholder="1110" help="Gunakan penomoran berjenjang, mis. 1110." />
                        <x-form.input name="name" label="Nama Akun" :value="$account->name" required col="col-md-8" />

                        <x-form.select name="type" label="Tipe Akun" :value="$account->type" required col="col-md-4" :placeholder="false"
                                       :options="['asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Ekuitas', 'revenue' => 'Pendapatan', 'expense' => 'Beban']" />
                        <x-form.select name="normal_balance" label="Saldo Normal" :value="$account->normal_balance" required col="col-md-4" :placeholder="false"
                                       :options="['debit' => 'Debit', 'credit' => 'Kredit']" />
                        <x-form.select name="subtype" label="Sub-tipe" :value="$account->subtype" col="col-md-4"
                                       :options="['cash' => 'Kas', 'bank' => 'Bank', 'receivable' => 'Piutang', 'payable' => 'Utang', 'inventory' => 'Persediaan', 'tax' => 'Pajak', 'fixed' => 'Aset Tetap', 'cogs' => 'Harga Pokok', 'operating' => 'Operasional', 'other' => 'Lainnya']"
                                       help="Akun kas & bank dipakai pada form pembayaran." />

                        <x-form.select name="parent_id" label="Akun Induk" :options="$parents" :value="$account->parent_id"
                                       col="col-md-6" placeholder="— Tanpa induk —" />
                        <x-form.input name="opening_balance" label="Saldo Awal" type="number" step="0.01"
                                      :value="$account->opening_balance ?? 0" col="col-md-6" prefix="Rp" />

                        <x-form.checkbox name="is_postable" label="Dapat diposting (akun transaksi)"
                                         :value="$account->exists ? $account->is_postable : true" col="col-md-6"
                                         help="Akun header/grup sebaiknya tidak dapat diposting." />
                        <x-form.checkbox name="is_active" label="Akun aktif" :value="$account->exists ? $account->is_active : true" col="col-md-6" />

                        <x-form.textarea name="description" label="Keterangan" :value="$account->description" />
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('accounts.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
@endsection
