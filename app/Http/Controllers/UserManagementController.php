<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use App\Models\PermissionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('roles')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'permissions_count' => $user->getAllPermissions()->count(),
                    'created_at' => $user->created_at?->toDateString(),
                    'last_login' => $user->updated_at?->diffForHumans(),
                ];
            });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::withCount('permissions')->orderBy('name')->get(),
            'totalPermissions' => Permission::count(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::orderBy('name')->get(),
            'permissionTypes' => PermissionType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'permission_type_id' => 'nullable|exists:permission_types,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        // Create personal team
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => $user->name . "'s Team",
            'personal_team' => true,
        ]);
        $user->current_team_id = $team->id;
        $user->save();

        if (!empty($validated['permission_type_id'])) {
            $user->update(['permission_type_id' => $validated['permission_type_id']]);
            $permType = PermissionType::with('pages')->find($validated['permission_type_id']);
            $permType?->syncToUser($user);
        }

        return redirect()->route('users.index')->with('success', "User '{$user->name}' created with role '{$validated['role']}'.");
    }

    public function show(User $user)
    {
        return Inertia::render('Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
                'created_at' => $user->created_at?->toDateString(),
            ],
            'allRoles' => Role::orderBy('name')->get(),
            'allPermissions' => Permission::orderBy('name')->get()->groupBy(function ($p) {
                $parts = explode('_', $p->name, 2);
                return $parts[0] ?? 'other';
            }),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "Role updated to '{$validated['role']}'.");
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
        ]);

        $user->update($validated);

        return back()->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'Password reset.');
    }

    public function roles()
    {
        $roles = Role::with('permissions')->orderBy('name')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
                'users_count' => $role->users()->count(),
            ];
        });

        $permissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            // Group by module: view_customers -> customers, manage_settings -> settings
            $name = $p->name;
            $prefixes = ['view_', 'create_', 'edit_', 'delete_', 'manage_', 'send_', 'access_'];
            foreach ($prefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return str_replace($prefix, '', $name);
                }
            }
            return 'other';
        });

        return Inertia::render('Users/Roles', [
            'roles' => $roles,
            'permissionGroups' => $permissions,
        ]);
    }

    public function updateRolePermissions(Request $request, int $roleId)
    {
        $role = Role::findOrFail($roleId);

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Permissions updated for '{$role->name}'.");
    }
}
