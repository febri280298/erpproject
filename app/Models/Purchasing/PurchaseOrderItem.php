<?php

namespace App\Models\Purchasing;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'description', 'quantity', 'received_qty',
        'invoiced_qty', 'unit_price', 'discount_percent', 'tax_rate', 'tax_amount',
        'subtotal', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'received_qty' => 'decimal:4',
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
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outstandingQty(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->received_qty, 4));
    }

    public function uninvoicedQty(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->invoiced_qty, 4));
    }
}
