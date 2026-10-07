@extends('layouts.app')

@section('title', 'Roles & permissions')
@section('heading', 'Roles & permissions')

@section('content')
    <div class="panel mb-4">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Permission matrix</p>
                <h2 class="panel__question mb-0">Grant the least authority that works.</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('rbac.permissions') }}">
            @csrf

            <div class="table-responsive">
                <table class="matrix">
                    <thead>
                        <tr>
                            <th>Permission</th>
                            @foreach ($roles as $role)
                                <th>{{ $role->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modules as $module => $permissionNames)
                            <tr class="matrix__group">
                                <th scope="colgroup" colspan="{{ $roles->count() + 1 }}">{{ $module }}</th>
                            </tr>
                        @foreach ($permissionNames as $permissionName)
                            @php($permission = (object) ['name' => $permissionName])
                            <tr>
                                <td>
                                    {{-- The label is what a reader decides on; the machine
                                         name beneath it is what they need when matching the
                                         row to a seeder constant or a denied policy check. --}}
                                    <span class="matrix__perm">
                                        {{ \App\Rbac::label($permission->name) }}
                                        @if ($permission->name === $managePermission)
                                            <span class="badge badge-amber ms-2">locked to {{ $superadminRole }}</span>
                                        @endif
                                    </span>
                                    <code class="matrix__perm__code">{{ $permission->name }}</code>
                                </td>
                                @foreach ($roles as $role)
                                    @php($granted = $role->permissions->contains('name', $permission->name))
                                    {{-- Locked on every column, not only the Superadmin's.
                                         Ticking it elsewhere looked like it had worked and
                                         handed that role the page it is standing on, from
                                         where it could grant itself everything else. It
                                         reads checked for the Superadmin and unchecked
                                         everywhere else, which is the state the controller
                                         enforces on save. A disabled input is not
                                         submitted, which is right in both directions: the
                                         controller drops it from the roles that must not
                                         hold it and re-adds it to the one that must. --}}
                                    @php($locked = $permission->name === $managePermission)
                                    @php($checked = $locked ? $role->name === $superadminRole : $granted)
                                    <td>
                                        <input type="checkbox" class="form-check-input"
                                               name="permissions[{{ $role->name }}][]"
                                               value="{{ $permission->name }}"
                                               @checked($checked)
                                               @disabled($locked)
                                               aria-label="{{ \App\Rbac::label($permission->name) }} for {{ $role->name }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="action-bar action-bar--end p-3 border-top" style="border-color: var(--line) !important;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2"></i> <span class="ms-1">Save permissions</span>
                </button>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel__head">
            <p class="eyebrow mb-0">User roles</p>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th style="width: 320px;">Role</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php($deactivated = $user->trashed())
                        <tr @class(['is-deactivated' => $deactivated])>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="app-avatar">{{ Str::of($user->name)->substr(0, 2)->upper() }}</span>
                                    <span>{{ $user->name }}</span>
                                    @if ($deactivated)
                                        <span class="badge badge-soft">Deactivated</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-dim mono" style="font-size: 0.8125rem;">{{ $user->email }}</td>
                            <td>
                                @if ($user->hasRole($superadminRole))
                                    {{-- Not reassignable, and the controller refuses it
                                         too: demoting the last Superadmin would leave
                                         nobody able to reach this page, with no way back
                                         from inside the app. --}}
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge badge-amber">{{ $superadminRole }}</span>
                                        <span class="text-dim small">Locked to this account.</span>
                                    </div>
                                @else
                                    {{-- Three controls, one line. None submit on click: each opens a confirmation
                                         dialog declared in rbac/partials/confirm-modals.blade.php, where the row's
                                         action and name are handed over. The role form stays because the dialog
                                         reads the selected role out of its select; Reactivate is unconfirmed on
                                         purpose - it is the undo direction. The select is constrained in
                                         app.scss (.rbac-actions) so Bootstrap's .form-select { width: 100% } does
                                         not claim the flex line and push the buttons under it. --}}
                                    <div class="action-bar rbac-actions">
                                        <form method="POST" action="{{ route('rbac.users.role', $user) }}">
                                            @csrf
                                            <label class="visually-hidden" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                            <select name="role" id="role-{{ $user->id }}" class="form-select">
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->name }}"
                                                        @selected($user->hasRole($role->name))>
                                                        {{ $role->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-outline-secondary text-nowrap"
                                                    data-bs-toggle="modal" data-bs-target="#rbacRoleModal"
                                                    data-row-action="{{ route('rbac.users.role', $user) }}"
                                                    data-row-label="{{ $user->name }}"
                                                    data-value-source="select[name=role]">Update</button>
                                        </form>

                                        @if ($deactivated)
                                            <form method="POST" action="{{ route('rbac.users.reactivate', $user) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary text-nowrap">Reactivate</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-danger text-nowrap"
                                                    data-bs-toggle="modal" data-bs-target="#rbacDeactivateModal"
                                                    data-row-action="{{ route('rbac.users.deactivate', $user) }}"
                                                    data-row-label="{{ $user->name }}">Deactivate</button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @include('rbac.partials.confirm-modals')
@endsection
