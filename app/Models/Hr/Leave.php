<?php

namespace App\Models\Hr;

use App\Models\Concerns\HasDocumentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasDocumentStatus, LogsActivity;

    protected $fillable = [
        'leave_no', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'days', 'reason', 'status', 'approved_by', 'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('leave_no', 'like', "%{$v}%"))
            ->when($f['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($f['leave_type_id'] ?? null, fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->between($f['from'] ?? null, $f['to'] ?? null, 'start_date');
    }
}
