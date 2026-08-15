<?php

namespace App\Services;

use App\Models\Master\Partner;
use App\Models\Master\PriceHistory;
use App\Models\Master\PriceLevel;
use App\Models\Master\Product;
use App\Models\Master\ProductCustomerPrice;
use App\Models\Master\ProductPrice;
use App\Models\Master\ProductSupplierPrice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to a price, so no price can change without leaving a trail.
 *
 * Both sync methods compare the incoming value against what is stored and only
 * touch `price_histories` when the number actually moved — re-saving a product
 * without editing prices does not pollute the log.
 */
class PricingService
{
    /**
     * @param  array<int,array{price_level_id:int|string, price:mixed, min_qty?:mixed}>  $rows
     * @return int number of tiers whose price changed
     */
    public function syncSalePrices(Product $product, array $rows, string $source = 'manual'): int
    {
        return DB::transaction(function () use ($product, $rows, $source) {
            $existing = $product->prices()->get()->keyBy('price_level_id');
            $levels = PriceLevel::query()->get()->keyBy('id');
            $changed = 0;
            $keep = [];

            foreach ($rows as $row) {
                $levelId = (int) ($row['price_level_id'] ?? 0);
                $level = $levels->get($levelId);

                if (! $level) {
                    continue;
                }

                $price = round((float) ($row['price'] ?? 0), 2);
                $minQty = round((float) ($row['min_qty'] ?? 0), 4);
                $current = $existing->get($levelId);
                $old = $current ? (float) $current->price : 0.0;

                // An empty tier means "not sold at this level" — drop the row.
                if ($price <= 0) {
                    if ($current) {
                        $this->record($product, PriceHistory::TYPE_SALE, $level->name, $old, 0, $source, priceLevelId: $levelId, notes: 'Tingkat harga dikosongkan');
                        $current->delete();
                        $changed++;
                    }

                    continue;
                }

                $keep[] = $levelId;

                if ($current) {
                    if (abs($old - $price) >= 0.01) {
                        $this->record($product, PriceHistory::TYPE_SALE, $level->name, $old, $price, $source, priceLevelId: $levelId);
                        $changed++;
                    }
                    $current->forceFill(['price' => $price, 'min_qty' => $minQty])->save();

                    continue;
                }

                ProductPrice::create([
                    'product_id' => $product->id,
                    'price_level_id' => $levelId,
                    'price' => $price,
                    'min_qty' => $minQty,
                    'is_active' => true,
                ]);

                $this->record($product, PriceHistory::TYPE_SALE, $level->name, 0, $price, $source, priceLevelId: $levelId, notes: 'Harga baru ditetapkan');
                $changed++;
            }

            // Tiers absent from the submitted form are removed.
            $product->prices()->whereNotIn('price_level_id', $keep ?: [0])->delete();

            $this->syncBaseSalePrice($product);

            return $changed;
        });
    }

