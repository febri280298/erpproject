<?php

namespace App\Models\Manufacturing;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Master\Product;
use App\Models\Master\Uom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bill of materials: what one production batch consumes to yield `quantity`
 * units of `product`.
 */
class Bom extends Model
{
    use LogsActivity, Searchable;

    protected $table = 'boms';

    protected $fillable = ['bom_no', 'name', 'product_id', 'quantity', 'uom_id', 'is_active', 'notes'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['bom_no', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /** Standard material cost of one batch, at current purchase prices. */
    public function materialCost(): float
    {
        return round((float) $this->items->sum(
            fn (BomItem $i) => $i->effectiveQty() * (float) ($i->product->purchase_price ?? 0)
        ), 2);
    }

    public function costPerUnit(): float
    {
        $qty = (float) $this->quantity;

        return $qty > 0 ? round($this->materialCost() / $qty, 2) : 0.0;
    }
}
