<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\BuildsMonthlySeries;
use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Services\AccountMap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard modul Akuntansi.
 *
 * Yang ditonjolkan adalah hal-hal yang membuat pembukuan tidak bisa ditutup:
 * jurnal yang masih draft, pemetaan akun yang belum lengkap, dan — yang paling
 * penting — apakah debit masih sama dengan kredit. Selisih satu rupiah pun
 * berarti ada yang salah, dan itu jauh lebih mendesak daripada angka laba.
 */
class AccountingDashboardController extends Controller
{
    use BuildsMonthlySeries;

    public function __construct(private readonly AccountMap $accounts) {}

    public function __invoke(): View
    {
        $awal = CarbonImmutable::now()->startOfMonth();
        $akhir = CarbonImmutable::now()->endOfMonth();

        $pendapatan = $this->totalJenis('revenue', $awal, $akhir);
        $beban = $this->totalJenis('expense', $awal, $akhir);

        return view('dashboards.accounting', [
            'stats' => [
                'kas_bank' => $this->saldoKasBank(),
                'pendapatan' => $pendapatan,
                'beban' => $beban,
                'laba' => $pendapatan - $beban,
            ],
            'neraca' => [
                'aset' => $this->totalJenis('asset'),
                'liabilitas' => $this->totalJenis('liability'),
                'ekuitas' => $this->totalJenis('equity'),
            ],
            'selisihJurnal' => $this->selisihJurnal(),
            'jurnalDraft' => Journal::where('status', 'draft')->count(),
            'akunBelumDipetakan' => $this->accounts->missing(),
            'jurnalTerbaru' => Journal::withCount('lines')
                ->latest('date')->latest('id')->limit(8)->get(),
            'akunTeraktif' => $this->akunTeraktif($awal, $akhir),
            'grafik' => $this->grafikLabaRugi(),
        ]);
    }

    /**
     * Total saldo semua akun bertipe tertentu, dalam arah normalnya.
     *
     * Dihitung satu kueri agregat, bukan memanggil Account::balance() per akun —
     * bagan akun bisa ratusan baris dan dashboard tidak boleh menembakkan
     * ratusan kueri hanya untuk satu angka.
     */
    private function totalJenis(string $jenis, ?CarbonImmutable $dari = null, ?CarbonImmutable $sampai = null): float
    {
        $baris = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->where('accounts.type', $jenis)
            ->when($dari, fn ($q) => $q->whereDate('journals.date', '>=', $dari->toDateString()))
            ->when($sampai, fn ($q) => $q->whereDate('journals.date', '<=', $sampai->toDateString()))
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as k')
            ->first();

        $selisih = (float) $baris->d - (float) $baris->k;

        // Aset dan beban bersaldo normal debit; sisanya kredit.
        $normalDebit = in_array($jenis, ['asset', 'expense'], true);
        $nilai = $normalDebit ? $selisih : -$selisih;

        // Saldo awal hanya relevan untuk saldo kumulatif, bukan untuk periode.
        if (! $dari) {
            $nilai += (float) Account::where('type', $jenis)->sum('opening_balance');
        }

        return round($nilai, 2);
    }

    private function saldoKasBank(): float
    {
        return round(Account::cashAndBank()->get()->sum(fn (Account $a) => $a->balance()), 2);
    }

    /**
     * Selisih debit dan kredit seluruh jurnal terposting. Harus nol.
     */
    private function selisihJurnal(): float
    {
        $baris = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journals.status', 'posted')
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as k')
            ->first();

        return round((float) $baris->d - (float) $baris->k, 2);
    }

    private function akunTeraktif(CarbonImmutable $awal, CarbonImmutable $akhir)
    {
        return DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->whereBetween('journals.date', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->selectRaw('accounts.code, accounts.name, COUNT(*) as baris, SUM(journal_lines.debit + journal_lines.credit) as nilai')
            ->orderByDesc('nilai')
            ->limit(6)
            ->get();
    }

    /**
     * Pendapatan vs beban 12 bulan. Tidak memakai monthlySeries() karena
     * sumbernya jurnal, bukan tabel dokumen yang berkolom `total` dan `status`.
     *
     * @return array{labels:array<int,string>,series:array<string,array<int,float>>}
     */
    private function grafikLabaRugi(): array
    {
        $awal = CarbonImmutable::now()->startOfMonth()->subMonths(11);

        $ambil = function (string $jenis) use ($awal) {
            return DB::table('journal_lines')
                ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journals.status', 'posted')
                ->where('accounts.type', $jenis)
                ->whereDate('journals.date', '>=', $awal->toDateString())
                ->groupByRaw('YEAR(journals.date), MONTH(journals.date)')
                ->selectRaw('YEAR(journals.date) as y, MONTH(journals.date) as m, '
                    .'COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as k')
                ->get()
                ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->y, $r->m));
        };

        $pendapatan = $ambil('revenue');
        $beban = $ambil('expense');

        $labels = [];
        $deretPendapatan = [];
        $deretBeban = [];

        for ($i = 0; $i < 12; $i++) {
            $bulan = $awal->addMonths($i);
            $kunci = $bulan->format('Y-m');

            $p = $pendapatan->get($kunci);
            $b = $beban->get($kunci);

            $labels[] = $bulan->translatedFormat('M y');
            $deretPendapatan[] = round((float) ($p->k ?? 0) - (float) ($p->d ?? 0), 2);
            $deretBeban[] = round((float) ($b->d ?? 0) - (float) ($b->k ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'series' => ['Pendapatan' => $deretPendapatan, 'Beban' => $deretBeban],
        ];
    }
}
