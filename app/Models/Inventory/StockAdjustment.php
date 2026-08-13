<?php

namespace App\Models\Inventory;

use App\Models\Accounting\Journal;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Manual correction or stock-opname result. Posting writes one movement per
 * item for the difference between system and counted quantity.
 */
class StockAdjustment extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'adjustment_no', 'date', 'warehouse_id', 'reason', 'status',
        'notes', 'created_by', 'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    /** Signed value of the correction; positive means stock gained. */
    public function valueImpact(): float
    {
        return (float) $this->items->sum(fn (StockAdjustmentItem $i) => (float) $i->difference * (float) $i->unit_cost);
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('adjustment_no', 'like', "%{$v}%"))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
