<?php

namespace App\Services;

/**
 * Server-side twin of resources/js/doc-items.js. The browser's numbers are only
 * a preview — these are the values actually stored.
 *
 * Bila DPP Nilai Lain diaktifkan (PMK 131/2024), pajak dihitung dari
 * `DPP × rasio` alih-alih dari DPP penuh: tarif 12% atas 11/12 harga jual
 * setara 11% dari harga jual.
 */
class LineItemCalculator
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>> rows with dpp/tax/total filled in
     */
    public function calculate(array $rows): array
    {
        $ratio = $this->ratio();

        return array_values(array_map(function (array $row) use ($ratio) {
            $qty = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            $discountPercent = (float) ($row['discount_percent'] ?? 0);
            $taxRate = (float) ($row['tax_rate'] ?? 0);

            $gross = round($qty * $price, 2);
            $subtotal = round($gross - ($gross * $discountPercent / 100), 2);

            // Baris tanpa pajak tidak mengenal nilai lain; dasarnya tetap DPP penuh.
            $taxBase = $taxRate > 0 ? round($subtotal * $ratio, 2) : $subtotal;
            $tax = round($taxBase * $taxRate / 100, 2);

            return array_merge($row, [
                'quantity' => round($qty, 4),
                'unit_price' => round($price, 2),
                'discount_percent' => round($discountPercent, 2),
                'tax_rate' => round($taxRate, 4),
                'subtotal' => $subtotal,
                'dpp_other' => $taxBase,
                'tax_amount' => $tax,
                'total' => round($subtotal + $tax, 2),
            ]);
        }, $rows));
    }

    /**
     * Every money column of the document header in one array.
     *
     * `discount_amount` and `shipping_cost` are echoed back deliberately: an
     * emptied input arrives as null (Laravel converts "" to null), and the
     * columns are NOT NULL, so the caller must never write the raw request
     * value straight to the model.
     *
     * @param  array<int,array<string,mixed>>  $rows  already passed through calculate()
     * @return array{subtotal:float,dpp_other_amount:float,discount_amount:float,shipping_cost:float,tax_amount:float,total:float}
     */
    public function totals(array $rows, float $discountAmount = 0, float $shippingCost = 0): array
    {
        $subtotal = round(array_sum(array_column($rows, 'subtotal')), 2);
        $tax = round(array_sum(array_column($rows, 'tax_amount')), 2);
        $discount = round($discountAmount, 2);
        $shipping = round($shippingCost, 2);

        // Hanya baris berpajak yang punya nilai lain; sisanya tidak ikut dijumlah.
        $dppOther = round(array_sum(array_map(
            fn (array $r) => (float) ($r['tax_rate'] ?? 0) > 0 ? (float) ($r['dpp_other'] ?? 0) : 0,
            $rows,
        )), 2);

        return [
            'subtotal' => $subtotal,
            'dpp_other_amount' => $dppOther,
            'discount_amount' => $discount,
            'shipping_cost' => $shipping,
            'tax_amount' => $tax,
            'total' => round($subtotal - $discount + $shipping + $tax, 2),
        ];
    }

    /** Aktif atau tidaknya DPP Nilai Lain beserta rasionya. */
    public function isDppOtherEnabled(): bool
    {
        return (bool) $this->settings->get('use_dpp_nilai_lain', false);
    }

    /** @return array{0:int,1:int} pembilang dan penyebut, mis. [11, 12] */
    public function ratioParts(): array
    {
        return [
            (int) $this->settings->get('dpp_ratio_numerator', 11),
            (int) $this->settings->get('dpp_ratio_denominator', 12),
        ];
    }

    public function ratioLabel(): string
    {
        [$num, $den] = $this->ratioParts();

        return "{$num}/{$den}";
    }

    /**
     * Pecahan disimpan sebagai pembilang & penyebut, bukan desimal, supaya
     * 11/12 tidak kehilangan presisi dan pajaknya meleset beberapa sen.
     */
    public function ratio(): float
    {
        if (! $this->isDppOtherEnabled()) {
            return 1.0;
        }

        [$num, $den] = $this->ratioParts();

        return $den > 0 ? $num / $den : 1.0;
    }
}
