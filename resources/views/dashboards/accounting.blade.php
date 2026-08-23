@extends('layouts.app')

@section('title', 'Dashboard Akuntansi')
@section('pretitle', 'Akuntansi · ' . now()->translatedFormat('F Y'))

@section('actions')
    <div class="btn-list">
        @can('journal.create')
            <a href="{{ route('journals.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Jurnal Baru
            </a>
        @endcan
        @can('accounting-report.view')
            <a href="{{ route('accounting.balance-sheet') }}" class="btn">
                <i class="ti ti-scale me-1"></i> Neraca
            </a>
        @endcan
    </div>
@endsection

@section('content')
    {{-- Pembukuan yang tidak seimbang adalah kesalahan, bukan sekadar catatan:
         diangkat paling atas karena semua angka di bawahnya jadi meragukan. --}}
    @if(abs($selisihJurnal) >= 0.01)
        <div class="alert alert-danger" role="alert">
            <div class="d-flex">
                <div><i class="ti ti-alert-triangle me-2"></i></div>
                <div>
                    <h4 class="alert-title">Debit dan kredit tidak seimbang</h4>
                    <div>
                        Selisih {{ rupiah(abs($selisihJurnal)) }} pada jurnal terposting.
                        Seluruh laporan keuangan ikut salah selama selisih ini ada.
                    </div>
                    @can('journal.view')
                        <div class="mt-2">
                            <a href="{{ route('journals.index') }}" class="btn btn-sm btn-danger">Periksa jurnal</a>
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    @if(count($akunBelumDipetakan) > 0)
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-title">{{ count($akunBelumDipetakan) }} pemetaan akun belum diatur</h4>
            <p class="mb-2">Posting dokumen akan gagal selama pemetaannya belum lengkap.</p>
            @can('setting.edit')
                <a href="{{ route('settings.edit') }}#accounting" class="btn btn-sm btn-warning">Atur sekarang</a>
            @endcan
        </div>
    @endif

    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Kas & bank" :value="rupiah($stats['kas_bank'])"
                    icon="ti ti-wallet" color="green" :href="route('accounts.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Pendapatan bulan ini" :value="rupiah($stats['pendapatan'])"
                    icon="ti ti-trending-up" color="teal" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Beban bulan ini" :value="rupiah($stats['beban'])"
                    icon="ti ti-trending-down" color="orange" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat label="Laba bulan ini" :value="rupiah($stats['laba'])"
                    icon="ti ti-report-money" :color="$stats['laba'] >= 0 ? 'green' : 'red'"
                    hint="Pendapatan dikurangi beban" />
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-8">
            <x-card title="Pendapatan vs Beban" subtitle="12 bulan terakhir, dari jurnal terposting">
                <div id="grafik-labarugi" style="height:16rem"></div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Posisi Keuangan" subtitle="Saldo kumulatif">
                <table class="table table-sm table-vcenter card-table mb-0">
                    <tbody>
                    <tr>
                        <td class="text-secondary">Aset</td>
                        <td class="text-num fw-bold">{{ rupiah($neraca['aset']) }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Liabilitas</td>
                        <td class="text-num">{{ rupiah($neraca['liabilitas']) }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Ekuitas</td>
                        <td class="text-num">{{ rupiah($neraca['ekuitas']) }}</td>
                    </tr>
                    </tbody>
                </table>

                @php $selisihNeraca = round($neraca['aset'] - $neraca['liabilitas'] - $neraca['ekuitas'], 2); @endphp

                <div class="mt-3 small {{ abs($selisihNeraca) < 0.01 ? 'text-secondary' : 'text-danger' }}">
                    @if(abs($selisihNeraca) < 0.01)
                        <i class="ti ti-circle-check me-1"></i>
                        Aset = Liabilitas + Ekuitas
                    @else
                        <i class="ti ti-alert-triangle me-1"></i>
                        Selisih {{ rupiah($selisihNeraca) }} — laba berjalan belum ditutup ke ekuitas,
                        atau ada jurnal yang tidak seimbang.
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-7">
            <x-card title="Jurnal Terbaru">
                <x-slot:actions>
                    @if($jurnalDraft > 0)
                        <span class="badge bg-orange-lt">{{ fnum($jurnalDraft, 0) }} draft</span>
                    @endif
                </x-slot:actions>

                @if($jurnalTerbaru->isEmpty())
                    <x-empty title="Belum ada jurnal"
                             message="Jurnal terbentuk otomatis saat dokumen diposting." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Tanggal</th>
                                <th>Sumber</th>
                                <th>Status</th>
                                <th class="text-num">Baris</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($jurnalTerbaru as $j)
                                <tr>
                                    <td><a href="{{ route('journals.show', $j) }}">{{ $j->journal_no }}</a></td>
                                    <td class="text-secondary">{{ fdate($j->date) }}</td>
                                    <td class="text-secondary small">{{ $j->sourceLabel() }}</td>
                                    <td><x-status :value="$j->status" /></td>
                                    <td class="text-num text-secondary">{{ $j->lines_count }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card title="Akun Paling Aktif" subtitle="Bulan ini, berdasarkan nilai mutasi">
                @if($akunTeraktif->isEmpty())
                    <x-empty title="Belum ada mutasi bulan ini"
                             message="Akun yang terpakai akan muncul di sini." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter card-table">
                            <thead>
                            <tr>
                                <th>Akun</th>
                                <th class="text-num">Baris</th>
                                <th class="text-num">Mutasi</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($akunTeraktif as $a)
                                <tr>
                                    <td>
                                        <div>{{ $a->name }}</div>
                                        <div class="text-secondary small font-monospace">{{ $a->code }}</div>
                                    </td>
                                    <td class="text-num text-secondary">{{ $a->baris }}</td>
                                    <td class="text-num fw-bold">{{ rupiah($a->nilai) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wadah = document.getElementById('grafik-labarugi');

            if (! wadah) {
                return;
            }

            // erp.gambarGrafik memuat ApexCharts secara malas; memanggil
            // ApexCharts langsung di sini akan gagal karena pustakanya belum ada
            // saat DOMContentLoaded.
            window.erp.gambarGrafik(wadah, {
                chart: { type: 'bar', height: 256, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: 'Pendapatan', data: @json($grafik['series']['Pendapatan']) },
                    { name: 'Beban', data: @json($grafik['series']['Beban']) },
                ],
                xaxis: { categories: @json($grafik['labels']) },
                yaxis: { labels: { formatter: (v) => v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : new Intl.NumberFormat('id-ID').format(v) } },
                tooltip: { y: { formatter: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } },
                dataLabels: { enabled: false },
                plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
                colors: ['#2fb344', '#f76707'],
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { strokeDashArray: 4 },
            });
        });
    </script>
@endpush
