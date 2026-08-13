<?php

namespace App\Models\Sales;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id', 'product_id', 'description', 'quantity', 'delivered_qty',
        'invoiced_qty', 'unit_price', 'discount_percent', 'tax_rate', 'tax_amount',
        'subtotal', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'delivered_qty' => 'decimal:4',
        'invoiced_qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outstandingQty(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->delivered_qty, 4));
    }

    public function uninvoicedQty(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->invoiced_qty, 4));
    }
}
