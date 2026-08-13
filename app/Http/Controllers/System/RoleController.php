<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('system.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function __construct(private readonly ModuleRegistry $modules) {}

    public function create(): View
    {
        return view('system.roles.form', array_merge([
            'role' => new Role,
            'assigned' => [],
        ], $this->matrix()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', "Peran \"{$role->name}\" berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        return view('system.roles.form', array_merge([
            'role' => $role,
            'assigned' => $role->permissions->pluck('name')->all(),
        ], $this->matrix()));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Peran Super Admin tidak dapat diubah.');
        }

        $data = $this->validated($request, $role);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', "Peran \"{$role->name}\" berhasil diperbarui.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Peran Super Admin tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Peran ini masih dipakai oleh pengguna.');
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('roles.index')->with('success', "Peran \"{$name}\" berhasil dihapus.");
    }

    /**
     * Builds the permission matrix: one row per subject, one column per action.
     *
     * Subjects are ordered and labelled from config so the table reads like the
     * sidebar. Anything present in the database but missing from the config map
     * still shows up, under "Lainnya", so a new permission is never invisible.
     *
     * @return array{groups:array<string,mixed>, actions:array<string,string>}
     */
    private function matrix(): array
    {
        $existing = Permission::orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => Str::before($name, '.'))
            ->map(fn ($names) => $names->map(fn ($n) => Str::after($n, '.'))->all());

        $groups = [];
        $mapped = [];

        foreach (config('erp.permission_groups') as $groupLabel => $group) {
            $rows = [];

            foreach ($group['subjects'] as $subject => $label) {
                if (! $existing->has($subject)) {
                    continue;
                }

                $mapped[] = $subject;
                $rows[] = [
                    'subject' => $subject,
                    'label' => $label,
                    'available' => $existing[$subject],
                ];
            }

            if ($rows !== []) {
                $groups[$groupLabel] = [
                    'rows' => $rows,
                    'enabled' => $this->modules->enabled($group['module']),
                ];
            }
        }

        $orphans = $existing->keys()->diff($mapped);

        if ($orphans->isNotEmpty()) {
            $groups['Lainnya'] = [
                'enabled' => true,
                'rows' => $orphans->map(fn (string $subject) => [
                    'subject' => $subject,
                    'label' => Str::headline($subject),
                    'available' => $existing[$subject],
                ])->values()->all(),
            ];
        }

        return ['groups' => $groups, 'actions' => config('erp.permission_actions')];
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);
    }
}
