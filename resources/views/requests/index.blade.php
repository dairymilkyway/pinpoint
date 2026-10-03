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
                <p class="mb-0 text-dim small">
                    @if ($canDecide)
                        Changes a Customer has asked for. Nothing reaches the directory until
                        one of them is approved.
                    @else
                        Changes you have asked for. An administrator reviews each one before it
                        reaches the directory.
                    @endif
                </p>
            </div>

            @can('addresses.request')
                <a href="{{ route('requests.create', ['type' => 'create']) }}" class="btn btn-primary btn-sm text-nowrap">
                    <i class="bi bi-plus-lg"></i> <span class="ms-1">Request a new address</span>
                </a>
            @endcan
        </div>

        <div class="panel__body panel__body--flush">
            @if ($pending->isEmpty())
                <p class="text-dim small p-4 mb-0">
                    {{ $canDecide ? 'Nothing is waiting on you.' : 'You have no requests waiting on a decision.' }}
                </p>
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
                <div>
                    <p class="eyebrow mb-1">Decided</p>
                    <p class="mb-0 text-dim small">The last twenty settled requests, most recent first.</p>
                </div>
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
