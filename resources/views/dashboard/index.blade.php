@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @php($user = auth()->user())
    @php($role = $user->getRoleNames()->first())

    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Signed in as {{ $role ?? 'no role' }}</p>
                <p class="mb-0 text-dim small">
                    @if ($mayReadDirectory)
                        A summary of the directory. The panels below reflect what your role can reach.
                    @else
                        Your account does not hold any directory permissions yet.
                    @endif
                </p>
            </div>

            @if ($mayCreate)
                <div class="d-flex gap-2">
                    <a href="{{ route('addresses.create') }}" class="btn btn-primary btn-sm text-nowrap">
                        <i class="bi bi-plus-lg"></i> <span class="ms-1">New address</span>
                    </a>
                    <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                        <i class="bi bi-table"></i> <span class="ms-1">Open directory</span>
                    </a>
                </div>
            @elseif ($mayReadDirectory)
                <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                    <i class="bi bi-table"></i> <span class="ms-1">Open directory</span>
                </a>
            @endif
        </div>
    </div>

    @if (! $mayReadDirectory)
        {{-- Registration now grants Viewer, so this is only the honest state for
             an account whose role was revoked - it renders instead of a 403. --}}
        <div class="panel">
            <div class="panel__body">
                <p class="mb-2">You are signed in, but nothing has been shared with you yet.</p>
                <p class="mb-0 text-dim small">
                    An administrator needs to assign your account a role before the directory
                    becomes visible. Once that happens this page fills in automatically.
                </p>
            </div>
        </div>
    @else
        <div class="stat-grid">
            @foreach ($cards as $card)
                <div class="stat">
                    <p class="stat__label">{{ $card['label'] }}</p>
                    <p class="stat__value mb-1">{{ number_format($card['value']) }}</p>
                    <p class="stat__hint mb-0">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="panel h-100">
                    <div class="panel__head">
                        <div>
                            <p class="eyebrow mb-1">Recent</p>
                            <p class="mb-0 text-dim small">The six most recently added addresses.</p>
                        </div>
                    </div>

                    <div class="panel__body panel__body--flush">
                        @forelse ($recent as $address)
                            <div class="recent-row">
                                <div class="min-w-0">
                                    <p class="mb-0 text-truncate">
                                        <span class="fw-medium">{{ $address->label }}</span>
                                        @if ($address->is_default)
                                            <span class="badge-amber ms-1">Default</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 text-dim small text-truncate">
                                        {{ $address->line1 }} &middot;
                                        {{ collect([$address->city, $address->state])->filter()->join(', ') }}
                                    </p>
                                </div>

                                <span class="text-faint small text-nowrap">{{ $address->user?->name }}</span>
                            </div>
                        @empty
                            <p class="panel__body text-dim mb-0">No addresses on file yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="panel h-100">
                    <div class="panel__head">
                        <div>
                            <p class="eyebrow mb-1">Map coverage</p>
                            <p class="mb-0 text-dim small">Addresses that carry coordinates and can be pinned.</p>
                        </div>
                    </div>

                    <div class="panel__body">
                        <p class="stat__value mb-2">{{ $coverage['percent'] }}%</p>

                        <div class="meter" role="img"
                             aria-label="{{ $coverage['pinned'] }} of {{ $coverage['total'] }} addresses are pinned">
                            <span class="meter__fill" style="width: {{ $coverage['percent'] }}%"></span>
                        </div>

                        {{-- Kept on one line so the sentence renders contiguously. --}}
                        <p class="mb-0 mt-3 text-dim small">{{ number_format($coverage['pinned']) }} of {{ number_format($coverage['total']) }} addresses have coordinates.</p>
                    </div>
                </div>

                @if ($access)
                    {{-- Admin only: the same permission that opens /rbac gates this. --}}
                    <div class="panel mt-4">
                        <div class="panel__head">
                            <div>
                                <p class="eyebrow mb-1">Access</p>
                                <p class="mb-0 text-dim small">Everything the permission system currently holds.</p>
                            </div>
                        </div>

                        <div class="panel__body">
                            <div class="access-row">
                                <span class="text-dim small">Users</span>
                                <span class="fw-medium">{{ number_format($access['users']) }}</span>
                            </div>
                            <div class="access-row">
                                <span class="text-dim small">Roles</span>
                                <span class="fw-medium">{{ number_format($access['roles']) }}</span>
                            </div>
                            <div class="access-row">
                                <span class="text-dim small">Permissions</span>
                                <span class="fw-medium">{{ number_format($access['permissions']) }}</span>
                            </div>

                            <a href="{{ route('rbac.index') }}" class="btn btn-outline-secondary btn-sm mt-3">
                                <i class="bi bi-shield-lock"></i> <span class="ms-1">Manage roles</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($mayMap)
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <p class="eyebrow mb-1">Your locations</p>
                        <p class="mb-0 text-dim small">
                            Pinned from the coordinates on your own addresses. Tiles by OpenStreetMap.
                        </p>
                    </div>
                </div>

                <div class="panel__body panel__body--flush">
                    {{-- Same host markup and same lazily imported module as the
                         directory map, so there is one map implementation. --}}
                    <div class="map is-loading" data-address-map data-url="{{ route('home.map') }}">
                        <div class="skeleton-overlay skeleton-overlay--map" aria-hidden="true">
                            <div class="skeleton skeleton--title" style="width: 30%"></div>
                            <div class="skeleton skeleton--row mt-3" style="width: 85%"></div>
                            <div class="skeleton skeleton--row mt-2" style="width: 62%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection
