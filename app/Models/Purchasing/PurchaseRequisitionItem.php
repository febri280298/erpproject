<?php

namespace App\Models\Purchasing;

use App\Models\Master\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionItem extends Model
{
    protected $fillable = [
        'purchase_requisition_id', 'product_id', 'description',
        'quantity', 'ordered_qty', 'unit_price', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'ordered_qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outstandingQty(): float
    {
        return max(0, (float) $this->quantity - (float) $this->ordered_qty);
    }
}
