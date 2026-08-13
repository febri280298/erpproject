<?php

namespace App\Services;

/**
 * Server-side twin of resources/js/doc-items.js. The browser's numbers are only
 * a preview — these are the values actually stored.
 */
class LineItemCalculator
{
    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>> rows with subtotal/tax_amount/total filled in
     */
    public function calculate(array $rows): array
    {
        return array_values(array_map(function (array $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            $discountPercent = (float) ($row['discount_percent'] ?? 0);
            $taxRate = (float) ($row['tax_rate'] ?? 0);

            $gross = round($qty * $price, 2);
            $subtotal = round($gross - ($gross * $discountPercent / 100), 2);
            $tax = round($subtotal * $taxRate / 100, 2);

            return array_merge($row, [
                'quantity' => round($qty, 4),
                'unit_price' => round($price, 2),
                'discount_percent' => round($discountPercent, 2),
                'tax_rate' => round($taxRate, 4),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => round($subtotal + $tax, 2),
            ]);
        }, $rows));
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows  already passed through calculate()
     * @return array{subtotal:float,tax_amount:float,total:float}
     */
    public function totals(array $rows, float $discountAmount = 0, float $shippingCost = 0): array
    {
        $subtotal = round(array_sum(array_column($rows, 'subtotal')), 2);
        $tax = round(array_sum(array_column($rows, 'tax_amount')), 2);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal - $discountAmount + $shippingCost + $tax, 2),
        ];
    }
}
