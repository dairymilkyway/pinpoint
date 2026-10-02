<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacController extends Controller
{
    public function index(): View
    {
        return view('rbac.index', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
            'users' => User::with('roles')->orderBy('name')->get(),
            'adminRole' => Rbac::ADMIN_ROLE,
            'managePermission' => Rbac::MANAGE_PERMISSION,
        ]);
    }

    public function updatePermissions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', 'exists:permissions,name'],
        ]);

        $submitted = $validated['permissions'] ?? [];
        $known = Permission::pluck('name')->all();

        foreach (Role::all() as $role) {
            $names = $submitted[$role->name] ?? [];

            // The Admin role keeps rbac.manage no matter what the form posts,
            // otherwise an admin could lock every admin out of this page.
            if ($role->name === Rbac::ADMIN_ROLE) {
                $names[] = Rbac::MANAGE_PERMISSION;
            }

            $role->syncPermissions(array_values(array_intersect(array_unique($names), $known)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role permissions updated.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles([$validated['role']]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Role updated for {$user->name}.");
    }
}
