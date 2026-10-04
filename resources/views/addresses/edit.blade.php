@extends('layouts.app')

@section('title', 'Edit address')
@section('heading', 'Edit address')

@section('content')
    <div class="panel panel--reading">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Editing</p>
                <p class="mb-0 text-dim small">{{ $address->label }}</p>
            </div>
        </div>

        <div class="panel__body">
            @include('addresses.partials.form', [
                'address' => $address,
                'action' => route('addresses.update', $address),
                'method' => 'PUT',
                'submitLabel' => 'Save changes',
            ])
        </div>
    </div>
@endsection
