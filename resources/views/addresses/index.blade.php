@extends('layouts.app')

@section('title', 'Addresses')
@section('heading', 'Addresses')

@section('content')
    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Directory</p>
                <p class="mb-0 text-dim small">Every address you have access to. Search, sort and export from here.</p>
            </div>

            @can('addresses.create')
                <a href="{{ route('addresses.create') }}" class="btn btn-primary btn-sm text-nowrap">
                    <i class="bi bi-plus-lg"></i> <span class="ms-1">New address</span>
                </a>
            @endcan
        </div>

        <div class="panel__body panel__body--flush skeleton-host">
            {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
            <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Map</p>
                <p class="mb-0 text-dim small">Pinned from the coordinates on file. Tiles by OpenStreetMap.</p>
            </div>
        </div>

        <div class="panel__body panel__body--flush">
            {{-- is-loading is set here rather than by JS so the shimmer is up
                 before the lazily imported Leaflet chunk has even arrived. --}}
            <div class="map is-loading" data-address-map data-url="{{ route('addresses.map') }}">
                <div class="skeleton-overlay skeleton-overlay--map" aria-hidden="true">
                    <div class="skeleton skeleton--title" style="width: 30%"></div>
                    <div class="skeleton skeleton--row mt-3" style="width: 85%"></div>
                    <div class="skeleton skeleton--row mt-2" style="width: 62%"></div>
                </div>
            </div>
        </div>
    </div>

    @include('addresses.partials.delete-modal')
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
