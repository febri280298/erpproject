@extends('layouts.app')

@section('title', 'Jurnal Manual')
@section('pretitle', 'Akuntansi')

@section('content')
    <form method="POST" action="{{ route('journals.store') }}"
          x-data="journalEditor({ accounts: {{ Js::from($accounts) }} })"
          @submit="validate($event)">
        @csrf

        <x-card title="Informasi Jurnal">
            <div class="row g-3">
                <x-form.input name="date" label="Tanggal" type="date" :value="old('date', now()->toDateString())" required col="col-md-3" />
                <x-form.input name="reference" label="Referensi" :value="old('reference')" col="col-md-3" />
                <x-form.input name="description" label="Keterangan" :value="old('description')" required col="col-md-6" />
            </div>
        </x-card>

        <x-card title="Baris Jurnal" flush class="mt-3">
            <x-slot:actions>
                <button type="button" class="btn btn-sm btn-primary" @click="addRow()">
                    <i class="ti ti-plus me-1"></i> Tambah Baris
                </button>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-vcenter table-items card-table mb-0">
                    <thead>
                    <tr>
                        <th style="width:2.5rem">#</th>
                        <th style="min-width:20rem">Akun</th>
                        <th style="min-width:14rem">Keterangan</th>
                        <th class="text-num" style="width:12rem">Debit</th>
                        <th class="text-num" style="width:12rem">Kredit</th>
                        <th class="col-action"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(row, index) in rows" :key="index">
                        <tr>
                            <td class="text-secondary" x-text="index + 1"></td>
                            <td>
                                <select class="form-select" :name="`lines[${index}][account_id]`" x-model="row.account_id" required>
                                    <option value="">— Pilih akun —</option>
                                    <template x-for="a in accounts" :key="a.id">
                                        <option :value="a.id" x-text="a.label"></option>
                                    </template>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control" :name="`lines[${index}][description]`" x-model="row.description">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control text-end"
                                       :name="`lines[${index}][debit]`" x-model.number="row.debit"
                                       @input="if (row.debit > 0) row.credit = 0">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control text-end"
                                       :name="`lines[${index}][credit]`" x-model.number="row.credit"
                                       @input="if (row.credit > 0) row.debit = 0">
                            </td>
                            <td>
                                <button type="button" class="btn btn-icon btn-ghost-danger" @click="removeRow(index)"
                                        aria-label="Hapus baris">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">Total</td>
                        <td class="text-num" x-text="money(totalDebit)"></td>
                        <td class="text-num" x-text="money(totalCredit)"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end">
                            <span x-show="balanced" class="badge bg-green-lt">Seimbang</span>
                            <span x-show="!balanced" class="badge bg-red-lt">
                                Selisih: <span x-text="money(Math.abs(totalDebit - totalCredit))"></span>
                            </span>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <x-slot:footer>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('journals.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary" :disabled="!balanced">
                        <i class="ti ti-device-floppy me-1"></i> Posting Jurnal
                    </button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('journalEditor', (config) => ({
                accounts: config.accounts ?? [],
                rows: [
                    { account_id: '', description: '', debit: 0, credit: 0 },
                    { account_id: '', description: '', debit: 0, credit: 0 },
                ],

                addRow() { this.rows.push({ account_id: '', description: '', debit: 0, credit: 0 }); },

                removeRow(index) {
                    if (this.rows.length <= 2) return;
                    this.rows.splice(index, 1);
                },

                get totalDebit() { return this.rows.reduce((s, r) => s + (parseFloat(r.debit) || 0), 0); },
                get totalCredit() { return this.rows.reduce((s, r) => s + (parseFloat(r.credit) || 0), 0); },

                /* Guard on the client too — the server refuses unbalanced entries anyway. */
                get balanced() {
                    return this.totalDebit > 0 && Math.abs(this.totalDebit - this.totalCredit) < 0.01;
                },

                validate(event) {
                    if (!this.balanced) {
                        event.preventDefault();
                        window.alert('Jurnal harus seimbang antara total debit dan kredit.');
                    }
                },

                money: (v) => window.erp.fmtMoney(v),
            }));
        });
    </script>
@endpush