    /**
     * Negotiated price for individual customers; overrides their tier.
     *
     * @param  array<int,array{partner_id:int|string, price:mixed, min_qty?:mixed, notes?:string|null}>  $rows
     * @return int number of customers whose price changed
     */
    public function syncCustomerPrices(Product $product, array $rows, string $source = 'manual'): int
    {
        return DB::transaction(function () use ($product, $rows, $source) {
            $existing = $product->customerPrices()->get()->keyBy('partner_id');
            $customers = Partner::query()->customers()->get()->keyBy('id');
            $changed = 0;
            $keep = [];

            foreach ($rows as $row) {
                $partnerId = (int) ($row['partner_id'] ?? 0);
                $customer = $customers->get($partnerId);

                if (! $customer) {
                    continue;
                }

                $price = round((float) ($row['price'] ?? 0), 2);
                $current = $existing->get($partnerId);
                $old = $current ? (float) $current->price : 0.0;

                if ($price <= 0) {
                    if ($current) {
                        $this->record($product, PriceHistory::TYPE_SALE, $customer->name, $old, 0, $source, partnerId: $partnerId, notes: 'Harga khusus customer dihapus');
                        $current->delete();
                        $changed++;
                    }

                    continue;
                }

                $keep[] = $partnerId;

                $attributes = [
                    'price' => $price,
                    'min_qty' => round((float) ($row['min_qty'] ?? 0), 4),
                    'notes' => $row['notes'] ?? null,
                    'is_active' => true,
                ];

                if ($current) {
                    if (abs($old - $price) >= 0.01) {
                        $this->record($product, PriceHistory::TYPE_SALE, $customer->name, $old, $price, $source, partnerId: $partnerId);
                        $changed++;
                    }
                    $current->forceFill($attributes)->save();

                    continue;
                }

                ProductCustomerPrice::create(array_merge($attributes, [
                    'product_id' => $product->id,
                    'partner_id' => $partnerId,
                ]));

                $this->record($product, PriceHistory::TYPE_SALE, $customer->name, 0, $price, $source, partnerId: $partnerId, notes: 'Harga khusus customer ditetapkan');
                $changed++;
            }

            $product->customerPrices()->whereNotIn('partner_id', $keep ?: [0])->delete();

            return $changed;
        });
    }

    /**
     * @param  array<int,array{partner_id:int|string, price:mixed, supplier_sku?:string|null, lead_time_days?:mixed, min_order_qty?:mixed, is_preferred?:mixed, notes?:string|null}>  $rows
     * @return int number of suppliers whose price changed
     */
    public function syncSupplierPrices(Product $product, array $rows, string $source = 'manual'): int
    {
        return DB::transaction(function () use ($product, $rows, $source) {
            $existing = $product->supplierPrices()->get()->keyBy('partner_id');
            $suppliers = Partner::query()->suppliers()->get()->keyBy('id');
            $changed = 0;
            $keep = [];
            $preferred = null;

            foreach ($rows as $row) {
                $partnerId = (int) ($row['partner_id'] ?? 0);
                $supplier = $suppliers->get($partnerId);

                if (! $supplier) {
                    continue;
                }

                $price = round((float) ($row['price'] ?? 0), 2);
                $current = $existing->get($partnerId);
                $old = $current ? (float) $current->price : 0.0;

                if ($price <= 0) {
                    if ($current) {
                        $this->record($product, PriceHistory::TYPE_PURCHASE, $supplier->name, $old, 0, $source, partnerId: $partnerId, notes: 'Harga supplier dihapus');
                        $current->delete();
                        $changed++;
                    }

                    continue;
                }

                $keep[] = $partnerId;

                $attributes = [
                    'price' => $price,
                    'supplier_sku' => $row['supplier_sku'] ?? null,
                    'lead_time_days' => (int) ($row['lead_time_days'] ?? 0),
                    'min_order_qty' => round((float) ($row['min_order_qty'] ?? 0), 4),
                    'is_preferred' => (bool) ($row['is_preferred'] ?? false),
                    'notes' => $row['notes'] ?? null,
                    'is_active' => true,
                ];

                if ($attributes['is_preferred']) {
                    $preferred = $partnerId;
                }

                if ($current) {
                    if (abs($old - $price) >= 0.01) {
                        $this->record($product, PriceHistory::TYPE_PURCHASE, $supplier->name, $old, $price, $source, partnerId: $partnerId);
                        $changed++;
                    }
                    $current->forceFill($attributes)->save();

                    continue;
                }

                ProductSupplierPrice::create(array_merge($attributes, [
                    'product_id' => $product->id,
                    'partner_id' => $partnerId,
                ]));

                $this->record($product, PriceHistory::TYPE_PURCHASE, $supplier->name, 0, $price, $source, partnerId: $partnerId, notes: 'Supplier baru ditambahkan');
                $changed++;
            }

            $product->supplierPrices()->whereNotIn('partner_id', $keep ?: [0])->delete();

            // Exactly one supplier may hold the preferred flag.
            if ($preferred) {
                $product->supplierPrices()->where('partner_id', '!=', $preferred)->update(['is_preferred' => false]);
            }

            $this->syncBasePurchasePrice($product);

            return $changed;
        });
    }

