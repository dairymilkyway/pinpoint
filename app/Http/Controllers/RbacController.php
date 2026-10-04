<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
            // withTrashed so a deactivated account stays in the table, dimmed,
            // with a Reactivate button. Without it the row is filtered out before
            // the view renders and the account cannot be brought back at all.
            'users' => User::withTrashed()->with('roles')->orderBy('name')->get(),
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

            // Dropped from every role before it is added back to the one that
            // holds it. rbac.manage is what gates this page, so a role holding
            // it can grant itself every other permission and come back here -
            // handing it out is self-service escalation, not delegation. The
            // form disables the box, but a disabled control is a rendering
            // decision and a crafted POST is not, so the rule lives here too.
            $names = array_diff($names, [Rbac::MANAGE_PERMISSION]);

            // And the Superadmin keeps it no matter what the form posts,
            // otherwise a superadmin could lock every superadmin out of this
            // page. This follows the role that holds the permission, not the
            // one whose name happens to start with "admin".
            if ($role->name === Rbac::SUPERADMIN_ROLE) {
                $names[] = Rbac::MANAGE_PERMISSION;
            }

            $before = $role->permissions->pluck('name')->sort()->values()->all();

            $role->syncPermissions(array_values(array_intersect(array_unique($names), $known)));

            $after = $role->permissions->pluck('name')->sort()->values()->all();

            // One entry per role that actually moved. A save that changes
            // nothing is not an act and leaves no trace.
            if ($before === $after) {
                continue;
            }

            AuditLog::record(
                AuditLog::RBAC_PERMISSIONS_CHANGED,
                $role,
                ['permissions' => implode(', ', $before)],
                ['permissions' => implode(', ', $after), 'role' => $role->name],
            );
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

        // Captured before the sync, because syncRoles replaces the relation and
        // the old role is the whole point of a before/after entry.
        $oldRole = $user->getRoleNames()->first();

        $user->syncRoles([$validated['role']]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // A role set to the value it already holds is not a change, and the
        // no-demote guard above returns before this point. Only the act records.
        if ($oldRole !== $validated['role']) {
            AuditLog::record(
                AuditLog::USER_ROLE_CHANGED,
                $user,
                ['role' => $oldRole],
                ['role' => $validated['role'], 'account' => $user->name],
            );
        }

        return back()->with('success', "Role updated for {$user->name}.");
    }

    /**
     * Soft-delete a user, blocking sign-in while keeping their addresses and
     * their name on them. Refusals return to the screen with an error rather
     * than an abort, matching assignRole: the page and the flash partial are the
     * same, so a refusal reads identically to the no-demote guard above.
     */
    public function deactivate(User $user): RedirectResponse
    {
        // A Superadmin locking themselves out is the same loss as demoting the
        // last one - nobody would be left holding rbac.manage to undo it.
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        // Same guard and wording as assignRole: the view locks the Superadmin
        // row entirely, but a crafted POST is not the view.
        if ($user->hasRole(Rbac::SUPERADMIN_ROLE)) {
            return back()->with('error', "{$user->name} holds the Superadmin role. It cannot be deactivated here.");
        }

        $user->delete();

        // Both refusals above return before this line, so only the act lands.
        AuditLog::record(AuditLog::USER_DEACTIVATED, $user, null, [
            'account' => $user->name,
            'state' => 'Deactivated',
        ]);

        return back()->with('success', "{$user->name} has been deactivated.");
    }

    /** Undo a deactivation, restoring sign-in. */
    public function reactivate(User $user): RedirectResponse
    {
        $user->restore();

        AuditLog::record(AuditLog::USER_REACTIVATED, $user, null, [
            'account' => $user->name,
            'state' => 'Reactivated',
        ]);

        return back()->with('success', "{$user->name} has been reactivated.");
    }
}
