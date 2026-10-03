@extends('layouts.app')

@section('title', 'New address')
@section('heading', 'New address')

@section('content')
    <div class="panel" style="max-width: 40rem;">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Create</p>
                <p class="mb-0 text-dim small">
                    The managing roles hold no addresses of their own, so the first
                    thing to settle is whose this one is.
                </p>
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
                        <div class="form-hint">
                            The next screen builds the address for this account. The
                            managing roles are not listed because they hold none.
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-3 border-top" style="border-color: var(--line) !important;">
                        <button type="submit" class="btn btn-primary px-4">Continue</button>
                        <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
