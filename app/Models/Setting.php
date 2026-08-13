<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    /** Cast the stored string back to the declared type. */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'number' => is_numeric($this->value) ? $this->value + 0 : 0,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }
}
