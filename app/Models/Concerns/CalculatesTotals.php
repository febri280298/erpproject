<?php

namespace App\Models\Concerns;

/**
 * Recomputes header money columns from the document's own line items so the
 * stored totals can never drift from the lines.
 */
trait CalculatesTotals
{
    public function recalculateTotals(bool $save = true): static
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $subtotal = round((float) $items->sum('subtotal'), 2);
        $tax = round((float) $items->sum('tax_amount'), 2);

        $this->subtotal = $subtotal;
        $this->tax_amount = $tax;
        $this->total = round($subtotal - (float) $this->discount_amount + (float) $this->shipping_cost + $tax, 2);

        if ($save) {
            $this->saveQuietly();
        }

        return $this;
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->total - (float) ($this->paid_amount ?? 0), 2);
    }
}
