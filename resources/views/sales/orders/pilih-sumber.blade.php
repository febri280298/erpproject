@extends('layouts.app')

@section('title', 'Buat Pesanan Penjualan')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    {{-- Langkah pertama: pilih sumbernya — dari penawaran yang sudah deal, atau
         langsung tanpa penawaran. Pola yang sama dipakai Surat Jalan. --}}
    <div class="row row-cards">
        <div class="col-md-6">
            <x-card title="Dari Penawaran yang Sudah Deal"
                    subtitle="Produk, jumlah, harga, dan diskonnya ditarik otomatis dari penawaran.">
                @if($quotations->isEmpty())
                    <x-empty icon="ti ti-file-off" title="Belum ada penawaran yang siap dikonversi"
                             message="Hanya penawaran berstatus Diterima yang muncul di sini, dan yang belum pernah dijadikan pesanan.">
                        @can('quotation.view')
                            <x-slot:action>
                                <a href="{{ route('quotations.index') }}" class="btn">Lihat daftar penawaran</a>
                            </x-slot:action>
                        @endcan
                    </x-empty>
                @else
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label required" for="quotation_id">Penawaran</label>
                            <select name="quotation_id" id="quotation_id" class="form-select" required>
                                <option value="">— Pilih penawaran —</option>
                                @foreach($quotations as $quotation)
                                    <option value="{{ $quotation->id }}">
                                        {{ $quotation->quotation_no }} ·
                                        {{ $quotation->customer?->name ?? '—' }} ·
                                        {{ fdate($quotation->date) }} ·
                                        {{ rupiah($quotation->total) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-arrow-right me-1"></i> Lanjut
                            </button>
                        </div>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="col-md-6">
            <x-card title="Tanpa Penawaran"
                    subtitle="Pilih customer dan produknya sendiri.">
                <p class="text-secondary">
                    Dipakai untuk order yang langsung masuk tanpa melalui penawaran harga —
                    misalnya pelanggan tetap yang harganya sudah baku.
                </p>
                <a href="{{ route('sales-orders.create', ['mode' => 'manual']) }}" class="btn btn-outline-primary w-100">
                    <i class="ti ti-pencil-plus me-1"></i> Buat Pesanan Manual
                </a>
            </x-card>
        </div>
    </div>
@endsection
