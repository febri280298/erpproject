@php
    /** Shared allocation form: pick a partner, then split an amount across their open invoices. */
@endphp

@if(! $partnerId)
    <x-card :title="'Pilih '.$partnerLabel">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label required" for="partner_id">{{ $partnerLabel }}</label>
                <select name="partner_id" id="partner_id" class="form-select" required>
                    <option value="">— Pilih —</option>
                    @foreach($partners as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <small class="form-hint">Faktur yang masih terbuka akan ditampilkan setelah dipilih.</small>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Lanjut</button>
            </div>
        </form>
    </x-card>
@elseif($openInvoices->isEmpty())
    <x-card>
        <x-empty icon="ti ti-check" title="Tidak ada faktur terbuka"
                 message="Semua faktur untuk mitra ini sudah lunas." />
        <div class="text-center">
            <a href="{{ route($routeName.'.create') }}" class="btn btn-link">Pilih mitra lain</a>
        </div>
    </x-card>
@else
    <form method="POST" action="{{ route($routeName.'.store') }}"
          x-data="{
              rows: {{ Js::from($openInvoices->map(fn($i) => ['id' => $i->id, 'outstanding' => $i->outstandingAmount(), 'amount' => 0])->values()) }},
              get total() { return this.rows.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0); },
              payAll() { this.rows.forEach(r => r.amount = r.outstanding); },
              clearAll() { this.rows.forEach(r => r.amount = 0); },
              money(v) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2 }).format(v || 0); }
          }">
        @csrf
        <input type="hidden" name="partner_id" value="{{ $partnerId }}">

        <x-card title="Informasi Pembayaran">
            <div class="row g-3">
                <x-form.input name="date" label="Tanggal" type="date" :value="now()->toDateString()" required col="col-md-3" />
                <x-form.select name="account_id" label="Akun Kas / Bank" :options="$accounts" required col="col-md-4" />
                <x-form.select name="method" label="Metode" required col="col-md-2" :placeholder="false"
                               :options="['transfer' => 'Transfer', 'cash' => 'Tunai', 'cheque' => 'Cek', 'giro' => 'Giro']" />
                <x-form.input name="reference" label="No. Referensi" col="col-md-3"
                              placeholder="No. transfer / cek" />
            </div>
        </x-card>

        <x-card title="Alokasi ke Faktur" flush class="mt-3">
            <x-slot:actions>
                <button type="button" class="btn btn-sm" @click="payAll()">Bayar Semua</button>
                <button type="button" class="btn btn-sm btn-ghost-secondary" @click="clearAll()">Kosongkan</button>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. Faktur</th><th>Tanggal</th><th>Jatuh Tempo</th>
                        <th class="text-num">Total</th><th class="text-num">Sudah Dibayar</th>
                        <th class="text-num">Sisa</th><th style="width:12rem" class="text-end">Dibayar Sekarang</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($openInvoices as $index => $invoice)
                        <tr>
                            <td>
                                <input type="hidden" name="allocations[{{ $index }}][invoice_id]" value="{{ $invoice->id }}">
                                <span class="fw-bold">{{ $invoice->invoice_no }}</span>
                            </td>
                            <td>{{ fdate($invoice->date) }}</td>
                            <td class="{{ $invoice->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ fdate($invoice->due_date) }}</td>
                            <td class="text-num">{{ rupiah($invoice->total) }}</td>
                            <td class="text-num">{{ rupiah($invoice->paid_amount) }}</td>
                            <td class="text-num fw-bold">{{ rupiah($invoice->outstandingAmount()) }}</td>
                            <td>
                                <input type="number" step="0.01" min="0" max="{{ $invoice->outstandingAmount() }}"
                                       name="allocations[{{ $index }}][amount]"
                                       class="form-control text-end"
                                       x-model.number="rows[{{ $index }}].amount">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="6" class="text-end">Total Pembayaran</td>
                        <td class="text-num fs-3" x-text="'Rp ' + money(total)"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card-body border-top">
                <x-form.textarea name="notes" label="Catatan" rows="2" />
            </div>

            <x-slot:footer>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route($routeName.'.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan sebagai Draft
                    </button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
@endif
