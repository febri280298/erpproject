<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = ['code', 'name', 'days', 'is_active'];

    protected $casts = [
        'days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name'];
}
