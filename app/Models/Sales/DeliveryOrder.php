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

class DeliveryOrder extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'do_no', 'date', 'sales_order_id', 'partner_id', 'warehouse_id',
        'driver_name', 'vehicle_no', 'shipping_address', 'status', 'notes',
        'created_by', 'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
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

    public function totalQuantity(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('do_no', 'like', "%{$v}%"))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
