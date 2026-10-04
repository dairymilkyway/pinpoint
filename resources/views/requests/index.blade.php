@extends('layouts.app')

@section('title', 'Requests')
@section('heading', 'Requests')

@section('content')
    {{-- The same screen for both roles, read from opposite ends. A reader opens
         it to empty a queue; a Customer opens it to watch their own proposals.
         The controller scopes the rows, so the copy is the only thing that has
         to know which one is looking.

         Rows are summaries and the proposal itself lives in a review modal: a
         request carries a dozen fields, and a table cell listing them all is a
         wall nobody reads. --}}
    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">
                    {{ $canDecide ? 'Awaiting your review' : 'Your requests' }}
                    @if ($pending->isNotEmpty())
                        &middot; {{ $pending->count() }} waiting
                    @endif
                </p>
                <h2 class="panel__question mb-0">
                    {{ $canDecide ? 'Clear the queue.' : 'Watch what you have asked for.' }}
                </h2>
            </div>

            @can('addresses.request')
                <div class="action-bar">
                    <a href="{{ route('requests.create', ['type' => 'create']) }}" class="btn btn-primary text-nowrap">
                        <i class="bi bi-plus-lg"></i> <span class="ms-1">Request a new address</span>
                    </a>
                </div>
            @endcan
        </div>

        <div class="panel__body panel__body--flush">
            @if ($pending->isEmpty())
                <div class="table-state">
                    {{-- A check, because a clear queue is the good outcome and reads as such. --}}
                    <i class="bi bi-check2-circle d-block mb-2" aria-hidden="true"></i>
                    <p class="mb-1">{{ $canDecide ? 'Nothing is waiting on you.' : 'You have no requests waiting on a decision.' }}</p>
                    <p class="mb-0 small">
                        {{ $canDecide
                            ? 'A request a Customer raises appears here for you to approve or reject.'
                            : 'Ask for a change from the address list and it appears here until it is decided.' }}
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Requested change</th>
                                <th>Requester</th>
                                <th>{{ $canDecide ? 'Waiting' : 'Submitted' }}</th>
                                <th class="text-end"><span class="visually-hidden">Decision</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $change)
                                @include('requests.partials.row', [
                                    'change' => $change,
                                    'canDecide' => $canDecide,
                                    'decided' => false,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($decided->isNotEmpty())
        <div class="panel mt-4">
            <div class="panel__head">
                <p class="eyebrow mb-0">Decided</p>
            </div>

            <div class="panel__body panel__body--flush">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Requested change</th>
                                <th>Requester</th>
                                <th>Decided</th>
                                <th class="text-end">Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($decided as $change)
                                @include('requests.partials.row', [
                                    'change' => $change,
                                    'canDecide' => $canDecide,
                                    'decided' => true,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Outside the table: a modal inside a table body is not valid markup.
         One pair per waiting request, so the decision is made with the values on
         screen rather than from memory of a row. The pair is two siblings, not
         one inside the other, so a refusal is confirmed without a modal stacked
         on a modal. --}}
    @foreach ($pending as $change)
        @include('requests.partials.review-modal', ['change' => $change, 'canDecide' => $canDecide])

        @if ($canDecide)
            @include('requests.partials.reject-modal', ['change' => $change])
        @endif
    @endforeach
@endsection
