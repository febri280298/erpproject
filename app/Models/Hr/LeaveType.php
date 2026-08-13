<?php

namespace App\Models\Hr;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = ['code', 'name', 'max_days', 'is_paid', 'is_active'];

    protected $casts = [
        'max_days' => 'integer',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name'];

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }
}
