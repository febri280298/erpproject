<?php

namespace App\Models\Hr;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Employee extends Model
{
    use LogsActivity, Searchable, SoftDeletes;

    protected $fillable = [
        'nik', 'name', 'user_id', 'department_id', 'position_id', 'gender',
        'birth_date', 'join_date', 'resign_date', 'employment_type', 'phone',
        'email', 'address', 'bank_name', 'bank_account', 'npwp',
        'basic_salary', 'allowance', 'photo', 'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'resign_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
    ];

    protected static array $searchable = ['nik', 'name', 'email', 'phone'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function grossSalary(): float
    {
        return (float) $this->basic_salary + (float) $this->allowance;
    }

    public function yearsOfService(): float
    {
        return $this->join_date ? round($this->join_date->diffInYears(now(), true), 1) : 0.0;
    }

    /** Remaining annual leave days for the current year. */
    public function remainingLeave(?int $leaveTypeId = null): float
    {
        $type = $leaveTypeId
            ? LeaveType::find($leaveTypeId)
            : LeaveType::query()->where('code', 'TAHUNAN')->first();

        if (! $type) {
            return 0.0;
        }

        $used = (float) $this->leaves()
            ->where('leave_type_id', $type->id)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->sum('days');

        return max(0, $type->max_days - $used);
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->take(2)
            ->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'resigned' => 'secondary',
            'terminated' => 'red',
            default => 'secondary',
        };
    }
}
