<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FiscalPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FiscalPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);

        return view('accounting.periods.index', [
            'periods' => FiscalPeriod::query()
                ->with('closer:id,name')
                ->where('year', $year)
                ->orderBy('month')
                ->get(),
            'year' => $year,
            'years' => range(now()->year - 5, now()->year + 1),
        ]);
    }

    /** Creates the twelve periods of a year in one go. */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $created = 0;

        for ($month = 1; $month <= 12; $month++) {
            $start = CarbonImmutable::create($data['year'], $month, 1);

            $period = FiscalPeriod::firstOrCreate(
                ['year' => $data['year'], 'month' => $month],
                [
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->endOfMonth()->toDateString(),
                    'is_closed' => false,
                ]
            );

            $created += $period->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->route('fiscal-periods.index', ['year' => $data['year']])
            ->with('success', $created > 0
                ? "{$created} periode untuk tahun {$data['year']} berhasil dibuat."
                : "Semua periode tahun {$data['year']} sudah ada.");
    }

    public function toggle(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        $closing = ! $fiscalPeriod->is_closed;

        $fiscalPeriod->forceFill([
            'is_closed' => $closing,
            'closed_at' => $closing ? now() : null,
            'closed_by' => $closing ? Auth::id() : null,
        ])->save();

        return back()->with('success', $closing
            ? "Periode {$fiscalPeriod->label()} ditutup. Jurnal baru pada periode ini akan ditolak."
            : "Periode {$fiscalPeriod->label()} dibuka kembali.");
    }
}
