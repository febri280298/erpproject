<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('system.users.index', [
            'users' => User::query()
                ->with('roles:id,name')
                ->search($request->query('q'))
                ->when($request->filled('role'), fn ($q) => $q->role($request->query('role')))
                ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => Role::orderBy('name')->pluck('name', 'name'),
        ]);
    }

    public function create(): View
    {
        return view('system.users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::orderBy('name')->pluck('name', 'name'),
            'assigned' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')->with('success', "Pengguna \"{$user->name}\" berhasil ditambahkan.");
    }

    public function edit(User $user): View
    {
        return view('system.users.form', [
            'user' => $user,
            'roles' => Role::orderBy('name')->pluck('name', 'name'),
            'assigned' => $user->roles->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,name'],
        ]);

        // Guard against locking yourself out of the system.
        if ($user->id === Auth::id() && ! ($data['is_active'] ?? true)) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? false,
        ];

        // An empty password field means "leave the current password alone".
        if (filled($data['password'] ?? null)) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')->with('success', "Pengguna \"{$user->name}\" berhasil diperbarui.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "Pengguna \"{$name}\" berhasil dihapus.");
    }
}
