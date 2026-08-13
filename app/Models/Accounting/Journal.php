<?php

namespace App\Models\Accounting;

use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Journal extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'journal_no', 'date', 'type', 'reference', 'description',
        'source_type', 'source_id', 'total_debit', 'total_credit',
        'status', 'reversed_by', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by');
    }

    public function isBalanced(): bool
    {
        return bccomp((string) $this->total_debit, (string) $this->total_credit, 2) === 0;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'sales' => 'Penjualan',
            'purchase' => 'Pembelian',
            'cash' => 'Kas/Bank',
            'inventory' => 'Persediaan',
            'payroll' => 'Penggajian',
            default => 'Umum',
        };
    }

    public function sourceLabel(): string
    {
        return $this->source_type ? class_basename($this->source_type).' #'.$this->source_id : 'Manual';
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('journal_no', 'like', "%{$v}%")
                ->orWhere('reference', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->status($f['status'] ?? null)
            ->between($f['from'] ?? null, $f['to'] ?? null);
    }
}
