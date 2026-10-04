@extends('layouts.app')

@php($adding = $type === App\Models\AddressRequest::TYPE_CREATE)

@section('title', $adding ? 'Request a new address' : 'Request an edit')
@section('heading', $adding ? 'Request a new address' : 'Request an edit')

@section('content')
    <div class="panel panel--reading">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Change request</p>
                <p class="mb-0 text-dim small">
                    An administrator reviews this before anything is saved. Nothing here
                    changes the list until it is approved.
                </p>
            </div>
        </div>

        <div class="panel__body">
            @include('addresses.partials.form', [
                'address' => $address,
                'action' => route('requests.store'),
                'method' => 'POST',
                'submitLabel' => 'Submit request',
                // What makes this a proposal rather than a write: the type, and
                // the address it is about. Empty for an addition, which has none
                // yet - the row is created if and when it is approved.
                'hidden' => [
                    'type' => $type,
                    'address_id' => $address->id,
                ],
                // A default marker is not something to ask permission for.
                'showDefault' => false,
                'withNote' => true,
                'cancelRoute' => route('requests.index'),
            ])
        </div>
    </div>
@endsection
