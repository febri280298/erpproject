<?php

namespace App\Models\Hr;

use App\Models\Accounting\Journal;
use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payroll extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'payroll_no', 'period_year', 'period_month', 'payment_date',
        'total_gross', 'total_deduction', 'total_net', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'payment_date' => 'date',
        'total_gross' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'total_net' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'source', 'source_type', 'source_id');
    }

    public function periodLabel(): string
    {
        return CarbonImmutable::create($this->period_year, $this->period_month, 1)->translatedFormat('F Y');
    }

    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::create($this->period_year, $this->period_month, 1);
    }

    public function periodEnd(): CarbonImmutable
    {
        return $this->periodStart()->endOfMonth();
    }

    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $this->forceFill([
            'total_gross' => round((float) $items->sum('gross_salary'), 2),
            'total_deduction' => round((float) $items->sum('total_deduction'), 2),
            'total_net' => round((float) $items->sum('net_salary'), 2),
        ])->saveQuietly();
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('payroll_no', 'like', "%{$v}%"))
            ->when($f['year'] ?? null, fn ($q, $v) => $q->where('period_year', $v))
            ->when($f['month'] ?? null, fn ($q, $v) => $q->where('period_month', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
    }
}
