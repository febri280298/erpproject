<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'late_minutes', 'overtime_hours', 'status', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'late_minutes' => 'integer',
        'overtime_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'present' => 'green',
            'late' => 'orange',
            'absent' => 'red',
            'leave' => 'azure',
            'sick' => 'yellow',
            'holiday' => 'secondary',
            default => 'secondary',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Alpa',
            'leave' => 'Cuti',
            'sick' => 'Sakit',
            'holiday' => 'Libur',
            default => ucfirst((string) $this->status),
        };
    }

    public function workedHours(): float
    {
        if (! $this->check_in || ! $this->check_out) {
            return 0.0;
        }

        $in = strtotime((string) $this->check_in);
        $out = strtotime((string) $this->check_out);

        return $out > $in ? round(($out - $in) / 3600, 2) : 0.0;
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($w) => $w->where('department_id', $v)))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v));
    }
}
