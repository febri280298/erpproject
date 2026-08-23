<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates gap-free, per-module document numbers such as `PO/2026/08/0001`.
 *
 * The sequence row is locked for the duration of the enclosing transaction so
 * two concurrent posts can never claim the same number.
 */
class DocumentNumberService
{
    /**
     * Modul yang nomornya menyisipkan inisial mitra: `PO/GB/2026/08/0001`.
     *
     * Urutan pencacahnya tetap satu per modul, bukan per mitra. Dengan begitu
     * nomornya dijamin unik walau inisial mitra kelak diubah, dan tidak ada dua
     * dokumen berbeda yang bisa berakhir dengan nomor sama.
     */
    public const WITH_PARTNER_INITIAL = [
        'purchase_order',
        'goods_receipt',
        'delivery_order',
    ];

    /**
     * Ditampilkan pada pratinjau di form, saat mitranya belum dipilih.
     * Tanpa penanda ini pratinjau memperlihatkan nomor yang berbeda dari yang
     * akhirnya tersimpan, dan itu terbaca seperti sistem yang salah hitung.
     */
    private const PLACEHOLDER = '(mitra)';

    /** Modules seeded on install; `prefix` doubles as the fallback. */
    public const DEFAULTS = [
        'purchase_requisition' => ['prefix' => 'PR', 'reset_period' => 'monthly'],
        'purchase_order' => ['prefix' => 'PO', 'reset_period' => 'monthly'],
        'goods_receipt' => ['prefix' => 'GRN', 'reset_period' => 'monthly'],
        'purchase_invoice' => ['prefix' => 'BLI', 'reset_period' => 'monthly'],
        'supplier_payment' => ['prefix' => 'BYR', 'reset_period' => 'monthly'],
        'quotation' => ['prefix' => 'QT', 'reset_period' => 'monthly'],
        'sales_order' => ['prefix' => 'SO', 'reset_period' => 'monthly'],
        'delivery_order' => ['prefix' => 'SJ', 'reset_period' => 'monthly'],
        'sales_invoice' => ['prefix' => 'INV', 'reset_period' => 'monthly'],
        'customer_payment' => ['prefix' => 'RCP', 'reset_period' => 'monthly'],
        'sales_return' => ['prefix' => 'RTR', 'reset_period' => 'monthly'],
        'stock_transfer' => ['prefix' => 'TRF', 'reset_period' => 'monthly'],
        'stock_adjustment' => ['prefix' => 'ADJ', 'reset_period' => 'monthly'],
        'journal' => ['prefix' => 'JV', 'reset_period' => 'monthly'],
        'production_order' => ['prefix' => 'WO', 'reset_period' => 'monthly'],
        'bom' => ['prefix' => 'BOM', 'reset_period' => 'yearly'],
        'leave' => ['prefix' => 'CTI', 'reset_period' => 'yearly'],
        'payroll' => ['prefix' => 'PAY', 'reset_period' => 'yearly'],
    ];

    public function next(string $module, ?string $date = null, ?string $initial = null): string
    {
        $when = $date ? Carbon::parse($date) : Carbon::now();

        return DB::transaction(function () use ($module, $when, $initial) {
            $sequence = NumberSequence::query()
                ->where('module', $module)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $defaults = self::DEFAULTS[$module] ?? ['prefix' => strtoupper(substr($module, 0, 3)), 'reset_period' => 'monthly'];
                $sequence = NumberSequence::create([
                    'module' => $module,
                    'prefix' => $defaults['prefix'],
                    'reset_period' => $defaults['reset_period'],
                    'padding' => 4,
                    'next_number' => 1,
                    'period_year' => $when->year,
                    'period_month' => $when->month,
                ]);
            }

            if ($this->periodChanged($sequence, $when)) {
                $sequence->next_number = 1;
                $sequence->period_year = $when->year;
                $sequence->period_month = $when->month;
            }

            $number = $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->period_year = $when->year;
            $sequence->period_month = $when->month;
            $sequence->save();

            return $this->format($sequence, $when, $number, $this->segment($module, $initial));
        });
    }

    private function periodChanged(NumberSequence $sequence, Carbon $when): bool
    {
        return match ($sequence->reset_period) {
            'yearly' => $sequence->period_year !== $when->year,
            'monthly' => $sequence->period_year !== $when->year || $sequence->period_month !== $when->month,
            default => false,
        };
    }

    /**
     * Ruas inisial untuk modul ini, atau null bila tidak dipakai.
     *
     * Mitra yang belum berinisial tidak diberi penanda apa pun — nomornya
     * kembali ke bentuk tanpa ruas itu. Menuliskan penanda ke nomor yang
     * benar-benar tersimpan justru mengabadikan kekurangan data.
     */
    private function segment(string $module, ?string $initial): ?string
    {
        if (! in_array($module, self::WITH_PARTNER_INITIAL, true)) {
            return null;
        }

        $bersih = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $initial));

        return $bersih !== '' ? $bersih : null;
    }

    private function format(NumberSequence $sequence, Carbon $when, int $number, ?string $initial = null): string
    {
        $padded = str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);

        $ruas = array_filter([$sequence->prefix, $initial]);

        $ruas = match ($sequence->reset_period) {
            'monthly' => [...$ruas, $when->format('Y'), $when->format('m'), $padded],
            'yearly' => [...$ruas, $when->format('Y'), $padded],
            default => [...$ruas, $padded],
        };

        return implode('/', $ruas);
    }

    /**
     * Pratinjau saja — tidak memakai nomor.
     *
     * Mitranya belum dipilih saat form dibuka, jadi ruas inisialnya ditampilkan
     * sebagai penanda agar bentuk nomornya sudah terbaca sejak awal.
     */
    public function peek(string $module, ?string $date = null, ?string $initial = null): string
    {
        $when = $date ? Carbon::parse($date) : Carbon::now();
        $sequence = NumberSequence::where('module', $module)->first();

        if (! $sequence) {
            $defaults = self::DEFAULTS[$module] ?? ['prefix' => strtoupper(substr($module, 0, 3)), 'reset_period' => 'monthly'];
            $sequence = new NumberSequence(array_merge($defaults, ['padding' => 4, 'next_number' => 1]));
        }

        $number = $this->periodChanged($sequence, $when) ? 1 : $sequence->next_number;

        $ruas = $this->segment($module, $initial)
            ?? (in_array($module, self::WITH_PARTNER_INITIAL, true) ? self::PLACEHOLDER : null);

        return $this->format($sequence, $when, $number, $ruas);
    }
}
