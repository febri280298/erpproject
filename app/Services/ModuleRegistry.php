<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Decides which modules an installation runs.
 *
 * The catalogue lives in config/erp.php; the on/off flags live in
 * `settings.modules_enabled` so they can be changed from the UI. Core modules
 * and anything the rest of the system depends on can never be switched off.
 *
 * Switching a module off only hides its menu and blocks its routes — no data is
 * deleted, and accounting keeps journalling in the background so the books stay
 * complete if the module comes back.
 */
class ModuleRegistry
{
    private const SETTING_KEY = 'modules_enabled';

    /** @var array<string,bool>|null */
    private ?array $state = null;

    public function __construct(private readonly SettingService $settings) {}

    /** @return array<string,array<string,mixed>> */
    public function catalogue(): array
    {
        return config('erp.modules', []);
    }

    public function exists(string $module): bool
    {
        return array_key_exists($module, $this->catalogue());
    }

    public function isCore(string $module): bool
    {
        return (bool) ($this->catalogue()[$module]['core'] ?? false);
    }

    public function label(string $module): string
    {
        return $this->catalogue()[$module]['label'] ?? $module;
    }

    /**
     * A module is on when its own flag is on *and* the module it depends on is
     * on too — so turning off Pembelian also hides Permintaan Pembelian.
     */
    public function enabled(?string $module): bool
    {
        if ($module === null || $module === '') {
            return true;
        }

        if (! $this->exists($module)) {
            return true;
        }

        if ($this->isCore($module)) {
            return true;
        }

        if (($this->flags()[$module] ?? true) === false) {
            return false;
        }

        $parent = $this->catalogue()[$module]['requires'] ?? null;

        return $parent ? $this->enabled($parent) : true;
    }

    public function disabled(?string $module): bool
    {
        return ! $this->enabled($module);
    }

    /** @return array<int,string> */
    public function enabledKeys(): array
    {
        return array_values(array_filter(
            array_keys($this->catalogue()),
            fn (string $module) => $this->enabled($module),
        ));
    }

    /** @param array<string,mixed> $selection module => truthy */
    public function save(array $selection): void
    {
        $flags = [];

        foreach ($this->catalogue() as $module => $meta) {
            $flags[$module] = ($meta['core'] ?? false)
                ? true
                : (bool) ($selection[$module] ?? false);
        }

        $this->settings->set(self::SETTING_KEY, $flags, 'modules', 'json');
        $this->state = null;
    }

    /** @return array<string,bool> */
    private function flags(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        // Guard the window between `migrate:fresh` and the settings seeder.
        if (! Schema::hasTable('settings')) {
            return $this->state = $this->defaults();
        }

        $stored = $this->settings->get(self::SETTING_KEY);

        return $this->state = is_array($stored)
            ? array_merge($this->defaults(), array_map(fn ($v) => (bool) $v, $stored))
            : $this->defaults();
    }

    /** @return array<string,bool> */
    private function defaults(): array
    {
        return array_map(
            fn (array $meta) => (bool) ($meta['default'] ?? true),
            $this->catalogue(),
        );
    }
}
