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
        'stock_transfer' => ['prefix' => 'TRF', 'reset_period' => 'monthly'],
        'stock_adjustment' => ['prefix' => 'ADJ', 'reset_period' => 'monthly'],
        'journal' => ['prefix' => 'JV', 'reset_period' => 'monthly'],
        'production_order' => ['prefix' => 'WO', 'reset_period' => 'monthly'],
        'bom' => ['prefix' => 'BOM', 'reset_period' => 'yearly'],
        'leave' => ['prefix' => 'CTI', 'reset_period' => 'yearly'],
        'payroll' => ['prefix' => 'PAY', 'reset_period' => 'yearly'],
    ];

    public function next(string $module, ?string $date = null): string
    {
        $when = $date ? Carbon::parse($date) : Carbon::now();

        return DB::transaction(function () use ($module, $when) {
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

            return $this->format($sequence, $when, $number);
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

    private function format(NumberSequence $sequence, Carbon $when, int $number): string
    {
        $padded = str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);

        return match ($sequence->reset_period) {
            'monthly' => sprintf('%s/%s/%s/%s', $sequence->prefix, $when->format('Y'), $when->format('m'), $padded),
            'yearly' => sprintf('%s/%s/%s', $sequence->prefix, $when->format('Y'), $padded),
            default => sprintf('%s/%s', $sequence->prefix, $padded),
        };
    }

    /** Preview only — does not consume a number. */
    public function peek(string $module, ?string $date = null): string
    {
        $when = $date ? Carbon::parse($date) : Carbon::now();
        $sequence = NumberSequence::where('module', $module)->first();

        if (! $sequence) {
            $defaults = self::DEFAULTS[$module] ?? ['prefix' => strtoupper(substr($module, 0, 3)), 'reset_period' => 'monthly'];
            $sequence = new NumberSequence(array_merge($defaults, ['padding' => 4, 'next_number' => 1]));
        }

        $number = $this->periodChanged($sequence, $when) ? 1 : $sequence->next_number;

        return $this->format($sequence, $when, $number);
    }
}
