<?php

namespace App\Models\Manufacturing;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    protected $fillable = ['bom_id', 'product_id', 'quantity', 'waste_percent', 'notes'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percent' => 'decimal:2',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Quantity including the allowance for expected waste. */
    public function effectiveQty(): float
    {
        return round((float) $this->quantity * (1 + (float) $this->waste_percent / 100), 4);
    }
}
