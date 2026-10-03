@extends('layouts.app')

@section('title', 'Addresses')
@section('heading', 'Addresses')

@section('content')
    {{-- Reached by the roles that only ever see their own addresses - a reader of
         the whole book gets addresses.users instead. --}}
    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Your addresses</p>
                {{-- New address and Import live in the table's own toolbar rather
                     than here, so the panel does not carry the same button twice. --}}
                <p class="mb-0 text-dim small">The addresses on your account. Search and sort from here.</p>
            </div>
        </div>

        <div class="panel__body panel__body--flush skeleton-host">
            {{ $dataTable->table(['class' => 'table table-hover align-middle w-100']) }}
            <div class="skeleton-overlay skeleton-overlay--table" aria-hidden="true"></div>
        </div>
    </div>

    @include('addresses.partials.delete-modal')
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
