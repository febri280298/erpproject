<?php

namespace App\Models\Sales;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'delivery_order_id', 'sales_order_item_id', 'product_id', 'quantity', 'notes',
    ];

    protected $casts = ['quantity' => 'decimal:4'];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
