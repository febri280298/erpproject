<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Deret 12 bulan terakhir untuk grafik dashboard.
 *
 * Bulan tanpa transaksi tetap muncul sebagai nol. Tanpa itu grafik akan
 * memampatkan sumbu waktu — dua bulan yang berjauhan tampak berdampingan dan
 * tren terbaca lebih mulus daripada kenyataannya.
 */
trait BuildsMonthlySeries
{
    /**
     * @param  array<int,array{tabel:string,kolom?:string}>  $sumber  satu entri per garis grafik
     * @return array{labels:array<int,string>,series:array<string,array<int,float>>}
     */
    protected function monthlySeries(array $sumber, int $bulan = 12): array
    {
        $awal = CarbonImmutable::now()->startOfMonth()->subMonths($bulan - 1);

        $labels = [];
        $series = [];

        foreach ($sumber as $nama => $spesifikasi) {
            $kolom = $spesifikasi['kolom'] ?? 'total';

            $baris = DB::table($spesifikasi['tabel'])
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereDate('date', '>=', $awal->toDateString())
                ->groupByRaw('YEAR(date), MONTH(date)')
                ->selectRaw("YEAR(date) as y, MONTH(date) as m, COALESCE(SUM({$kolom}),0) as jumlah")
                ->get()
                ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->y, $r->m));

            $nilai = [];

            for ($i = 0; $i < $bulan; $i++) {
                $titik = $awal->addMonths($i);
                $nilai[] = round((float) ($baris->get($titik->format('Y-m'))->jumlah ?? 0), 2);
            }

            $series[$nama] = $nilai;
        }

        for ($i = 0; $i < $bulan; $i++) {
            $labels[] = $awal->addMonths($i)->translatedFormat('M y');
        }

        return ['labels' => $labels, 'series' => $series];
    }
}
