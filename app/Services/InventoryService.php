<?php

namespace App\Services;

use App\Models\Inventory\Stock;
use App\Models\Inventory\StockMovement;
use App\Models\Master\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry point for every stock change.
 *
 * Valuation is moving average: receiving blends the incoming cost into
 * `stocks.avg_cost`, issuing leaves the average untouched and takes the cost
 * from it. Each call appends exactly one row to `stock_movements` carrying the
 * running balance, which is what the stock card reads back.
 */
class InventoryService
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * Add stock and blend the unit cost into the moving average.
     */
    public function receive(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $refType,
        ?int $refId = null,
        ?string $refNo = null,
        ?string $date = null,
        ?string $notes = null,
    ): ?StockMovement {
        if ($quantity <= 0 || ! $this->isStockable($productId)) {
            return null;
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $refType, $refId, $refNo, $date, $notes) {
            $stock = $this->lockStock($productId, $warehouseId);

            $oldQty = (float) $stock->quantity;
            $oldValue = $oldQty * (float) $stock->avg_cost;

            $newQty = round($oldQty + $quantity, 4);
            $newValue = round($oldValue + ($quantity * $unitCost), 2);
            // A negative starting balance would poison the average; fall back to
            // the incoming cost in that case.
            $newAvg = $newQty > 0 ? round($newValue / $newQty, 4) : round($unitCost, 4);

            $stock->forceFill([
                'quantity' => $newQty,
                'avg_cost' => $newAvg,
            ])->save();

            return $this->writeMovement(
                StockMovement::IN, $productId, $warehouseId, $quantity, $unitCost,
                $newQty, $newValue, $refType, $refId, $refNo, $date, $notes
            );
        });
    }

    /**
     * Remove stock at the current moving-average cost.
     */
    public function issue(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $refType,
        ?int $refId = null,
        ?string $refNo = null,
        ?string $date = null,
        ?string $notes = null,
        ?float $forcedUnitCost = null,
    ): ?StockMovement {
        if ($quantity <= 0 || ! $this->isStockable($productId)) {
            return null;
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $refType, $refId, $refNo, $date, $notes, $forcedUnitCost) {
            $stock = $this->lockStock($productId, $warehouseId);

            $available = (float) $stock->quantity;

            if ($quantity > $available && ! $this->allowsNegativeStock()) {
                $product = Product::find($productId);
                throw new RuntimeException(sprintf(
                    'Stok %s tidak mencukupi. Tersedia %s, dibutuhkan %s.',
                    $product?->name ?? "#{$productId}",
                    rtrim(rtrim(number_format($available, 4, ',', '.'), '0'), ','),
                    rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',')
                ));
            }

            $unitCost = $forcedUnitCost ?? (float) $stock->avg_cost;

            // Rata-rata 0 berarti barangnya belum pernah diterima sama sekali —
            // hal yang baru mungkin terjadi sejak stok negatif diizinkan.
            // Tanpa pengganti, barang terkirim dengan HPP nol: persediaan tidak
            // pernah dikreditkan dan laba tercatat terlalu besar, dan tidak ada
            // yang memperbaikinya ketika barangnya akhirnya datang. Harga beli
            // di master dipakai sebagai taksiran; rata-rata sebenarnya
            // terbentuk saat penerimaan.
            if ($forcedUnitCost === null && $unitCost <= 0.0) {
                $unitCost = (float) (Product::find($productId)?->purchase_price ?? 0);
            }

            $newQty = round($available - $quantity, 4);
            $newValue = round($newQty * $unitCost, 2);

            $fill = ['quantity' => $newQty];

            // Taksirannya ikut disimpan sebagai rata-rata. Kalau tidak,
            // penerimaan berikutnya menilai saldo minus ini sebagai nol dan
            // rata-ratanya melonjak: 12 keluar lalu 20 masuk @139.000
            // menghasilkan rata-rata 347.500, bukan 139.000.
            if ((float) $stock->avg_cost <= 0.0 && $unitCost > 0.0) {
                $fill['avg_cost'] = round($unitCost, 4);
            }

            $stock->forceFill($fill)->save();

            return $this->writeMovement(
                StockMovement::OUT, $productId, $warehouseId, $quantity, $unitCost,
                $newQty, $newValue, $refType, $refId, $refNo, $date, $notes
            );
        });
    }

    /**
     * Undo every movement written by a document (used when a posted document is
     * cancelled). Counter-movements are appended so the ledger stays append-only.
     */
    public function reverseDocument(string $refType, int $refId, ?string $date = null, ?string $notes = null): void
    {
        $movements = StockMovement::query()
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->orderByDesc('id')
            ->get();

        foreach ($movements as $movement) {
            $note = $notes ?? 'Pembatalan '.$movement->ref_no;

            if ($movement->isIn()) {
                $this->issue(
                    $movement->product_id, $movement->warehouse_id, (float) $movement->quantity,
                    $refType.'_reversal', $refId, $movement->ref_no, $date, $note,
                    (float) $movement->unit_cost
                );
            } else {
                $this->receive(
                    $movement->product_id, $movement->warehouse_id, (float) $movement->quantity,
                    (float) $movement->unit_cost, $refType.'_reversal', $refId, $movement->ref_no, $date, $note
                );
            }
        }
    }

    public function currentCost(int $productId, int $warehouseId): float
    {
        $cost = (float) Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('avg_cost');

        // Fall back to the master purchase price for a product never received yet.
        return $cost > 0 ? $cost : (float) (Product::find($productId)?->purchase_price ?? 0);
    }

    public function onHand(int $productId, int $warehouseId): float
    {
        return (float) Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    /** Total inventory value across all warehouses (or just one). */
    public function totalValue(?int $warehouseId = null): float
    {
        return (float) Stock::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('COALESCE(SUM(quantity * avg_cost), 0) as v')
            ->value('v');
    }

    private function lockStock(int $productId, int $warehouseId): Stock
    {
        $stock = Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        return $stock ?? Stock::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'reserved_qty' => 0,
            'avg_cost' => 0,
        ]);
    }

    private function writeMovement(
        string $direction, int $productId, int $warehouseId, float $quantity, float $unitCost,
        float $balanceQty, float $balanceValue, string $refType, ?int $refId,
        ?string $refNo, ?string $date, ?string $notes,
    ): StockMovement {
        return StockMovement::create([
            'date' => $date ?? now()->toDateString(),
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'direction' => $direction,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'ref_no' => $refNo,
            'quantity' => round($quantity, 4),
            'unit_cost' => round($unitCost, 4),
            'balance_qty' => $balanceQty,
            'balance_value' => $balanceValue,
            'notes' => $notes,
            'created_by' => Auth::id(),
        ]);
    }

    private function isStockable(int $productId): bool
    {
        return Product::where('id', $productId)->value('type') === Product::TYPE_STOCK;
    }

    private function allowsNegativeStock(): bool
    {
        return (bool) $this->settings->get('allow_negative_stock', false);
    }
}
