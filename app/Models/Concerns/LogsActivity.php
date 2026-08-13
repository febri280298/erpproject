<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes an audit row whenever the model is created, updated or deleted.
 * Models opt in and may narrow what is stored via `$activityIgnored`.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created'));
        static::updated(fn (Model $model) => $model->recordActivity('updated'));
        static::deleted(fn (Model $model) => $model->recordActivity('deleted'));
    }

    public function recordActivity(string $event, ?string $description = null, array $properties = []): void
    {
        $ignored = array_merge(['updated_at', 'created_at', 'remember_token', 'password'], $this->activityIgnored ?? []);

        if ($event === 'updated') {
            $changes = collect($this->getChanges())->except($ignored);
            if ($changes->isEmpty()) {
                return;
            }
            $properties = array_merge($properties, ['changes' => $changes->all()]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'description' => $description ?? sprintf('%s %s: %s', $event, class_basename($this), $this->activityTitle()),
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
        ]);
    }

    /** Human-readable identifier used in the log line. */
    public function activityTitle(): string
    {
        foreach (['name', 'code', 'invoice_no', 'po_no', 'so_no', 'do_no', 'grn_no', 'payment_no', 'journal_no'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return (string) $this->getKey();
    }

    public function activities()
    {
        return $this->morphMany(ActivityLog::class, 'subject', 'subject_type', 'subject_id');
    }
}
