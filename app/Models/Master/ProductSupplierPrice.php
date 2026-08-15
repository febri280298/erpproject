<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplierPrice extends Model
{
    protected $fillable = [
        'product_id', 'partner_id', 'price', 'supplier_sku', 'lead_time_days',
        'min_order_qty', 'is_preferred', 'last_purchased_at', 'notes', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_order_qty' => 'decimal:4',
        'lead_time_days' => 'integer',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
        'last_purchased_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function scopeCheapestFirst(Builder $query): Builder
    {
        return $query->orderBy('price');
    }
}
