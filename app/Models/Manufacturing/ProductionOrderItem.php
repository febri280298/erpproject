<?php

namespace App\Models\Manufacturing;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderItem extends Model
{
    protected $fillable = [
        'production_order_id', 'product_id', 'planned_qty', 'consumed_qty', 'unit_cost', 'notes',
    ];

    protected $casts = [
        'planned_qty' => 'decimal:4',
        'consumed_qty' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cost(): float
    {
        return round((float) $this->consumed_qty * (float) $this->unit_cost, 2);
    }
}
