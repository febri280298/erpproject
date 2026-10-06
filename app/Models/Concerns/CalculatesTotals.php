<?php

namespace App\Models\Concerns;

use App\Services\LineItemCalculator;

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

        // Nilai lain hanya berlaku pada baris berpajak; baris bebas pajak
        // tetap memakai DPP penuh dan tidak ikut dijumlahkan.
        $ratio = app(LineItemCalculator::class)->ratio();
        $this->dpp_other_amount = round((float) $items
            ->filter(fn ($item) => (float) $item->tax_rate > 0)
            ->sum(fn ($item) => round((float) $item->subtotal * $ratio, 2)), 2);

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

    /**
     * Sisa serupiah ke bawah yang tidak mungkin dibayar siapa pun.
     *
     * Pajak dihitung per baris lalu dibulatkan dua desimal, jadi total dokumen
     * hampir selalu membawa pecahan sen, sementara pembayaran diketik dalam
     * rupiah bulat. Yang tertinggal Rp 0,01 sampai Rp 0,99 — tidak bisa
     * ditransfer, tidak bisa ditagih, tetapi cukup untuk menahan faktur di
     * status "Sebagian" selamanya.
     */
    public const SISA_DIABAIKAN = 1.0;

    /**
     * Lunas bila sisa tagihannya tidak berarti lagi.
     *
     * Sengaja bertumpu pada outstandingAmount() — angka yang sama persis
     * dengan yang tercetak di kolom Sisa — bukan menghitung ulang dari total.
     * Dulu keduanya dihitung terpisah dan ikut berbeda: kolom Sisa memotong
     * PPh 23 serta nota kredit, pemeriksa status tidak, sehingga faktur yang
     * sudah lunas tetap tampil "Sebagian" di samping tulisan "Rp 0". Satu
     * sumber angka berarti layar dan status tidak bisa lagi berselisih.
     */
    public function lunas(): bool
    {
        return $this->outstandingAmount() < self::SISA_DIABAIKAN;
    }
}
