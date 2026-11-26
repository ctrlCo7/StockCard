<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        $users = User::query()->with(['roles', 'office'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('users.index', ['users' => $users, 'filters' => $filters, 'roles' => Role::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['is_active' => true]), ...$this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except('roles')->all());
            $user->roles()->sync($data['roles']);

            return $user;
        });
        AuditLogger::log('user.created', "User {$user->email} created with roles: ".implode(', ', $user->roles->pluck('name')->all()), $user);

        return redirect()->route('users.index')->with('success', "User {$user->name} created.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user->load('roles'), ...$this->options()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $this->guardSelfLockout($request, $user, $data);

        $oldRoles = $user->roles->pluck('name')->all();
        DB::transaction(function () use ($user, $data) {
            $user->update(collect($data)->except('roles')->all());
            $user->roles()->sync($data['roles']);
        });
        AuditLogger::log('user.updated', "User {$user->email} updated", $user,
            ['roles' => $oldRoles], ['roles' => $user->fresh('roles')->roles->pluck('name')->all(), 'password_changed' => isset($data['password'])]);

        return redirect()->route('users.index')->with('success', "User {$user->name} updated.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->is_active]);
        AuditLogger::log($user->is_active ? 'user.activated' : 'user.deactivated', "User {$user->email} ".($user->is_active ? 'activated' : 'deactivated'), $user);

        return back()->with('success', "{$user->name} is now ".($user->is_active ? 'active' : 'inactive').'.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'designation' => ['nullable', 'string', 'max:150'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]) + ['is_active' => false];
    }

    /** Prevent an administrator from removing their own admin role or deactivating themselves. */
    private function guardSelfLockout(Request $request, User $user, array $data): void
    {
        if (! $user->is($request->user())) {
            return;
        }
        $adminRoleId = Role::where('name', 'admin')->value('id');
        abort_if(! in_array($adminRoleId, array_map('intval', $data['roles']), true) || ! $data['is_active'], 422,
            'You cannot remove your own administrator role or deactivate your own account.');
    }

    private function options(): array
    {
        return ['roles' => Role::orderBy('id')->get(), 'offices' => Office::orderBy('name')->get()];
    }
}
