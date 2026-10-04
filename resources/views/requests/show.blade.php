@extends('layouts.app')

@section('title', 'Request')
@section('heading', 'Request')

@section('content')
    @php
        // One request, read-only: the queue owns the decision controls. The
        // proposal lives in the review modal on the queue, but a notification
        // lands here, so the values are shown on the page instead.

        $isAddition = $change->isAddition();
        $isImport = $change->type === App\Models\AddressRequest::TYPE_IMPORT;
        $count = $change->rowCount();
        $decided = ! $change->isPending();

        // An edit is shown against what the address said when the request was
        // raised, and only in the fields that actually move. Listing twelve
        // unchanged fields buries the two that matter.
        $fields = collect($change->payload ?? [])->when(
            ! $isAddition,
            fn ($all) => $all->filter(fn ($value, $key) => ($change->before[$key] ?? null) != $value),
        );

        $importFields = ['label', 'line1', 'city', 'state', 'postal_code', 'country'];
        $orEmpty = fn ($value) => ($value === null || $value === '') ? 'empty' : $value;

        $badge = match ($change->status) {
            App\Models\AddressRequest::STATUS_APPROVED => 'badge-success',
            App\Models\AddressRequest::STATUS_REJECTED => 'badge-amber',
            default => 'badge-soft',
        };
    @endphp

    <div class="panel mb-4">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">
                    {{ Str::headline($change->type) }} request
                    &middot; {{ $change->created_at->diffForHumans() }}
                </p>
                <h2 class="panel__question mb-0">{{ $change->subjectLabel() }}</h2>
                <p class="mb-0 text-dim small">Asked for by {{ $change->user->name }}</p>
            </div>

            <span class="badge {{ $badge }}">{{ Str::headline($change->status) }}</span>
        </div>

        <div class="panel__body panel__body--flush">
            @if ($change->isOrphaned())
                <div class="alert alert-warning small m-3 mb-0">
                    That address no longer exists, so this request cannot be applied.
                </div>
            @elseif ($change->isStale())
                <div class="alert alert-warning small m-3 mb-0">
                    This address was edited after the request was raised, so approving it
                    would overwrite that change.
                </div>
            @endif

            @if ($isImport)
                {{-- The whole file is one decision, so the rows are listed
                     read-only and there is no per-row control. --}}
                <div class="p-3">
                    <p class="text-dim small mb-3">
                        {{ $count }} {{ Str::plural('address', $count) }} from a spreadsheet.
                    </p>

                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($change->payload ?? [] as $index => $address)
                                <tr>
                                    <td class="text-dim small">{{ $index + 1 }}</td>
                                    <td>
                                        @foreach ($importFields as $field)
                                            @if (filled($address[$field] ?? null))
                                                <div class="small">
                                                    <span class="text-dim">{{ Str::headline($field) }}:</span>
                                                    {{ $address[$field] }}
                                                </div>
                                            @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($change->type === App\Models\AddressRequest::TYPE_DELETE)
                <div class="p-3">
                    <p class="mb-0 text-dim small">
                        This request asks for the address to be deleted.
                    </p>
                </div>
            @elseif ($fields->isNotEmpty())
                <div class="p-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Field</th>
                                @unless ($isAddition)
                                    <th>Was</th>
                                @endunless
                                <th>{{ $isAddition ? 'Value' : 'Becomes' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fields as $field => $value)
                                <tr>
                                    <td class="text-dim small">{{ Str::headline($field) }}</td>
                                    @unless ($isAddition)
                                        <td class="text-dim text-decoration-line-through">{{ $orEmpty($change->before[$field] ?? null) }}</td>
                                    @endunless
                                    <td>{{ $orEmpty($value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-3">
                    <p class="mb-0 text-dim small">No values changed.</p>
                </div>
            @endif

            @if (filled($change->note))
                <div class="p-3 border-top" style="border-color: var(--line) !important;">
                    <p class="eyebrow mb-1">Why</p>
                    <p class="mb-0">&ldquo;{{ $change->note }}&rdquo;</p>
                </div>
            @endif
        </div>
    </div>

    @if ($decided)
        <div class="panel">
            <div class="panel__head">
                <p class="eyebrow mb-0">Decision</p>
            </div>

            <div class="panel__body">
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-dim small">Outcome</dt>
                    <dd class="col-sm-9 mb-2">
                        <span class="badge {{ $badge }}">{{ Str::headline($change->status) }}</span>
                    </dd>

                    <dt class="col-sm-3 text-dim small">Decided by</dt>
                    <dd class="col-sm-9 mb-2">{{ $change->decider?->name ?? 'a removed account' }}</dd>

                    <dt class="col-sm-3 text-dim small">When</dt>
                    <dd class="col-sm-9 mb-2">{{ $change->decided_at?->diffForHumans() }}</dd>

                    @if (filled($change->decision_note))
                        <dt class="col-sm-3 text-dim small">Note</dt>
                        <dd class="col-sm-9 mb-0">&ldquo;{{ $change->decision_note }}&rdquo;</dd>
                    @endif
                </dl>
            </div>
        </div>
    @endif
@endsection
