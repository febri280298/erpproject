<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Business partner — a customer, a supplier, or both.
 */
class Partner extends Model
{
    use LogsActivity, Searchable, SoftDeletes;

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_BOTH = 'both';

    protected $fillable = [
        'code', 'name', 'type', 'contact_person', 'phone', 'email', 'npwp',
        'address', 'city', 'payment_term_id', 'credit_limit', 'opening_balance',
        'notes', 'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name', 'contact_person', 'phone', 'email', 'city'];

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function supplierPrices(): HasMany
    {
        return $this->hasMany(ProductSupplierPrice::class);
    }

    /** Tier used when quoting this customer; falls back to the default tier. */
    public function effectivePriceLevelId(): ?int
    {
        return $this->price_level_id ?? PriceLevel::defaultId();
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_CUSTOMER, self::TYPE_BOTH]);
    }

    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_SUPPLIER, self::TYPE_BOTH]);
    }

    public function isCustomer(): bool
    {
        return in_array($this->type, [self::TYPE_CUSTOMER, self::TYPE_BOTH], true);
    }

    public function isSupplier(): bool
    {
        return in_array($this->type, [self::TYPE_SUPPLIER, self::TYPE_BOTH], true);
    }

    /** Unpaid AR balance across posted sales invoices. */
    public function receivableBalance(): float
    {
        return (float) $this->salesInvoices()
            ->whereIn('status', ['posted', 'partial'])
            ->sum(DB::raw('total - paid_amount'));
    }

    /** Unpaid AP balance across posted purchase invoices. */
    public function payableBalance(): float
    {
        return (float) $this->purchaseInvoices()
            ->whereIn('status', ['posted', 'partial'])
            ->sum(DB::raw('total - paid_amount'));
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CUSTOMER => 'Pelanggan',
            self::TYPE_SUPPLIER => 'Pemasok',
            default => 'Pelanggan & Pemasok',
        };
    }
}
