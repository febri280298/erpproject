@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('pretitle', 'Pengaturan')

@section('content')
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="list-group list-group-transparent sticky-top" style="top:5rem">
                <a href="#modules" class="list-group-item list-group-item-action"><i class="ti ti-toggle-right me-2"></i> Modul</a>
                <a href="#company" class="list-group-item list-group-item-action"><i class="ti ti-building me-2"></i> Profil Perusahaan</a>
                <a href="#tax" class="list-group-item list-group-item-action"><i class="ti ti-receipt-tax me-2"></i> PPN & DPP Nilai Lain</a>
                <a href="#accounting" class="list-group-item list-group-item-action"><i class="ti ti-calculator me-2"></i> Pemetaan Akun</a>
                <a href="#operations" class="list-group-item list-group-item-action"><i class="ti ti-settings me-2"></i> Operasional</a>
                <a href="#sequences" class="list-group-item list-group-item-action"><i class="ti ti-hash me-2"></i> Format Nomor</a>
            </div>
        </div>

        <div class="col-lg-9">
            {{-- Module switches: hide menus + block routes, never delete data --}}
            <form method="POST" action="{{ route('settings.modules') }}" id="modules">
                @csrf @method('PUT')
                <x-card title="Modul Aktif"
                        subtitle="Matikan modul yang tidak dipakai agar menu lebih ringkas. Data tidak dihapus.">
                    <div class="row g-3">
                        @foreach($moduleCatalogue as $key => $module)
                            @php
                                $isCore = (bool) ($module['core'] ?? false);
                                $parent = $module['requires'] ?? null;
                                $parentOff = $parent && $moduleRegistry->disabled($parent);
                            @endphp
                            <div class="col-md-6">
                                <div class="card card-sm h-100 {{ $moduleRegistry->disabled($key) ? 'opacity-75' : '' }}">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-2">
                                            <span class="avatar avatar-sm bg-{{ $moduleRegistry->enabled($key) ? 'primary' : 'secondary' }}-lt">
                                                <i class="{{ $module['icon'] ?? 'ti ti-box' }}"></i>
                                            </span>
                                            <div class="flex-fill">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong>{{ $module['label'] }}</strong>

                                                    @if($isCore)
                                                        <span class="badge bg-secondary-lt">Inti</span>
                                                    @else
                                                        <label class="form-check form-switch m-0">
                                                            <input type="hidden" name="modules[{{ $key }}]" value="0">
                                                            <input type="checkbox" name="modules[{{ $key }}]" value="1"
                                                                   class="form-check-input"
                                                                   @checked($moduleRegistry->enabled($key))
                                                                   @disabled($parentOff)>
                                                        </label>
                                                    @endif
                                                </div>
                                                <div class="text-secondary small mt-1">{{ $module['description'] }}</div>
                                                @if($parent)
                                                    <div class="small mt-1 {{ $parentOff ? 'text-danger' : 'text-secondary' }}">
                                                        <i class="ti ti-arrow-narrow-right"></i>
                                                        Bagian dari {{ $moduleRegistry->label($parent) }}{{ $parentOff ? ' (sedang nonaktif)' : '' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <h4 class="alert-title">Yang terjadi saat modul dimatikan</h4>
                        <ul class="mb-0 mt-2">
                            <li>Menunya hilang dari sidebar dan alamatnya membalas 404.</li>
                            <li>Data lama tetap tersimpan — menyalakan kembali memulihkan tampilannya utuh.</li>
                            <li>Khusus Akuntansi: jurnal tetap dicatat di belakang layar, sehingga pembukuan
                                tidak bolong bila modulnya dinyalakan lagi nanti.</li>
                        </ul>
                    </div>

                    <x-slot:footer>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Simpan Modul</button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            {{-- Company profile --}}
            <form method="POST" action="{{ route('settings.company') }}" enctype="multipart/form-data" id="company" class="mt-3">
                @csrf @method('PUT')
                <x-card title="Profil Perusahaan" subtitle="Tampil pada header dokumen cetak">
                    <div class="row g-3">
                        <x-form.input name="company_name" label="Nama Perusahaan" :value="$settings['company_name'] ?? ''" required col="col-md-8" />
                        <x-form.input name="company_npwp" label="NPWP" :value="$settings['company_npwp'] ?? ''" col="col-md-4" />
                        <x-form.input name="company_phone" label="Telepon" :value="$settings['company_phone'] ?? ''" col="col-md-6" />
                        <x-form.input name="company_email" label="Email" type="email" :value="$settings['company_email'] ?? ''" col="col-md-6" />
                        <x-form.textarea name="company_address" label="Alamat" :value="$settings['company_address'] ?? ''" rows="3" />

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12">
                            <div class="text-secondary small">
                                Rekening di bawah dicetak pada faktur penjualan sebagai tujuan transfer.
                                Dikosongkan berarti blok itu tidak ikut tercetak.
                            </div>
                        </div>
                        <x-form.input name="company_bank_name" label="Bank" :value="$settings['company_bank_name'] ?? ''"
                                      col="col-md-3" placeholder="BCA" />
                        <x-form.input name="company_bank_account" label="Nomor Rekening" :value="$settings['company_bank_account'] ?? ''"
                                      col="col-md-4" placeholder="5745884943" />
                        <x-form.input name="company_bank_holder" label="Atas Nama" :value="$settings['company_bank_holder'] ?? ''"
                                      col="col-md-5" placeholder="PT Pilar Utama Material" />
                        <div class="col-md-6">
                            <label class="form-label" for="company_logo">Logo</label>
                            <input type="file" name="company_logo" id="company_logo" class="form-control" accept="image/*">
                            @if(! empty($settings['company_logo']))
                                <img src="{{ asset('storage/'.$settings['company_logo']) }}" alt="Logo" class="mt-2" style="max-height:3rem">
                            @endif
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Simpan Profil</button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            {{-- Account mapping: drives every automatic journal --}}
            <form method="POST" action="{{ route('settings.accounting') }}" id="accounting" class="mt-3">
                @csrf @method('PUT')
                <x-card title="Pemetaan Akun" subtitle="Menentukan akun yang dipakai saat dokumen diposting">
                    <div class="row g-3">
                        @foreach($accountMappings as $key => [$label, $fallback])
                            <x-form.select :name="$key" :label="$label" :options="$accounts"
                                           :value="$settings[$key] ?? $fallback" col="col-md-6"
                                           placeholder="— Belum diatur —" />
                        @endforeach

                        <x-form.input name="default_tax_rate" label="Tarif Pajak Default" type="number" step="0.01"
                                      :value="$settings['default_tax_rate'] ?? 11" col="col-md-3" suffix="%" />
                        <x-form.input name="fiscal_year_start" label="Awal Tahun Fiskal"
                                      :value="$settings['fiscal_year_start'] ?? '01-01'" col="col-md-3"
                                      placeholder="01-01" help="Format DD-MM." />
                    </div>

                    <x-slot:footer>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Simpan Pemetaan</button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            {{-- Operational rules --}}
            {{-- PPN: DPP Nilai Lain sesuai PMK 131/2024 --}}
            <form method="POST" action="{{ route('settings.tax') }}" id="tax" class="mt-3"
                  x-data="{ aktif: {{ ($settings['use_dpp_nilai_lain'] ?? false) ? 'true' : 'false' }} }">
                @csrf @method('PUT')
                <x-card title="PPN & DPP Nilai Lain"
                        subtitle="Sesuai PMK 131/2024: tarif 12% dikenakan atas 11/12 harga jual.">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-check form-switch">
                                <input type="hidden" name="use_dpp_nilai_lain" value="0">
                                <input type="checkbox" name="use_dpp_nilai_lain" value="1"
                                       class="form-check-input" x-model="aktif">
                                <span class="form-check-label">Gunakan DPP Nilai Lain</span>
                            </label>
                            <small class="form-hint">
                                Bila aktif, pajak dihitung dari <strong>DPP × rasio</strong>, bukan dari DPP penuh.
                                Pasangkan dengan tarif pajak <strong>12%</strong> agar hasilnya setara 11% dari harga jual.
                            </small>
                        </div>

                        <x-form.input name="dpp_ratio_numerator" label="Pembilang Rasio" type="number"
                                      :value="$settings['dpp_ratio_numerator'] ?? 11" col="col-md-3" />
                        <x-form.input name="dpp_ratio_denominator" label="Penyebut Rasio" type="number"
                                      :value="$settings['dpp_ratio_denominator'] ?? 12" col="col-md-3" />
                        <x-form.input name="wht_service_rate" label="Tarif PPh 23 Jasa" type="number" step="0.01"
                                      :value="$settings['wht_service_rate'] ?? 2" col="col-md-3" suffix="%"
                                      help="Tarif bawaan saat membuat faktur bertipe Jasa." />

                        <div class="col-md-6" x-show="aktif" x-cloak>
                            <div class="alert alert-info mb-0 py-2">
                                <strong>Contoh</strong> harga jual Rp 10.000.000<br>
                                DPP Nilai Lain = 11/12 × 10.000.000 = <strong>9.166.667</strong><br>
                                PPN 12% × 9.166.667 = <strong>1.100.000</strong> (setara 11% harga jual)
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3 mb-0" x-show="!aktif" x-cloak>
                        Saat ini pajak dihitung langsung dari DPP penuh. Cara ini benar bila tarif pajak
                        yang dipakai sudah 11%, tetapi faktur tidak menampilkan baris DPP Nilai Lain
                        yang biasanya diminta pada faktur pajak.
                    </div>

                    <x-slot:footer>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Simpan Pengaturan Pajak</button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            <form method="POST" action="{{ route('settings.operations') }}" id="operations" class="mt-3">
                @csrf @method('PUT')
                <x-card title="Pengaturan Operasional">
                    <div class="row g-3">
                        <x-form.checkbox name="allow_negative_stock" label="Izinkan stok negatif"
                                         :value="$settings['allow_negative_stock'] ?? false" col="col-md-6"
                                         help="Jika nonaktif, pengeluaran barang melebihi stok akan ditolak." />
                        <x-form.checkbox name="require_po_approval" label="Wajib persetujuan pesanan pembelian"
                                         :value="$settings['require_po_approval'] ?? true" col="col-md-6" />

                        <x-form.checkbox name="show_decimals" label="Tampilkan 2 angka di belakang koma"
                                         :value="$settings['show_decimals'] ?? false" col="col-md-12"
                                         help="Berlaku pada semua transaksi, laporan, dan dokumen cetak. Nilai tetap disimpan penuh sampai dua desimal — yang berubah hanya tampilannya." />

                        <x-form.input name="work_start_time" label="Jam Masuk Standar" type="time"
                                      :value="$settings['work_start_time'] ?? '08:00'" col="col-md-3" />
                        <x-form.input name="work_end_time" label="Jam Pulang Standar" type="time"
                                      :value="$settings['work_end_time'] ?? '17:00'" col="col-md-3" />

                        <x-form.input name="payroll_bpjs_percent" label="Potongan BPJS" type="number" step="0.01"
                                      :value="$settings['payroll_bpjs_percent'] ?? 4" col="col-md-2" suffix="%" />
                        <x-form.input name="payroll_pph21_percent" label="Potongan PPh 21" type="number" step="0.01"
                                      :value="$settings['payroll_pph21_percent'] ?? 0" col="col-md-2" suffix="%" />
                        <x-form.input name="payroll_overtime_rate" label="Tarif Lembur / Jam" type="number" step="0.01"
                                      :value="$settings['payroll_overtime_rate'] ?? 25000" col="col-md-2" prefix="Rp" />

                        <x-form.textarea name="invoice_footer_note" label="Catatan Kaki Faktur"
                                         :value="$settings['invoice_footer_note'] ?? ''" rows="2" />
                    </div>

                    <x-slot:footer>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Simpan Pengaturan</button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            {{-- Document numbering --}}
            <x-card title="Format Nomor Dokumen" subtitle="Prefix, reset periode, dan nomor berikutnya" flush class="mt-3" id="sequences">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr><th>Modul</th><th>Prefix</th><th>Reset</th><th>Digit</th><th>Nomor Berikutnya</th><th class="w-1"></th></tr>
                        </thead>
                        <tbody>
                        @foreach($sequences as $sequence)
                            <tr>
                                <form method="POST" action="{{ route('settings.sequence', $sequence) }}">
                                    @csrf @method('PUT')
                                    <td class="text-secondary">{{ \Illuminate\Support\Str::headline($sequence->module) }}</td>
                                    <td><input type="text" name="prefix" value="{{ $sequence->prefix }}" class="form-control form-control-sm" style="width:6rem"></td>
                                    <td>
                                        <select name="reset_period" class="form-select form-select-sm" style="width:8rem">
                                            <option value="never" @selected($sequence->reset_period === 'never')>Tidak</option>
                                            <option value="yearly" @selected($sequence->reset_period === 'yearly')>Tahunan</option>
                                            <option value="monthly" @selected($sequence->reset_period === 'monthly')>Bulanan</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="padding" value="{{ $sequence->padding }}" min="1" max="10" class="form-control form-control-sm" style="width:5rem"></td>
                                    <td><input type="number" name="next_number" value="{{ $sequence->next_number }}" min="1" class="form-control form-control-sm" style="width:7rem"></td>
                                    <td>
                                        @can('setting.edit')
                                            <button type="submit" class="btn btn-sm btn-ghost-primary"><i class="ti ti-device-floppy"></i></button>
                                        @endcan
                                    </td>
                                </form>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
@endsection
