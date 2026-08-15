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
        'do_no', 'date', 'sales_order_id', 'sales_invoice_id', 'partner_id', 'warehouse_id',
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

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function isInvoiced(): bool
    {
        return $this->sales_invoice_id !== null;
    }

    /** Sudah dikirim tetapi belum ditagih faktur mana pun. */
    public function scopeUninvoiced(Builder $query): Builder
    {
        return $query->where('status', 'posted')->whereNull('sales_invoice_id');
    }

    /**
     * Jumlah bersih yang layak ditagih: yang dikirim dikurangi yang sudah
     * diretur, sehingga barang yang telanjur kembali tidak ikut tertagih.
     */
    public function billableItems()
    {
        return $this->items->filter(fn (DeliveryOrderItem $i) => $i->returnableQty() > 0);
    }

    /** Sudah diposting dan masih menyisakan barang yang bisa dikembalikan. */
    public function canReturn(): bool
    {
        return $this->status === 'posted'
            && $this->items->contains(fn (DeliveryOrderItem $i) => $i->returnableQty() > 0);
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
