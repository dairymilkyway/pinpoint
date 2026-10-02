@extends('layouts.app')

@section('title', 'New address')
@section('heading', 'New address')

@section('content')
    <div class="panel" style="max-width: 60rem;">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Create</p>
                <p class="mb-0 text-dim small">Fields marked with an asterisk are required.</p>
            </div>
        </div>

        <div class="panel__body">
            @include('addresses.partials.form', [
                'address' => new App\Models\Address,
                'action' => route('addresses.store'),
                'method' => 'POST',
                'submitLabel' => 'Create address',
            ])
        </div>
    </div>
@endsection
