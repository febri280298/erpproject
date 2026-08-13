<?php

namespace App\Models\Accounting;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalPeriod extends Model
{
    protected $fillable = ['year', 'month', 'start_date', 'end_date', 'is_closed', 'closed_at', 'closed_by'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function label(): string
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    /** True when the given date falls inside a closed period. */
    public static function isLocked(string $date): bool
    {
        $d = CarbonImmutable::parse($date);

        return static::query()
            ->where('year', $d->year)
            ->where('month', $d->month)
            ->where('is_closed', true)
            ->exists();
    }
}
