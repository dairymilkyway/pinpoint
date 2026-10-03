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
            {{-- Named outright, because the address is not the reader's own. They
                 hold none, so the account it will belong to is the one thing on
                 this form that is already settled - and it is carried in the
                 action rather than in a field, so it cannot be edited here. --}}
            <p class="mb-3 text-dim small">
                Adding to <strong class="text-body">{{ $owner->name }}</strong>
            </p>

            @include('addresses.partials.form', [
                'address' => $address,
                'action' => route('addresses.store', ['user' => $owner->id]),
                'method' => 'POST',
                'submitLabel' => 'Create address',
            ])
        </div>
    </div>
@endsection
