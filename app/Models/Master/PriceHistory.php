<?php

namespace App\Models\Master;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of every price change. Written by PricingService whenever
 * a stored price actually differs from the one being saved.
 */
class PriceHistory extends Model
{
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    protected $fillable = [
        'product_id', 'price_type', 'partner_id', 'price_level_id', 'label',
        'old_price', 'new_price', 'difference', 'percent', 'source', 'notes', 'changed_by',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'difference' => 'decimal:2',
        'percent' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class, 'price_level_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function isIncrease(): bool
    {
        return (float) $this->difference > 0;
    }

    public function typeLabel(): string
    {
        return $this->price_type === self::TYPE_PURCHASE ? 'Harga Beli' : 'Harga Jual';
    }

    public function directionColor(): string
    {
        return $this->isIncrease() ? 'red' : 'green';
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['price_type'] ?? null, fn ($q, $v) => $q->where('price_type', $v))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
