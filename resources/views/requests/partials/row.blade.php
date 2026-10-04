@php
    // A row is a summary. The values being asked for are in the review modal,
    // because a proposal carries twelve fields and a table cell that lists them
    // all turns the queue into a wall nobody scans.
    $isAddition = $change->isAddition();
    $isImport = $change->type === App\Models\AddressRequest::TYPE_IMPORT;
    $count = $change->rowCount();

    $fields = collect($change->payload ?? [])->when(
        ! $isAddition,
        fn ($all) => $all->filter(fn ($value, $key) => ($change->before[$key] ?? null) != $value),
    );

    // An import is an addition, but "A new address" reads wrong for a whole
    // file, so it leads with how many rows it carries instead.
    $summary = $isImport
        ? $count.' '.Str::plural('address', $count)
        : ($isAddition
            ? 'A new address'
            : $fields->keys()->map(fn (string $field) => Str::headline($field))->implode(', '));
@endphp

<tr>
    <td>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-soft">{{ Str::headline($change->type) }}</span>
            <span>{{ $change->subjectLabel() }}</span>
        </div>

        @if ($summary !== '')
            <div class="text-dim small mt-1">{{ $summary }}</div>
        @endif

        @if (filled($change->note))
            <div class="text-dim small mt-1">{{ $change->note }}</div>
        @endif
    </td>

    <td class="small">{{ $change->user->name }}</td>

    <td class="text-dim small text-nowrap">
        @if ($decided)
            {{ $change->decided_at?->diffForHumans() }}
            <div>{{ $change->decider?->name ?? 'a removed account' }}</div>
        @else
            {{ $change->created_at->diffForHumans() }}
        @endif
    </td>

    <td class="text-end text-nowrap">
        @if ($decided)
            <span class="badge {{ $change->status === App\Models\AddressRequest::STATUS_APPROVED ? 'badge-success' : 'badge-amber' }}">
                {{ Str::headline($change->status) }}
            </span>
            @if ($change->decision_note)
                <div class="text-dim small mt-1">{{ Str::limit($change->decision_note, 60) }}</div>
            @endif
        @else
            @if ($change->isOrphaned())
                <span class="badge badge-amber">Address is gone</span>
            @elseif ($change->isStale())
                {{-- Decided by value, not by timestamp: the address moved after
                     this was written, so approving it would overwrite an edit
                     the requester never saw. --}}
                <span class="badge badge-amber">Changed since raised</span>
            @endif

            {{-- One button, and the whole decision lives behind it: the values
                 on one side, the reason on the other. --}}
            <button type="button" class="btn btn-sm {{ $canDecide && ! $change->isOrphaned() ? 'btn-primary' : 'btn-outline-secondary' }} ms-1"
                    data-bs-toggle="modal" data-bs-target="#review-{{ $change->id }}">
                {{ $canDecide ? 'Review' : 'View' }}
            </button>
        @endif
    </td>
</tr>
