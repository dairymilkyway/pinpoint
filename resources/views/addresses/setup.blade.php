@extends('layouts.app')

@section('title', 'Choose an owner')
@section('heading', 'New address')

@section('content')
    <div class="panel panel--narrow">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Create</p>
                <h2 class="panel__question mb-0">Whose address is this?</h2>
            </div>
        </div>

        <div class="panel__body">
            @if ($accounts->isEmpty())
                <p class="mb-0 text-dim">
                    There are no accounts to hold an address yet. Create a Customer
                    first, then come back.
                </p>
            @else
                {{-- A plain GET form. Confirming carries the account in the query
                     string, which is how the import page is opened too, and there
                     is nothing to create here - so there is nothing to post. --}}
                <form method="GET" action="{{ route('addresses.create') }}">
                    <div class="mb-4">
                        <label for="user" class="form-label">Whose address is this? <span class="text-dim">*</span></label>
                        <select name="user" id="user" required class="form-select">
                            <option value="">Choose an account&hellip;</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->name }} ({{ $account->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="action-bar pt-3 border-top" style="border-color: var(--line) !important;">
                        <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Continue</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
