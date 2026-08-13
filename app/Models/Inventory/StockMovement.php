<?php

namespace App\Models\Inventory;

use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only inventory ledger. One row per stock change; `balance_qty` and
 * `balance_value` are the running totals for the product/warehouse pair after
 * the movement, which is what the stock card report reads.
 */
class StockMovement extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected $fillable = [
        'date', 'product_id', 'warehouse_id', 'direction', 'ref_type', 'ref_id', 'ref_no',
        'quantity', 'unit_cost', 'balance_qty', 'balance_value', 'notes', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'balance_qty' => 'decimal:4',
        'balance_value' => 'decimal:2',
    ];

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

    public function isIn(): bool
    {
        return $this->direction === self::IN;
    }

    public function value(): float
    {
        return (float) $this->quantity * (float) $this->unit_cost;
    }

    public function refLabel(): string
    {
        return match ($this->ref_type) {
            'goods_receipt' => 'Penerimaan Barang',
            'delivery_order' => 'Surat Jalan',
            'transfer_out' => 'Transfer Keluar',
            'transfer_in' => 'Transfer Masuk',
            'adjustment' => 'Penyesuaian',
            'production_in' => 'Hasil Produksi',
            'production_out' => 'Pemakaian Produksi',
            'opening' => 'Saldo Awal',
            default => ucfirst(str_replace('_', ' ', (string) $this->ref_type)),
        };
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($f['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($f['ref_type'] ?? null, fn ($q, $v) => $q->where('ref_type', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v));
    }
}
