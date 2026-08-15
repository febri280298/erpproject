<?php

namespace App\Models\Sales;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    public const GOOD = 'good';

    public const DAMAGED = 'damaged';

    protected $fillable = [
        'sales_return_id', 'delivery_order_item_id', 'product_id', 'quantity', 'condition',
        'unit_price', 'discount_percent', 'tax_rate', 'tax_amount',
        'subtotal', 'total', 'unit_cost', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'unit_cost' => 'decimal:4',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderItem::class, 'delivery_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Barang rusak tidak dimasukkan kembali ke stok yang dijual. */
    public function isDamaged(): bool
    {
        return $this->condition === self::DAMAGED;
    }

    public function conditionLabel(): string
    {
        return $this->isDamaged() ? 'Rusak' : 'Baik';
    }

    public function conditionColor(): string
    {
        return $this->isDamaged() ? 'red' : 'green';
    }

    public function costValue(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }
}
