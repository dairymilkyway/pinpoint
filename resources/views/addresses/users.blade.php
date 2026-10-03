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
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
