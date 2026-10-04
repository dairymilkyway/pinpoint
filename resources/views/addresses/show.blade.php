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

            <div class="action-bar">
                <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary text-nowrap">
                    <i class="bi bi-arrow-left"></i> <span class="ms-1">All users</span>
                </a>
            </div>
        </div>
    </div>

    {{-- The account's figures as a metric band, the same component the dashboard
         uses. These describe the account rather than decide anything, so they sit
         at the supporting weight below the table. --}}
    <div class="metric-band mb-4" role="group" aria-label="Figures for {{ $owner->name }}">
        @foreach ($cards as $card)
            <div class="metric {{ $loop->first ? 'metric--lead' : '' }}">
                <p class="metric__label">{{ $card['label'] }}</p>
                <div class="metric__row">
                    <span class="metric__value">{{ number_format($card['value']) }}</span>
                    <span class="metric__unit">{{ $card['hint'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="panel mb-4">
        <div class="panel__head">
            <p class="eyebrow mb-0">By region</p>
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
                    <p class="eyebrow mb-0">Addresses</p>
                </div>

                {{-- First run and a search that matched nothing are different
                     screens. The copy travels on the host so the one table
                     module can word both. --}}
                <div class="panel__body panel__body--flush skeleton-host"
                     data-table-empty="This account holds no addresses yet."
                     data-table-zero="No addresses match that search. Clear the search to see them all.">
                    {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
                    <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
                    {{-- Shown to sighted readers by a display change, which assistive
                         tech does not reliably announce. The live region below is
                         always rendered and takes the same words when a fetch fails. --}}
                    <div class="table-state table-state--failed">
                        <p class="mb-1">Could not load the addresses.</p>
                        <p class="mb-0 text-dim small">Check your connection, then refresh the page to try again.</p>
                    </div>
                    <div class="visually-hidden" data-table-alert role="alert" aria-atomic="true"></div>
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
                        <p class="mb-0 text-dim small">{{ number_format($coverage['pinned']) }} of {{ number_format($coverage['total']) }} addresses have coordinates. Tiles by OpenStreetMap.</p>
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
