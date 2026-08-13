<?php

namespace App\Models\Inventory;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'system_qty', 'actual_qty',
        'difference', 'unit_cost', 'notes',
    ];

    protected $casts = [
        'system_qty' => 'decimal:4',
        'actual_qty' => 'decimal:4',
        'difference' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
