@php
    // One modal per waiting request, server-rendered rather than filled in by
    // JavaScript: the values being proposed are the whole point of opening it,
    // and they do not fit in a data attribute. Only pending rows get one, and a
    // pending queue is by nature short.
    $isAddition = $change->isAddition();
    $orphaned = $change->isOrphaned();
    $isImport = $change->type === App\Models\AddressRequest::TYPE_IMPORT;

    // An edit is shown against what the address said when the request was
    // raised, and only in the fields that actually move. An addition has nothing
    // to compare against, so every value it would write is listed - blank ones
    // dropped, because "Line 2: empty" tells a reader nothing.
    //
    // An import is not a field map at all - its payload is a list of whole
    // addresses - so it takes its own branch in the body and never reaches this
    // flat mapping, which would throw on an array value.
    $rows = $isImport
        ? collect()
        : collect($change->payload ?? [])
            ->when(
                ! $isAddition,
                fn ($all) => $all->filter(fn ($value, $key) => ($change->before[$key] ?? null) != $value),
            )
            ->when(
                $isAddition,
                fn ($all) => $all->filter(fn ($value) => $value !== null && $value !== ''),
            )
            ->map(fn ($value, $key) => [
                'field' => Str::headline($key),
                'was' => $isAddition ? null : ($change->before[$key] ?? null),
                'becomes' => $value,
            ]);

    // The columns a reader judges a proposed address on. The payload also carries
    // codes and coordinates derived from the city; those are not a reader's
    // question and are left out of the listing.
    $importFields = ['label', 'line1', 'city', 'state', 'postal_code', 'country'];

    $orEmpty = fn ($value) => ($value === null || $value === '') ? 'empty' : $value;
@endphp

<div class="modal fade" id="review-{{ $change->id }}" tabindex="-1"
     aria-labelledby="review-{{ $change->id }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">
                        {{ Str::headline($change->type) }} request
                        &middot; {{ $change->created_at->diffForHumans() }}
                    </p>
                    <h5 class="modal-title" id="review-{{ $change->id }}-label">
                        {{ $change->subjectLabel() }}
                    </h5>
                    <p class="mb-0 text-dim small">
                        Asked for by {{ $change->user->name }}
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @if ($orphaned)
                    <div class="alert alert-warning small mb-3">
                        That address no longer exists, so this cannot be applied. Reject it.
                    </div>
                @elseif ($change->isStale())
                    <div class="alert alert-warning small mb-3">
                        This address was edited after the request was raised, so approving it
                        would overwrite that change.
                    </div>
                @endif

                @if ($isImport)
                    {{-- The whole file is one decision, so the rows are listed
                         read-only: there is no per-row control and the footer
                         approves or rejects all of them together. --}}
                    <p class="text-dim small mb-3">
                        Approving adds all of these addresses to the requester's list;
                        rejecting adds none.
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
                @elseif ($rows->isNotEmpty())
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
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="text-dim small">{{ $row['field'] }}</td>
                                    @unless ($isAddition)
                                        <td class="text-dim text-decoration-line-through">{{ $orEmpty($row['was']) }}</td>
                                    @endunless
                                    <td>{{ $orEmpty($row['becomes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="mb-0 text-dim small">
                        This request asks for the address to be deleted. Nothing is written
                        until it is approved.
                    </p>
                @endif

                @if ($change->note)
                    <p class="eyebrow mt-4 mb-1">Why</p>
                    <p class="mb-0">&ldquo;{{ $change->note }}&rdquo;</p>
                @endif
            </div>

            <div class="modal-footer action-bar">
                @if ($canDecide)
                    {{-- Both decisions side by side, so the choice is the choice.
                         Rejecting leads to a confirmation of its own rather than
                         opening a reason field above a button nobody has decided
                         to press yet. --}}
                    <button type="button" class="btn btn-outline-danger"
                            data-modal-swap="reject-{{ $change->id }}"
                            data-modal-swap-from="review-{{ $change->id }}">
                        Reject
                    </button>

                    @unless ($orphaned)
                        <form method="POST" action="{{ route('requests.approve', $change) }}">
                            @csrf
                            <button class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> <span class="ms-1">Approve and apply</span>
                            </button>
                        </form>
                    @endunless
                @else
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
