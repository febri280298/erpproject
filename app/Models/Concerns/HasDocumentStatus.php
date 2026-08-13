<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared helpers for documents that move through a draft → posted lifecycle.
 * `$statusColors` on the model maps a status to a Tabler badge colour.
 */
trait HasDocumentStatus
{
    /** @var array<string,string> */
    protected static array $defaultStatusColors = [
        'draft' => 'secondary',
        'submitted' => 'azure',
        'sent' => 'azure',
        'pending' => 'yellow',
        'approved' => 'blue',
        'accepted' => 'blue',
        'confirmed' => 'blue',
        'released' => 'blue',
        'in_progress' => 'indigo',
        'partial' => 'orange',
        'posted' => 'green',
        'received' => 'green',
        'delivered' => 'green',
        'paid' => 'green',
        'completed' => 'green',
        'closed' => 'dark',
        'rejected' => 'red',
        'cancelled' => 'red',
        'expired' => 'red',
        'reversed' => 'red',
    ];

    public function statusColor(): string
    {
        $map = array_merge(self::$defaultStatusColors, static::$statusColors ?? []);

        return $map[$this->status] ?? 'secondary';
    }

    public function statusLabel(): string
    {
        return __('status.'.$this->status);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return in_array($this->status, ['posted', 'partial', 'paid', 'received', 'delivered', 'closed', 'completed'], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'rejected'], true);
    }

    /** Draft documents are the only ones that may still be edited or deleted. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to, string $column = 'date'): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
            ->when($to, fn ($q) => $q->whereDate($column, '<=', $to));
    }
}
