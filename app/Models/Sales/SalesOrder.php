<?php

namespace App\Models\Sales;

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

class SalesOrder extends Model
{
    use CalculatesTotals, HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'so_no', 'date', 'delivery_date', 'partner_id', 'warehouse_id', 'quotation_id',
        'payment_term_id', 'customer_po_no', 'status', 'subtotal', 'discount_amount',
        'shipping_cost', 'tax_amount', 'total', 'notes', 'terms',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'approved_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    public function canDeliver(): bool
    {
        return in_array($this->status, ['confirmed', 'partial'], true)
            && $this->items->contains(fn (SalesOrderItem $i) => $i->outstandingQty() > 0);
    }

    public function canInvoice(): bool
    {
        return in_array($this->status, ['confirmed', 'partial', 'delivered'], true);
    }

    public function deliveredPercent(): float
    {
        $ordered = (float) $this->items->sum('quantity');

        return $ordered > 0 ? round((float) $this->items->sum('delivered_qty') / $ordered * 100, 1) : 0.0;
    }

    public function syncDeliveryStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled', 'closed'], true)) {
            return;
        }

        $items = $this->items()->get();
        $fully = $items->every(fn (SalesOrderItem $i) => (float) $i->delivered_qty >= (float) $i->quantity);
        $any = $items->contains(fn (SalesOrderItem $i) => (float) $i->delivered_qty > 0);

        $this->forceFill(['status' => $fully ? 'delivered' : ($any ? 'partial' : 'confirmed')])->saveQuietly();
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('so_no', 'like', "%{$v}%")
                ->orWhere('customer_po_no', 'like', "%{$v}%")))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
