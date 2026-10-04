@extends('layouts.app')

@section('title', 'Address owners')
@section('heading', 'Address owners')

@section('content')
    {{-- The front door for a reader of the whole book: the users, not the
         addresses. Each row opens the addresses that user holds. --}}
    <div class="panel">
        <div class="panel__head">
            <p class="eyebrow mb-0">Users</p>

            @can('addresses.create')
                {{-- The one screen in this section with no addresses table, so
                     it has no toolbar to carry these two and keeps its own
                     copies. Import is here because this is the front door a
                     reader arriving with a spreadsheet starts from. --}}
                <div class="action-bar">
                    <a href="{{ route('addresses.import.create') }}" class="btn btn-outline-secondary text-nowrap">
                        <i class="bi bi-upload"></i> <span class="ms-1">Import Excel</span>
                    </a>
                    <a href="{{ route('addresses.create') }}" class="btn btn-primary text-nowrap">
                        <i class="bi bi-plus-lg"></i> <span class="ms-1">New address</span>
                    </a>
                </div>
            @endcan
        </div>

        {{-- First run and a search that matched nothing are different screens. The
             copy travels on the host so the one table module can word both. --}}
        <div class="panel__body panel__body--flush skeleton-host"
             data-table-empty="No account holds an address yet. Import a spreadsheet or add an address to start."
             data-table-zero="No accounts match that search. Clear the search to see them all.">
            {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
            <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
            {{-- Shown to sighted readers by a display change, which assistive tech
                 does not reliably announce. The live region below is always
                 rendered and takes the same words when a fetch fails. --}}
            <div class="table-state table-state--failed">
                <p class="mb-1">Could not load the user list.</p>
                <p class="mb-0 text-dim small">Check your connection, then refresh the page to try again.</p>
            </div>
            <div class="visually-hidden" data-table-alert role="alert" aria-atomic="true"></div>
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
