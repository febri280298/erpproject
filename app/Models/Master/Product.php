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
     * @return array<int,array<string,mixed>>
     */
    public static function optionsPayload(): array
    {
        return static::query()
            ->active()
            ->with('uom:id,code', 'tax:id,rate')
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
            ])
            ->all();
    }
}
