<?php

namespace App\Models\Sales;

use App\Models\Accounting\Journal;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Partner;
use App\Models\Master\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Barang yang dikembalikan customer setelah surat jalan diposting.
 *
 * Surat jalan asalnya tetap berstatus posted — retur adalah peristiwa baru,
 * bukan pembatalan pengiriman.
 */
class SalesReturn extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'return_no', 'date', 'delivery_order_id', 'sales_invoice_id', 'partner_id',
        'warehouse_id', 'reason', 'subtotal', 'tax_amount', 'total',
        'cost_returned', 'cost_damaged', 'issue_credit_note', 'status',
        'notes', 'created_by', 'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_returned' => 'decimal:2',
        'cost_damaged' => 'decimal:2',
        'issue_credit_note' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    /** Nota kredit hanya terbit bila diminta dan menempel pada sebuah faktur. */
    public function hasCreditNote(): bool
    {
        return $this->issue_credit_note && $this->sales_invoice_id !== null;
    }

    public function totalQuantity(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public function damagedQuantity(): float
    {
        return (float) $this->items->where('condition', SalesReturnItem::DAMAGED)->sum('quantity');
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('return_no', 'like', "%{$v}%"))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
