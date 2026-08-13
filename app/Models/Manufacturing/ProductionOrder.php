<?php

namespace App\Models\Manufacturing;

use App\Models\Accounting\Journal;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionOrder extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'order_no', 'date', 'due_date', 'bom_id', 'product_id', 'warehouse_id',
        'quantity', 'produced_qty', 'material_cost', 'overhead_cost', 'total_cost',
        'status', 'notes', 'created_by', 'completed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'quantity' => 'decimal:4',
        'produced_qty' => 'decimal:4',
        'material_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    public function canComplete(): bool
    {
        return in_array($this->status, ['released', 'in_progress'], true);
    }

    public function unitCost(): float
    {
        $qty = (float) $this->produced_qty ?: (float) $this->quantity;

        return $qty > 0 ? round((float) $this->total_cost / $qty, 2) : 0.0;
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('order_no', 'like', "%{$v}%"))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
