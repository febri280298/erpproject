<?php

namespace App\Models\Sales;

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

class SalesInvoice extends Model
{
    use CalculatesTotals, HasDocumentStatus, LogsActivity;

    /** Faktur dengan PPN; tiap baris memakai tarif pajak produknya sendiri. */
    public const TYPE_PPN = 'ppn';

    /** Tanpa PPN sama sekali — seluruh baris dipaksa 0%. */
    public const TYPE_NON_PPN = 'non_ppn';

    /** Jasa: tetap ber-PPN, tetapi nilainya dipotong PPh 23 oleh customer. */
    public const TYPE_JASA = 'jasa';

    public const TYPES = [
        self::TYPE_PPN => 'PPN',
        self::TYPE_NON_PPN => 'Non-PPN',
        self::TYPE_JASA => 'Jasa',
    ];

    protected $fillable = [
        'invoice_no', 'invoice_type', 'wht_rate', 'wht_amount', 'date', 'due_date', 'partner_id', 'sales_order_id',
        'customer_po_no',
        'subtotal', 'dpp_other_amount', 'discount_amount', 'shipping_cost', 'tax_amount', 'total',
        'paid_amount', 'credit_amount', 'status', 'notes', 'terms', 'created_by', 'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'dpp_other_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'wht_rate' => 'decimal:4',
        'wht_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
        return $this->belongsToMany(CustomerPayment::class, 'customer_payment_items')
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

    /** Bucket used by the AR aging report. */
    public function agingBucket(): string
    {
        $days = $this->daysOverdue();

        return match (true) {
            $days <= 0 => 'current',
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => 'over_90',
        };
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    /** Surat jalan yang ditagih oleh faktur ini — bisa lebih dari satu. */
    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->invoice_type] ?? 'PPN';
    }

    public function typeColor(): string
    {
        return match ($this->invoice_type) {
            self::TYPE_NON_PPN => 'secondary',
            self::TYPE_JASA => 'purple',
            default => 'blue',
        };
    }

    public function isService(): bool
    {
        return $this->invoice_type === self::TYPE_JASA;
    }

    /**
     * Nomor faktur masih boleh diganti?
     *
     * Batasnya pembayaran, bukan status posting. Faktur yang sudah diposting
     * pun nomornya masih boleh dibetulkan — customer kerap baru meminta format
     * nomor tertentu setelah fakturnya diterima, dan menerbitkan faktur
     * pengganti hanya demi itu membuat pembukuan penuh dokumen batal.
     *
     * Begitu ada pembayaran yang dicatat, nomornya mengunci: bukti transfer,
     * rekening koran, dan pembukuan customer sudah menyebut nomor itu, dan
     * mengubahnya berarti memutus jejak yang sudah dipegang dua belah pihak.
     */
    public function canRenumber(): bool
    {
        return $this->status !== 'cancelled' && $this->payments()->doesntExist();
    }

    /**
     * Jumlah yang benar-benar akan diterima: total faktur dikurangi PPh 23
     * yang dipotong dan disetorkan sendiri oleh customer.
     */
    public function amountDue(): float
    {
        return round((float) $this->total - (float) $this->wht_amount, 2);
    }

    /**
     * Sisa tagihan setelah pembayaran dan nota kredit retur.
     *
     * Menimpa versi di CalculatesTotals karena hanya faktur penjualan yang
     * dapat dikurangi nota kredit dan potongan PPh 23.
     */
    public function outstandingAmount(): float
    {
        return round($this->amountDue() - (float) $this->paid_amount - (float) $this->credit_amount, 2);
    }

    public function syncPaymentStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return;
        }

        $settled = round((float) $this->paid_amount + (float) $this->credit_amount, 2);
        $total = round((float) $this->total, 2);

        $this->forceFill([
            'status' => $settled <= 0 ? 'posted' : ($settled >= $total ? 'paid' : 'partial'),
        ])->saveQuietly();
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            // Nomor PO customer ikut dicari: bagian hutang mereka mencocokkan
            // tagihan terhadap nomor PO-nya, bukan terhadap nomor faktur kita.
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('invoice_no', 'like', "%{$v}%")
                ->orWhere('customer_po_no', 'like', "%{$v}%")))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when(($f['overdue'] ?? null) === '1', fn ($q) => $q->unpaid()->whereDate('due_date', '<', now()))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
