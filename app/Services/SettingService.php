<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached key/value application settings. The whole table is small, so it is
 * loaded once per request cycle rather than queried per key.
 */
class SettingService
{
    private const CACHE_KEY = 'erp.settings';

    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    /** @return array<string,mixed> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::all()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
            ->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'value' => is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
            ]
        );

        $this->flush();
    }

    /** @param array<string,mixed> $values */
    public function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_numeric($value) && ! is_string($value) => 'number',
                is_array($value) => 'json',
                default => Setting::where('key', $key)->value('type') ?? 'string',
            };

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'type' => $type,
                    'value' => is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                ]
            );
        }

        $this->flush();
    }

    /** @return array<string,mixed> */
    public function group(string $group): array
    {
        return Setting::where('group', $group)->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
            ->all();
    }

    public function flush(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
    }

    public function company(): array
    {
        return [
            'name' => $this->get('company_name', config('app.name')),
            'address' => $this->get('company_address', ''),
            'phone' => $this->get('company_phone', ''),
            'email' => $this->get('company_email', ''),
            'npwp' => $this->get('company_npwp', ''),
            'logo' => $this->get('company_logo'),
            // Dicetak pada faktur sebagai tujuan transfer.
            'bank_name' => $this->get('company_bank_name', ''),
            'bank_account' => $this->get('company_bank_account', ''),
            'bank_holder' => $this->get('company_bank_holder', ''),
        ];
    }
}
