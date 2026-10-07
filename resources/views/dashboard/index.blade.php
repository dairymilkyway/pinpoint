@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @php($user = auth()->user())
    @php($role = $user->getRoleNames()->first())
    @php($seesEveryAddress = $user->seesEveryAddress())

    {{-- 1. Identity and actions. The shell header already names who you are, so
         this row is the one place the dashboard offers a way into the work. --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <p class="eyebrow mb-0">Signed in as {{ $role ?? 'no role' }}</p>

        @if ($mayCreate)
            <div class="action-bar">
                <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary text-nowrap">
                    <i class="bi bi-table"></i> <span class="ms-1">Open addresses</span>
                </a>
                <a href="{{ route('addresses.create') }}" class="btn btn-primary text-nowrap">
                    <i class="bi bi-plus-lg"></i> <span class="ms-1">New address</span>
                </a>
            </div>
        @elseif ($mayReadDirectory)
            <div class="action-bar">
                <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary text-nowrap">
                    <i class="bi bi-table"></i> <span class="ms-1">Open addresses</span>
                </a>
            </div>
        @endif
    </div>

    @if (! $mayReadDirectory)
        {{-- Registration now grants Customer, so this is only the honest state for
             an account whose role was revoked - it renders instead of a 403. --}}
        <div class="panel">
            <div class="panel__body">
                <p class="mb-2">You are signed in, but nothing has been shared with you yet.</p>
                <p class="mb-0 text-dim small">
                    An administrator needs to assign your account a role before the list
                    becomes visible. Once that happens this page fills in automatically.
                </p>
            </div>
        </div>
    @else
        {{-- 2. The metric band. Unequal by weight on purpose: addresses lead, the
             rest support, so the band does not assert a false equality of
             importance. A value never appears without its label. --}}
        <div class="metric-band mb-4" role="group" aria-label="Address figures">
            @foreach ($cards as $card)
                <div class="metric {{ $loop->first ? 'metric--lead' : '' }}">
                    <p class="metric__label">{{ $card['label'] }}</p>
                    <div class="metric__row">
                        <span class="metric__value">{{ number_format($card['value']) }}</span>
                        <span class="metric__unit">{{ $card['hint'] }}</span>
                    </div>
                    @if (isset($card['trend']))
                        <div class="metric__spark" data-metric-spark data-points="{{ json_encode($card['trend']) }}">
                            <canvas aria-hidden="true"></canvas>
                        </div>
                        <p class="metric__spark-caption">Last 12 weeks</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($access)
            {{-- Superadmin only: the same permission that opens /rbac gates this. --}}
            <div class="panel mb-4">
                <div class="panel__head">
                    <p class="eyebrow mb-0">Access</p>

                    <div class="action-bar">
                        <a href="{{ route('rbac.index') }}" class="btn btn-outline-secondary text-nowrap">
                            <i class="bi bi-shield-lock"></i> <span class="ms-1">Manage roles</span>
                        </a>
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
                </div>
            </div>
        @endif

        {{-- 3. What is waiting on a person: the only items on the page that need a
             decision. A Customer sees the requests they have raised; a reader sees
             the queue they clear. --}}
        @if ($mayRaiseRequests)
            <div class="panel mb-4">
                <div class="panel__head">
                    <div>
                        <p class="eyebrow mb-1">Your requests</p>
                        @if ($waitingOnADecision > 0)
                            <p class="mb-0 text-dim small">{{ $waitingOnADecision }} waiting on a decision.</p>
                        @endif
                    </div>

                    <div class="action-bar">
                        <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary text-nowrap">
                            Open requests
                        </a>
                    </div>
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
        @elseif (auth()->user()->can('viewAny', \App\Models\AddressRequest::class))
            <div class="panel mb-4">
                <div class="panel__head">
                    <p class="eyebrow mb-0">Requests</p>

                    <div class="action-bar">
                        <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary text-nowrap">
                            Open the queue
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- 4. Distribution and the map. The reader's chart answers where the book
             is concentrated; the map is a supporting view for every role. --}}
        <div class="row g-4">
            @if ($chart !== null)
                <div class="col-lg-7">
                    <div class="panel h-100">
                        <div class="panel__head">
                            <div>
                                <p class="eyebrow mb-1">By region</p>
                                <h2 class="panel__question mb-0">Where are the addresses concentrated?</h2>
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
                </div>
            @endif

            <div class="{{ $chart !== null ? 'col-lg-5' : 'col-12' }}">
                <div class="panel panel--fill h-100">
                    <div class="panel__head">
                        <div>
                            {{-- A directory-wide reader pins the whole book, so
                                 the panel is named for what is on it rather than
                                 for the person reading it. --}}
                            <p class="eyebrow mb-1">{{ $seesEveryAddress ? 'User locations' : 'Your locations' }}</p>
                            <p class="mb-0 text-dim small">
                                {{ $seesEveryAddress ? 'Every address on file' : 'Your own addresses' }}, mapped.
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
