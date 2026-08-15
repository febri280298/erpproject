<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCustomerPrice extends Model
{
    protected $fillable = ['product_id', 'partner_id', 'price', 'min_qty', 'notes', 'is_active'];

    protected $casts = [
        'price' => 'decimal:2',
        'min_qty' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function marginPercent(): float
    {
        $cost = (float) ($this->product?->purchase_price ?? 0);

        return $cost > 0 ? round((((float) $this->price - $cost) / $cost) * 100, 2) : 0.0;
    }
}
