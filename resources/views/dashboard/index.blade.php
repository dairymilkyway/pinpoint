@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @php($user = auth()->user())
    @php($role = $user->getRoleNames()->first())
    @php($seesEveryAddress = $user->seesEveryAddress())

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
        {{-- Registration now grants Customer, so this is only the honest state for
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

        @if ($access)
            {{-- Superadmin only: the same permission that opens /rbac gates this.
                 Directly under the figures rather than at the foot of the page,
                 because the one account that sees it is the one whose job starts
                 with the roles, and it was previously below a full-height map. --}}
            <div class="panel mb-4">
                <div class="panel__head">
                    <div>
                        <p class="eyebrow mb-1">Access</p>
                        <p class="mb-0 text-dim small">Everything the permission system currently holds.</p>
                    </div>

                    <a href="{{ route('rbac.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                        <i class="bi bi-shield-lock"></i> <span class="ms-1">Manage roles</span>
                    </a>
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
                </div>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                @if ($chart !== null)
                    {{-- Whoever reads the whole directory. The Customer falls
                         through to the Recent list below, which says more about
                         eight addresses than a chart of where those eight sit.
                         Tested against null rather than truthiness: a reader
                         with nothing on file gets an empty array, and that
                         should still be the chart panel, not Recent. --}}
                    <div class="panel h-100">
                        <div class="panel__head">
                            <div>
                                <p class="eyebrow mb-1">By region</p>
                                <p class="mb-0 text-dim small">
                                    {{ $seesEveryAddress ? 'Every address on file' : 'Your own addresses' }},
                                    ranked by how many sit in each region.
                                </p>
                            </div>
                        </div>

                        <div class="panel__body">
                            {{-- The figures are small enough to travel in the
                                 markup, so there is no second endpoint to
                                 authenticate and no second request to wait on. --}}
                            <div class="chart skeleton-host is-loading"
                                 data-region-chart
                                 data-points="{{ json_encode($chart) }}">
                                <canvas aria-hidden="true"></canvas>
                                <div class="skeleton-overlay skeleton-overlay--chart" aria-hidden="true">
                                    <div class="skeleton skeleton--row" style="width: 88%"></div>
                                    <div class="skeleton skeleton--row mt-3" style="width: 74%"></div>
                                    <div class="skeleton skeleton--row mt-3" style="width: 81%"></div>
                                    <div class="skeleton skeleton--row mt-3" style="width: 62%"></div>
                                    <div class="skeleton skeleton--row mt-3" style="width: 70%"></div>
                                </div>
                            </div>

                            {{-- A canvas reads as nothing to a screen reader, so
                                 the same figures are repeated as text. --}}
                            <ul class="visually-hidden">
                                @foreach ($chart as $row)
                                    <li>{{ $row['label'] }}: {{ $row['value'] }} {{ Str::plural('address', $row['value']) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @else
                    {{-- The Customer's column. Was the six most recent addresses,
                         which said nothing the address table does not already say;
                         asking an administrator for a change is the one thing this
                         role does that no other screen on the dashboard mentions. --}}
                    <div class="panel h-100">
                        <div class="panel__head">
                            <div>
                                <p class="eyebrow mb-1">Your requests</p>
                                <p class="mb-0 text-dim small">
                                    @if ($waitingOnADecision > 0)
                                        {{ $waitingOnADecision }} waiting on a decision.
                                    @else
                                        What you have asked an administrator to change.
                                    @endif
                                </p>
                            </div>

                            <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                                Open requests
                            </a>
                        </div>

                        <div class="panel__body panel__body--flush">
                            @forelse ($myRequests as $change)
                                <div class="recent-row">
                                    <div class="min-w-0">
                                        <p class="mb-0 text-truncate">
                                            <span class="badge badge-soft">{{ Str::headline($change->type) }}</span>
                                            <span class="fw-medium ms-1">{{ $change->subjectLabel() }}</span>
                                        </p>
                                        <p class="mb-0 text-dim small text-truncate">
                                            @if ($change->isPending())
                                                Waiting since {{ $change->created_at->diffForHumans() }}
                                            @else
                                                {{ Str::headline($change->status) }}
                                                {{ $change->decided_at?->diffForHumans() }}
                                                @if ($change->decision_note)
                                                    &middot; &ldquo;{{ Str::limit($change->decision_note, 60) }}&rdquo;
                                                @endif
                                            @endif
                                        </p>
                                    </div>

                                    <span class="text-faint small text-nowrap">
                                        {{ $change->decider?->name ?? $change->user?->name }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-dim small p-4 mb-0">
                                    You have not asked for a change yet.
                                </p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="panel h-100">
                    <div class="panel__head">
                        <div>
                            {{-- A directory-wide reader pins the whole book, so
                                 the panel is named for what is on it rather than
                                 for the person reading it. --}}
                            <p class="eyebrow mb-1">{{ $seesEveryAddress ? 'User locations' : 'Your locations' }}</p>
                            {{-- The map shows which addresses are pinned; this line
                                 is the one fact it cannot: how many are not. Some
                                 of the gap is drawn anyway, from the province or
                                 region centre, so the second sentence is what stops
                                 the stand-ins reading as positions. It says "where
                                 one is known" rather than claiming all of them:
                                 a free-text address, or one whose city is placed
                                 but whose own coordinates are missing, has nothing
                                 to borrow from and stays off the map. --}}
                            <p class="mb-0 text-dim small">
                                {{ $seesEveryAddress ? 'Every address on file' : 'Your own addresses' }}, pinned.
                                {{ number_format($coverage['pinned']) }} of {{ number_format($coverage['total']) }} addresses have coordinates.
                                @if ($coverage['pinned'] < $coverage['total'])
                                    The rest are drawn at the centre of their province or region where one is known, and marked as approximate.
                                @endif
                                Tiles by OpenStreetMap.
                            </p>
                        </div>
                    </div>

                    <div class="panel__body panel__body--flush">
                        {{-- Same host markup and same lazily imported module as the
                             directory map, so there is one map implementation. The
                             module renders its own "nothing to pin" state when the
                             response is empty, so there is no branch here. --}}
                        <div class="map is-loading" data-address-map data-url="{{ route('home.map') }}">
                            <div class="skeleton-overlay skeleton-overlay--map" aria-hidden="true">
                                <div class="skeleton skeleton--title" style="width: 30%"></div>
                                <div class="skeleton skeleton--row mt-3" style="width: 85%"></div>
                                <div class="skeleton skeleton--row mt-2" style="width: 62%"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @endif
@endsection
