<?php

namespace App\Models\Accounting;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chart of accounts entry. Balances are always derived from posted journal
 * lines plus the opening balance — never stored as a running total.
 */
class Account extends Model
{
    use LogsActivity, Searchable;

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    protected $fillable = [
        'code', 'name', 'type', 'subtype', 'normal_balance', 'parent_id',
        'is_postable', 'is_active', 'opening_balance', 'description',
    ];

    protected $casts = [
        'is_postable' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
    ];

    protected static array $searchable = ['code', 'name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string|array $type): Builder
    {
        return $query->whereIn('type', (array) $type);
    }

    /** Cash and bank accounts usable as a payment source/destination. */
    public function scopeCashAndBank(Builder $query): Builder
    {
        return $query->postable()->whereIn('subtype', ['cash', 'bank']);
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'debit';
    }

    /**
     * Balance in the account's normal direction, restricted to posted journals.
     */
    public function balance(?string $from = null, ?string $to = null): float
    {
        $totals = $this->lines()
            ->whereHas('journal', fn ($q) => $q->where('status', 'posted')
                ->when($from, fn ($w) => $w->whereDate('date', '>=', $from))
                ->when($to, fn ($w) => $w->whereDate('date', '<=', $to)))
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $movement = $this->isDebitNormal()
            ? (float) $totals->d - (float) $totals->c
            : (float) $totals->c - (float) $totals->d;

        // Opening balance only applies when no explicit start date was given.
        return $movement + ($from ? 0.0 : (float) $this->opening_balance);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'asset' => 'Aset',
            'liability' => 'Kewajiban',
            'equity' => 'Ekuitas',
            'revenue' => 'Pendapatan',
            'expense' => 'Beban',
            default => ucfirst((string) $this->type),
        };
    }

    /**
     * Balances for every postable account in one query — used by the trial
     * balance, balance sheet and income statement instead of N per-account hits.
     *
     * @return Collection<int,object{account_id:int,debit:float,credit:float}>
     */
    public static function movementTotals(?string $from = null, ?string $to = null)
    {
        return DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journals.status', 'posted')
            ->when($from, fn ($q) => $q->whereDate('journals.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journals.date', '<=', $to))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->get()
            ->keyBy('account_id');
    }
}
