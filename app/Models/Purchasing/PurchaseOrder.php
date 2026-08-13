<?php

namespace App\Models\Purchasing;

use App\Models\Accounting\Journal;
use App\Models\Concerns\CalculatesTotals;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Partner;
use App\Models\Master\PaymentTerm;
use App\Models\Master\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseOrder extends Model
{
    use CalculatesTotals, HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'po_no', 'date', 'expected_date', 'partner_id', 'warehouse_id',
        'purchase_requisition_id', 'payment_term_id', 'status',
        'subtotal', 'discount_amount', 'shipping_cost', 'tax_amount', 'total',
        'notes', 'terms', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'expected_date' => 'date',
        'approved_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    /** Approved orders with outstanding lines may still be received. */
    public function canReceive(): bool
    {
        return in_array($this->status, ['approved', 'partial'], true)
            && $this->items->contains(fn (PurchaseOrderItem $i) => $i->outstandingQty() > 0);
    }

    public function canInvoice(): bool
    {
        return in_array($this->status, ['approved', 'partial', 'received'], true);
    }

    public function receivedPercent(): float
    {
        $ordered = (float) $this->items->sum('quantity');

        return $ordered > 0 ? round((float) $this->items->sum('received_qty') / $ordered * 100, 1) : 0.0;
    }

    /** Recomputes the header status from how much of the order has arrived. */
    public function syncReceiptStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled', 'closed'], true)) {
            return;
        }

        $items = $this->items()->get();
        $fully = $items->every(fn (PurchaseOrderItem $i) => (float) $i->received_qty >= (float) $i->quantity);
        $any = $items->contains(fn (PurchaseOrderItem $i) => (float) $i->received_qty > 0);

        $this->forceFill(['status' => $fully ? 'received' : ($any ? 'partial' : 'approved')])->saveQuietly();
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('po_no', 'like', "%{$v}%"))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
