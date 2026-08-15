<?php

namespace App\Models\Sales;

use App\Models\Concerns\CalculatesTotals;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use CalculatesTotals, HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'quotation_no', 'date', 'valid_until', 'partner_id', 'status',
        'subtotal', 'dpp_other_amount', 'discount_amount', 'shipping_cost', 'tax_amount', 'total',
        'notes', 'terms', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'dpp_other_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until
            && $this->valid_until->isPast()
            && in_array($this->status, ['draft', 'sent'], true);
    }

    public function canConvert(): bool
    {
        return $this->status === 'accepted' && $this->salesOrders()->doesntExist();
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('quotation_no', 'like', "%{$v}%"))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
