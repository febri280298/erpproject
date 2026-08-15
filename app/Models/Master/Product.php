<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use LogsActivity, Searchable, SoftDeletes;

    public const TYPE_STOCK = 'stock';

    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'sku', 'barcode', 'name', 'type', 'product_category_id', 'uom_id', 'tax_id',
        'purchase_price', 'sale_price', 'min_stock', 'max_stock', 'image',
        'description', 'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_stock' => 'decimal:4',
        'max_stock' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['sku', 'barcode', 'name'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function supplierPrices(): HasMany
    {
        return $this->hasMany(ProductSupplierPrice::class);
    }

    public function customerPrices(): HasMany
    {
        return $this->hasMany(ProductCustomerPrice::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('id');
    }

    /**
     * Sale price, resolved in order of how specific the agreement is:
     * harga khusus customer → tingkat harga → tingkat default → harga dasar.
     */
    public function priceFor(?int $priceLevelId = null, ?int $customerId = null): float
    {
        if ($customerId) {
            $rows = $this->relationLoaded('customerPrices') ? $this->customerPrices : $this->customerPrices()->get();
            $special = $rows->firstWhere('partner_id', $customerId);

            if ($special && $special->is_active && (float) $special->price > 0) {
                return (float) $special->price;
            }
        }

        $prices = $this->relationLoaded('prices') ? $this->prices : $this->prices()->get();

        $exact = $priceLevelId
            ? $prices->firstWhere('price_level_id', $priceLevelId)
            : null;

        if ($exact && (float) $exact->price > 0) {
            return (float) $exact->price;
        }

        $default = $prices->first(fn (ProductPrice $p) => $p->level?->is_default && (float) $p->price > 0);

        return (float) ($default->price ?? $this->sale_price);
    }

    /**
     * Purchase price from a supplier, falling back to the preferred supplier and
     * then to the product's own `purchase_price`.
     */
    public function costFrom(?int $partnerId = null): float
    {
        $rows = $this->relationLoaded('supplierPrices') ? $this->supplierPrices : $this->supplierPrices()->get();

        $exact = $partnerId ? $rows->firstWhere('partner_id', $partnerId) : null;

        if ($exact && (float) $exact->price > 0) {
            return (float) $exact->price;
        }

        $preferred = $rows->first(fn (ProductSupplierPrice $p) => $p->is_preferred && (float) $p->price > 0);

        return (float) ($preferred->price ?? $this->purchase_price);
    }

    /** Cheapest active supplier offer, used to flag better deals. */
    public function cheapestSupplierPrice(): ?ProductSupplierPrice
    {
        $rows = $this->relationLoaded('supplierPrices') ? $this->supplierPrices : $this->supplierPrices()->get();

        return $rows->where('is_active', true)->where('price', '>', 0)->sortBy('price')->first();
    }

    public function scopeStockable(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_STOCK);
    }

    public function isStockable(): bool
    {
        return $this->type === self::TYPE_STOCK;
    }

    /** On-hand across all warehouses, or a single one when given. */
    public function onHand(?int $warehouseId = null): float
    {
        return (float) $this->stocks()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    public function isBelowMinimum(): bool
    {
        return $this->isStockable() && $this->min_stock > 0 && $this->onHand() < (float) $this->min_stock;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function label(): string
    {
        return $this->sku.' — '.$this->name;
    }

    /**
     * Payload consumed by the Alpine line-item editor.
     *
     * `prices` and `supplier_prices` let the browser re-price every row the
     * moment a customer or supplier is picked, without a round trip.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function optionsPayload(): array
    {
        return static::query()
            ->active()
            ->with([
                'uom:id,code', 'tax:id,rate',
                'prices:id,product_id,price_level_id,price',
                'supplierPrices:id,product_id,partner_id,price',
                'customerPrices:id,product_id,partner_id,price',
            ])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom_id', 'tax_id', 'purchase_price', 'sale_price'])
            ->map(fn (self $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'uom' => $p->uom?->code ?? '',
                'purchase_price' => (float) $p->purchase_price,
                'sale_price' => (float) $p->sale_price,
                'tax_rate' => (float) ($p->tax?->rate ?? 0),
                'prices' => $p->prices
                    ->mapWithKeys(fn (ProductPrice $r) => [(string) $r->price_level_id => (float) $r->price])
                    ->all(),
                'supplier_prices' => $p->supplierPrices
                    ->mapWithKeys(fn (ProductSupplierPrice $r) => [(string) $r->partner_id => (float) $r->price])
                    ->all(),
                'customer_prices' => $p->customerPrices
                    ->mapWithKeys(fn (ProductCustomerPrice $r) => [(string) $r->partner_id => (float) $r->price])
                    ->all(),
            ])
            ->all();
    }
}
