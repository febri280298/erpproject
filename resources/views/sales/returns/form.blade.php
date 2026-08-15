@extends('layouts.app')

@section('title', 'Buat Retur Penjualan')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    @if(! $delivery)
        <x-card title="Pilih Surat Jalan yang Diretur"
                subtitle="Hanya surat jalan yang sudah diposting dan masih menyisakan barang yang bisa dikembalikan.">
            @if($openDeliveries->isEmpty())
                <x-empty icon="ti ti-truck-off" title="Tidak ada surat jalan yang bisa diretur"
                         message="Posting sebuah surat jalan terlebih dahulu." />
            @else
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label required" for="delivery_order_id">Surat Jalan</label>
                        <select name="delivery_order_id" id="delivery_order_id" class="form-select" required>
                            <option value="">— Pilih surat jalan —</option>
                            @foreach($openDeliveries as $option)
                                <option value="{{ $option->id }}">
                                    {{ $option->do_no }} · {{ $option->customer?->name }} · {{ fdate($option->date) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-arrow-right me-1"></i> Lanjut
                        </button>
                    </div>
                </form>
            @endif
        </x-card>
    @else
        <form method="POST" action="{{ route('sales-returns.store') }}" x-data="{ credit: {{ $invoices->isNotEmpty() ? 'true' : 'false' }} }">
            @csrf
            <input type="hidden" name="delivery_order_id" value="{{ $delivery->id }}">

            <x-card title="Informasi Retur">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" value="{{ $delivery->customer?->name }}" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gudang Penerima</label>
                        <input type="text" class="form-control" value="{{ $delivery->warehouse?->name }}" disabled>
                    </div>
                    <x-form.input name="date" label="Tanggal Retur" type="date" :value="now()->toDateString()" required col="col-md-3" />
                    <x-form.input name="reason" label="Alasan Retur" col="col-md-3"
                                  placeholder="Barang tidak sesuai pesanan" />
                </div>
            </x-card>

            <x-card title="Nota Kredit" class="mt-3"
                    subtitle="Mengurangi sisa tagihan customer pada faktur yang dipilih.">
                @if($invoices->isEmpty())
                    <div class="text-secondary">
                        <i class="ti ti-info-circle me-1"></i>
                        Pengiriman ini belum difakturkan, jadi belum ada tagihan yang perlu dikurangi.
                        Retur tetap mengembalikan stok dan membalik HPP.
                    </div>
                    <input type="hidden" name="issue_credit_note" value="0">
                @else
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="hidden" name="issue_credit_note" value="0">
                                <input type="checkbox" name="issue_credit_note" value="1"
                                       class="form-check-input" x-model="credit">
                                <span class="form-check-label">Terbitkan nota kredit</span>
                            </label>
                            <small class="form-hint">
                                Kosongkan bila customer memilih barang pengganti, bukan potongan tagihan.
                            </small>
                        </div>
                        <div class="col-md-6" x-show="credit" x-cloak>
                            <label class="form-label required" for="sales_invoice_id">Potongkan ke Faktur</label>
                            <select name="sales_invoice_id" id="sales_invoice_id" class="form-select" :required="credit">
                                @foreach($invoices as $invoice)
                                    <option value="{{ $invoice->id }}">
                                        {{ $invoice->invoice_no }} · sisa {{ rupiah($invoice->outstandingAmount()) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </x-card>

            <x-card title="Barang yang Dikembalikan" flush class="mt-3">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="min-width:14rem">Produk</th>
                            <th class="text-num">Dikirim</th>
                            <th class="text-num">Sudah Diretur</th>
                            <th class="text-num">Bisa Diretur</th>
                            <th style="width:9rem" class="text-end">Jumlah Retur</th>
                            <th style="width:9rem">Kondisi</th>
                            <th style="width:11rem" class="text-end">Harga Jual</th>
                            <th style="min-width:10rem">Keterangan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $i => $row)
                            <tr>
                                <td>
                                    <input type="hidden" name="items[{{ $i }}][delivery_order_item_id]" value="{{ $row['delivery_order_item_id'] }}">
                                    <input type="hidden" name="items[{{ $i }}][discount_percent]" value="{{ $row['discount_percent'] }}">
                                    <input type="hidden" name="items[{{ $i }}][tax_rate]" value="{{ $row['tax_rate'] }}">
                                    <div>{{ $row['product']?->name }}</div>
                                    <div class="text-secondary small">{{ $row['product']?->sku }}</div>
                                </td>
                                <td class="text-num">{{ fnum($row['delivered']) }} {{ $row['product']?->uom?->code }}</td>
                                <td class="text-num text-secondary">{{ fnum($row['returned']) }}</td>
                                <td class="text-num fw-bold text-orange">{{ fnum($row['returnable']) }}</td>
                                <td>
                                    {{-- Bawaan nol: retur biasanya hanya sebagian dari kiriman. --}}
                                    <input type="number" step="0.0001" min="0" max="{{ $row['returnable'] }}"
                                           name="items[{{ $i }}][quantity]" value="0"
                                           class="form-control text-end">
                                </td>
                                <td>
                                    <select name="items[{{ $i }}][condition]" class="form-select">
                                        <option value="good">Baik</option>
                                        <option value="damaged">Rusak</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" step="0.01" min="0" class="form-control text-end"
                                               name="items[{{ $i }}][unit_price]" value="{{ $row['unit_price'] }}">
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control" maxlength="255"
                                           name="items[{{ $i }}][notes]" placeholder="mis. pecah saat bongkar">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-body border-top">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <x-form.textarea name="notes" label="Catatan" rows="2" col="col-12" />
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info mb-0 py-2">
                                <strong>Baik</strong> — barang kembali ke stok.<br>
                                <strong>Rusak</strong> — tidak masuk stok, dicatat sebagai kerugian.
                            </div>
                        </div>
                    </div>
                </div>

                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('sales-returns.create') }}" class="btn btn-link">Kembali</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan sebagai Draft
                        </button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    @endif
@endsection
