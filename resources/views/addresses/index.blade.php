@extends('layouts.app')

@section('title', 'Addresses')
@section('heading', 'Addresses')

@section('content')
    {{-- Reached by the roles that only ever see their own addresses - a reader of
         the whole book gets addresses.users instead. --}}
    <div class="panel">
        <div class="panel__head">
            <p class="eyebrow mb-0">Your addresses</p>
        </div>

        {{-- First run and a search that matched nothing are different screens. The
             copy travels on the host so the one table module can word both. --}}
        <div class="panel__body panel__body--flush skeleton-host"
             data-table-empty="Nothing on your account yet. Add your first address to see it here."
             data-table-zero="No addresses match that search. Clear the search to see them all.">
            {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
            <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
            {{-- Shown to sighted readers by a display change, which assistive tech
                 does not reliably announce. The live region below is always
                 rendered and takes the same words when a fetch fails. --}}
            <div class="table-state table-state--failed">
                <p class="mb-1">Could not load your addresses.</p>
                <p class="mb-0 text-dim small">Check your connection, then refresh the page to try again.</p>
            </div>
            <div class="visually-hidden" data-table-alert role="alert" aria-atomic="true"></div>
        </div>
    </div>

    @include('addresses.partials.delete-modal')
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
