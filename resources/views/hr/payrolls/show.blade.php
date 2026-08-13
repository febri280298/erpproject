@extends('layouts.app')

@section('title', $payroll->payroll_no)
@section('pretitle', 'Penggajian · ' . $payroll->periodLabel())

@section('actions')
    @if($payroll->isDraft())
        @can('payroll.edit')
            <x-action-form :action="route('payrolls.regenerate', $payroll)" label="Muat Ulang Karyawan" icon="ti ti-refresh"
                           class="btn" confirm="Muat ulang daftar karyawan? Perubahan manual akan hilang." />
        @endcan
        @can('payroll.approve')
            <x-action-form :action="route('payrolls.approve', $payroll)" label="Setujui" icon="ti ti-check"
                           class="btn btn-primary" confirm="Setujui payroll ini?" />
        @endcan
    @elseif($payroll->status === 'approved')
        @can('payroll.approve')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pay-modal">
                <i class="ti ti-cash me-1"></i> Bayar & Jurnal
            </button>
        @endcan
    @endif

    @if($payroll->status !== 'cancelled')
        @can('payroll.approve')
            <x-action-form :action="route('payrolls.cancel', $payroll)" label="Batalkan" icon="ti ti-x"
                           class="btn btn-outline-danger"
                           confirm="Batalkan payroll ini? Jika sudah dibayar, jurnal akan dibalik." />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-md-4">
            <x-stat label="Total Bruto" :value="rupiah($payroll->total_gross)" icon="ti ti-coin" color="blue" />
        </div>
        <div class="col-md-4">
            <x-stat label="Total Potongan" :value="rupiah($payroll->total_deduction)" icon="ti ti-minus" color="red" />
        </div>
        <div class="col-md-4">
            <x-stat label="Total Netto Dibayar" :value="rupiah($payroll->total_net)" icon="ti ti-wallet" color="green" />
        </div>
    </div>

    <form method="POST" action="{{ route('payrolls.items', $payroll) }}">
        @csrf @method('PUT')

        <x-card title="Rincian Gaji Karyawan" flush>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th class="text-num">Hadir</th>
                        <th class="text-num">Gaji Pokok</th>
                        <th class="text-num">Tunjangan</th>
                        <th class="text-num" style="width:9rem">Lembur</th>
                        <th class="text-num" style="width:9rem">Bonus</th>
                        <th class="text-num" style="width:9rem">BPJS</th>
                        <th class="text-num" style="width:9rem">PPh 21</th>
                        <th class="text-num" style="width:9rem">Potongan Lain</th>
                        <th class="text-num">Netto</th>
                        <th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($payroll->items as $item)
                        <tr>
                            <td>
                                <div>{{ $item->employee?->name }}</div>
                                <div class="text-secondary small">{{ $item->employee?->nik }} · {{ $item->employee?->department?->name }}</div>
                            </td>
                            <td class="text-num">{{ $item->present_days }}</td>
                            <td class="text-num">{{ rupiah($item->basic_salary) }}</td>
                            <td class="text-num">{{ rupiah($item->allowance) }}</td>
                            @foreach(['overtime', 'bonus', 'bpjs', 'tax_pph21', 'other_deduction'] as $field)
                                <td>
                                    @if($payroll->isDraft())
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                               name="items[{{ $item->id }}][{{ $field }}]" value="{{ (float) $item->{$field} }}">
                                    @else
                                        <div class="text-num">{{ rupiah($item->{$field}) }}</div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-num fw-bold">{{ rupiah($item->net_salary) }}</td>
                            <td class="text-end">
                                <a href="{{ route('payrolls.slip', [$payroll, $item->id]) }}" class="btn btn-sm btn-ghost-secondary"
                                   target="_blank" title="Slip gaji">
                                    <i class="ti ti-file-text"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="9" class="text-end">Total Netto</td>
                        <td class="text-num fs-4">{{ rupiah($payroll->total_net) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            @if($payroll->isDraft())
                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">Ubah komponen lembur, bonus, dan potongan lalu simpan.</span>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </x-slot:footer>
            @endif
        </x-card>
    </form>

    @include('partials.doc-journals', ['journals' => $payroll->journals])
@endsection

@push('modals')
    @if($payroll->status === 'approved')
        <div class="modal fade" id="pay-modal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('payrolls.pay', $payroll) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Bayar Payroll {{ $payroll->payroll_no }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required" for="account_id">Akun Kas / Bank</label>
                            <select name="account_id" id="account_id" class="form-select" required>
                                @foreach($cashAccounts as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="payment_date">Tanggal Pembayaran</label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control"
                                   value="{{ optional($payroll->payment_date)->toDateString() ?? now()->toDateString() }}" required>
                        </div>
                        <p class="text-secondary small mb-0">
                            Jurnal: Beban Gaji {{ rupiah($payroll->total_gross) }} (D) —
                            Utang BPJS/PPh dan Kas/Bank {{ rupiah($payroll->total_net) }} (K).
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Bayar & Posting Jurnal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endpush
