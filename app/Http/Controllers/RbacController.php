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
            'superadminRole' => Rbac::SUPERADMIN_ROLE,
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

            // The Superadmin role keeps rbac.manage no matter what the form
            // posts, otherwise a superadmin could lock every superadmin out of
            // this page. This follows the role that holds the permission, not
            // the one whose name happens to start with "admin".
            if ($role->name === Rbac::SUPERADMIN_ROLE) {
                $names[] = Rbac::MANAGE_PERMISSION;
            }

            $role->syncPermissions(array_values(array_intersect(array_unique($names), $known)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role permissions updated.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        // Enforced here and not only in the form. The form disables the control
        // for a Superadmin, but a disabled control is a rendering decision, and
        // losing the last Superadmin cannot be undone from inside the app -
        // nobody would be left holding rbac.manage to put it back.
        if ($user->hasRole(Rbac::SUPERADMIN_ROLE)) {
            return back()->with('error', "{$user->name} holds the Superadmin role. It cannot be changed here.");
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles([$validated['role']]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Role updated for {$user->name}.");
    }
}
