<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds every permission and role from config/erp.php so the RBAC matrix has
 * a single, reviewable definition.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];

        foreach (config('erp.permissions') as $subject => $actions) {
            foreach ($actions as $action) {
                $name = "{$subject}.{$action}";
                Permission::findOrCreate($name, 'web');
                $all[] = $name;
            }
        }

        foreach (config('erp.roles') as $roleName => $patterns) {
            $role = Role::findOrCreate($roleName, 'web');

            $granted = $patterns === ['*']
                ? $all
                : collect($all)
                    ->filter(fn (string $permission) => collect($patterns)
                        ->contains(fn (string $pattern) => Str::is($pattern, $permission)))
                    ->values()
                    ->all();

            $role->syncPermissions($granted);
        }

        $this->command?->info(sprintf('%d permission, %d peran disiapkan.', count($all), count(config('erp.roles'))));
    }
}
