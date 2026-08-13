<?php

namespace App\Models\Purchasing;

use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Hr\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'pr_no', 'date', 'required_date', 'department_id', 'requested_by',
        'status', 'notes', 'approved_by', 'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'required_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Approved requisitions may still be turned into a PO. */
    public function canCreatePo(): bool
    {
        return $this->status === 'approved';
    }

    public function estimatedTotal(): float
    {
        return (float) $this->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price);
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('pr_no', 'like', "%{$v}%"))
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
