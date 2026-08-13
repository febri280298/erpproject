<?php

namespace App\Models\Sales;

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Master\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomerPayment extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'payment_no', 'date', 'partner_id', 'account_id', 'amount',
        'method', 'reference', 'status', 'notes', 'created_by', 'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CustomerPaymentItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'cash' => 'Tunai',
            'cheque' => 'Cek',
            'giro' => 'Giro',
            default => 'Transfer',
        };
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('payment_no', 'like', "%{$v}%")
                ->orWhere('reference', 'like', "%{$v}%")))
            ->when($f['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
