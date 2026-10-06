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
        return $this->tanpaPecahanSen((float) $this->total - (float) ($this->paid_amount ?? 0));
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
     * Sisa yang lebih kecil dari satu rupiah, kurang maupun lebih, dibaca nol.
     *
     * Ini satu-satunya tempat aturan itu diterapkan. Status lunas, warna kolom
     * Sisa, laporan umur piutang, saldo mitra, batas alokasi pembayaran, dan
     * cetakan faktur semuanya membaca sisa lewat outstandingAmount(), jadi
     * tidak ada lagi yang menilai Rp 0,44 sebagai utang sementara yang lain
     * menilainya lunas.
     *
     * Sebelumnya aturan ini hanya dipakai pemeriksa status. Faktur berubah
     * Lunas, tetapi kolom Sisa masih membandingkan "> 0" terhadap angka mentah
     * dan menampilkan "Rp 0" berwarna merah di sebelahnya.
     */
    protected function tanpaPecahanSen(float $sisa): float
    {
        $sisa = round($sisa, 2);

        return abs($sisa) < self::SISA_DIABAIKAN ? 0.0 : $sisa;
    }

    /**
     * Lunas bila sisa tagihannya habis.
     *
     * Sengaja bertumpu pada outstandingAmount(), angka yang sama persis dengan
     * yang tercetak di kolom Sisa, bukan menghitung ulang dari total. Dulu
     * keduanya dihitung terpisah dan berbeda: kolom Sisa memotong PPh 23 serta
     * nota kredit, pemeriksa status tidak, sehingga faktur yang sudah lunas
     * tetap tampil "Sebagian".
     */
    public function lunas(): bool
    {
        return $this->outstandingAmount() <= 0;
    }
}
