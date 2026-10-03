@extends('layouts.app')

@section('title', 'Addresses')
@section('heading', 'Addresses')

@section('content')
    {{-- The front door for a reader of the whole book: the users, not the
         addresses. Each row opens the addresses that user holds. --}}
    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Users</p>
                <p class="mb-0 text-dim small">Everyone who holds addresses. Open a user to see the ones they hold.</p>
            </div>

            @can('addresses.create')
                {{-- The one screen in this section with no addresses table, so
                     it has no toolbar to carry these two and keeps its own
                     copies. Import is here because this is the front door a
                     reader arriving with a spreadsheet starts from. --}}
                <div class="d-flex gap-2">
                    <a href="{{ route('addresses.import.create') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                        <i class="bi bi-upload"></i> <span class="ms-1">Import Excel</span>
                    </a>
                    <a href="{{ route('addresses.create') }}" class="btn btn-primary btn-sm text-nowrap">
                        <i class="bi bi-plus-lg"></i> <span class="ms-1">New address</span>
                    </a>
                </div>
            @endcan
        </div>

        <div class="panel__body panel__body--flush skeleton-host">
            {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
            <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
