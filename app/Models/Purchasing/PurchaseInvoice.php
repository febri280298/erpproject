<?php

namespace App\Models\Purchasing;

use App\Models\Accounting\Journal;
use App\Models\Concerns\CalculatesTotals;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseInvoice extends Model
{
    use CalculatesTotals, HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'invoice_no', 'supplier_invoice_no', 'date', 'due_date', 'partner_id',
        'purchase_order_id', 'subtotal', 'discount_amount', 'shipping_cost',
        'tax_amount', 'total', 'paid_amount', 'status', 'notes',
        'created_by', 'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(SupplierPayment::class, 'supplier_payment_items')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['posted', 'partial']);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && in_array($this->status, ['posted', 'partial'], true);
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()) : 0;
    }

    /** Re-derives paid/partial/posted from the amount actually settled. */
    public function syncPaymentStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return;
        }

        $paid = round((float) $this->paid_amount, 2);
        $total = round((float) $this->total, 2);

        $this->forceFill([
            'status' => $paid <= 0 ? 'posted' : ($paid >= $total ? 'paid' : 'partial'),
        ])->saveQuietly();
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('invoice_no', 'like', "%{$v}%")
                ->orWhere('supplier_invoice_no', 'like', "%{$v}%")))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when(($f['overdue'] ?? null) === '1', fn ($q) => $q->unpaid()->whereDate('due_date', '<', now()))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
