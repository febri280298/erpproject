<?php

namespace App\Models\Inventory;

use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'quantity', 'reserved_qty', 'avg_cost'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'reserved_qty' => 'decimal:4',
        'avg_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function availableQty(): float
    {
        return (float) $this->quantity - (float) $this->reserved_qty;
    }

    public function value(): float
    {
        return (float) $this->quantity * (float) $this->avg_cost;
    }
}
