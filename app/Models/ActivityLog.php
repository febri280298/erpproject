<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectName(): string
    {
        return $this->subject_type ? class_basename($this->subject_type) : '—';
    }

    public function eventColor(): string
    {
        return match ($this->event) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'posted', 'approved' => 'teal',
            'login' => 'azure',
            default => 'secondary',
        };
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"));
    }
}
