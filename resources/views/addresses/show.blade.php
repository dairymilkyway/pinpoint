@extends('layouts.app')

@section('title', $owner->name)
@section('heading', $owner->name)

@section('content')
    @php($role = $owner->getRoleNames()->first())

    <div class="panel mb-4">
        <div class="panel__head">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="avatar" aria-hidden="true">{{ $initials }}</span>

                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <p class="mb-0 fw-medium text-truncate">{{ $owner->name }}</p>
                        <span class="badge-amber">{{ $role ?? 'No role' }}</span>
                    </div>

                    <p class="mb-0 text-dim small text-truncate">
                        {{ $owner->email }}
                        @if ($owner->created_at)
                            &middot; joined {{ $owner->created_at->format('M j, Y') }}
                        @endif
                    </p>
                </div>
            </div>

            <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                <i class="bi bi-arrow-left"></i> <span class="ms-1">All users</span>
            </a>
        </div>
    </div>

    <div class="stat-grid">
        @foreach ($cards as $card)
            <div class="stat">
                <p class="stat__label">{{ $card['label'] }}</p>
                <p class="stat__value mb-1">{{ number_format($card['value']) }}</p>
                <p class="stat__hint mb-0">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="panel mb-4">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">By region</p>
                <p class="mb-0 text-dim small">
                    Addresses held by {{ $owner->name }}, ranked by how many sit in each region.
                </p>
            </div>
        </div>

        <div class="panel__body">
            {{-- Same figures-in-the-markup approach as the dashboard: no second
                 endpoint to authenticate and no second request to wait on. --}}
            <div class="chart skeleton-host is-loading"
                 data-region-chart
                 data-points="{{ json_encode($chart) }}">
                <canvas aria-hidden="true"></canvas>
                <div class="skeleton-overlay skeleton-overlay--chart" aria-hidden="true">
                    <div class="skeleton skeleton--row" style="width: 88%"></div>
                    <div class="skeleton skeleton--row mt-3" style="width: 74%"></div>
                    <div class="skeleton skeleton--row mt-3" style="width: 81%"></div>
                    <div class="skeleton skeleton--row mt-3" style="width: 62%"></div>
                </div>
            </div>

            {{-- A canvas reads as nothing to a screen reader, so the same figures
                 are repeated as text. --}}
            <ul class="visually-hidden">
                @foreach ($chart as $row)
                    <li>{{ $row['label'] }}: {{ $row['value'] }} {{ Str::plural('address', $row['value']) }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel h-100">
                <div class="panel__head">
                    <div>
                        <p class="eyebrow mb-1">Addresses</p>
                        <p class="mb-0 text-dim small">
                            Every address held by {{ $owner->name }}. Search, sort and export from here.
                        </p>
                    </div>
                </div>

                <div class="panel__body panel__body--flush skeleton-host">
                    {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
                    <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- The panel stretches to the table beside it and the map fills the
                 panel, so a table of any length leaves no dead space under the
                 map. Same host markup and same module as the dashboard. --}}
            <div class="panel panel--fill h-100">
                <div class="panel__head">
                    <div>
                        <p class="eyebrow mb-1">Pins</p>
                        {{-- On one line so the sentence renders contiguously, the
                             same reason the dashboard keeps it on one. --}}
                        <p class="mb-0 text-dim small">Their addresses, pinned. {{ number_format($coverage['pinned']) }} of {{ number_format($coverage['total']) }} addresses have coordinates. Tiles by OpenStreetMap.</p>
                    </div>
                </div>

                <div class="panel__body panel__body--flush">
                    <div class="map is-loading"
                         data-address-map
                         data-points="{{ json_encode($pins) }}">
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

    @include('addresses.partials.delete-modal')
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