    /**
     * Records the actual cost paid when a goods receipt is posted, so the
     * supplier price list tracks reality without anyone retyping it.
     */
    public function noteReceiptCost(Product $product, int $partnerId, float $price, ?string $date = null): void
    {
        if ($price <= 0) {
            return;
        }

        $row = $product->supplierPrices()->firstWhere('partner_id', $partnerId);
        $old = $row ? (float) $row->price : 0.0;

        if ($row && abs($old - $price) < 0.01) {
            $row->forceFill(['last_purchased_at' => $date ?? now()->toDateString()])->save();

            return;
        }

        $supplier = Partner::find($partnerId);

        if (! $supplier) {
            return;
        }

        if ($row) {
            $row->forceFill(['price' => $price, 'last_purchased_at' => $date ?? now()->toDateString()])->save();
        } else {
            ProductSupplierPrice::create([
                'product_id' => $product->id,
                'partner_id' => $partnerId,
                'price' => $price,
                'last_purchased_at' => $date ?? now()->toDateString(),
                'is_active' => true,
            ]);
        }

        $this->record(
            $product, PriceHistory::TYPE_PURCHASE, $supplier->name, $old, $price,
            'receipt', partnerId: $partnerId, notes: 'Dari penerimaan barang'
        );

        $this->syncBasePurchasePrice($product);
    }

    /** Sale price a customer should get for a product. */
    public function salePrice(Product $product, ?Partner $customer = null, ?int $priceLevelId = null): float
    {
        return $product->priceFor(
            $priceLevelId ?? $customer?->effectivePriceLevelId(),
            $customer?->id,
        );
    }

    /** Purchase price to use when ordering a product from a supplier. */
    public function purchasePrice(Product $product, ?Partner $supplier = null): float
    {
        return $product->costFrom($supplier?->id);
    }

    /**
     * Keeps `products.purchase_price` aligned with the preferred (or cheapest)
     * supplier, so reports and fallbacks stay meaningful.
     */
    private function syncBasePurchasePrice(Product $product): void
    {
        $rows = $product->supplierPrices()->where('price', '>', 0)->get();

        if ($rows->isEmpty()) {
            return;
        }

        $best = $rows->firstWhere('is_preferred', true) ?? $rows->sortBy('price')->first();

        if ($best && abs((float) $product->purchase_price - (float) $best->price) >= 0.01) {
            $product->forceFill(['purchase_price' => (float) $best->price])->saveQuietly();
        }
    }

    /** Keeps `products.sale_price` aligned with the default tier. */
    private function syncBaseSalePrice(Product $product): void
    {
        $default = $product->prices()
            ->whereHas('level', fn ($q) => $q->where('is_default', true))
            ->where('price', '>', 0)
            ->first();

        if ($default && abs((float) $product->sale_price - (float) $default->price) >= 0.01) {
            $product->forceFill(['sale_price' => (float) $default->price])->saveQuietly();
        }
    }

    private function record(
        Product $product,
        string $type,
        string $label,
        float $old,
        float $new,
        string $source,
        ?int $partnerId = null,
        ?int $priceLevelId = null,
        ?string $notes = null,
    ): void {
        PriceHistory::create([
            'product_id' => $product->id,
            'price_type' => $type,
            'partner_id' => $partnerId,
            'price_level_id' => $priceLevelId,
            'label' => $label,
            'old_price' => $old,
            'new_price' => $new,
            'difference' => round($new - $old, 2),
            'percent' => $old > 0 ? round((($new - $old) / $old) * 100, 2) : 0,
            'source' => $source,
            'notes' => $notes,
            'changed_by' => Auth::id(),
        ]);
    }
}
