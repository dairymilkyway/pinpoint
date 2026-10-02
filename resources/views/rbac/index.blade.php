@extends('layouts.app')

@section('title', 'Roles & permissions')
@section('heading', 'Roles & permissions')

@section('content')
    <div class="panel mb-4">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Permission matrix</p>
                <p class="mb-0 text-dim small">
                    Check a box to grant a permission to a role, then save. Changes take effect on the next request.
                    <code class="mono">{{ $managePermission }}</code> is always kept on the {{ $adminRole }} role.
                </p>
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
                        @foreach ($permissions as $permission)
                            <tr>
                                <td>
                                    <span class="matrix__perm">{{ $permission->name }}</span>
                                    @if ($permission->name === $managePermission)
                                        <span class="badge badge-amber ms-2">locked to {{ $adminRole }}</span>
                                    @endif
                                </td>
                                @foreach ($roles as $role)
                                    @php($granted = $role->permissions->contains('name', $permission->name))
                                    <td>
                                        <input type="checkbox" class="form-check-input"
                                               name="permissions[{{ $role->name }}][]"
                                               value="{{ $permission->name }}"
                                               @checked($granted)
                                               aria-label="{{ $permission->name }} for {{ $role->name }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top d-flex justify-content-end" style="border-color: var(--line) !important;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2"></i> <span class="ms-1">Save permissions</span>
                </button>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">User roles</p>
                <p class="mb-0 text-dim small">Assign one role per person. A role carries its permissions with it.</p>
            </div>
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
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="app-avatar">{{ Str::of($user->name)->substr(0, 2)->upper() }}</span>
                                    <span>{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="text-dim mono" style="font-size: 0.8125rem;">{{ $user->email }}</td>
                            <td>
                                <form method="POST" action="{{ route('rbac.users.role', $user) }}" class="d-flex gap-2">
                                    @csrf
                                    <label class="visually-hidden" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                    <select name="role" id="role-{{ $user->id }}" class="form-select form-select-sm">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->name }}"
                                                @selected($user->hasRole($role->name))>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Update</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
