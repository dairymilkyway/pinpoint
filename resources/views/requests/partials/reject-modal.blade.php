@php
    // The second step of a refusal, rendered beside the review modal rather
    // than inside it: a modal opened from within a modal leaves the first one's
    // backdrop stranded over the page. The two take turns, so only one is ever
    // on screen.
    $isAddition = $change->isAddition();
    $orphaned = $change->isOrphaned();
@endphp

<div class="modal fade" id="reject-{{ $change->id }}" tabindex="-1"
     aria-labelledby="reject-{{ $change->id }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('requests.reject', $change) }}">
                @csrf

                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Reject request</p>
                        <h5 class="modal-title" id="reject-{{ $change->id }}-label">
                            {{ $change->subjectLabel() }}
                        </h5>
                        <p class="mb-0 text-dim small">
                            Asked for by {{ $change->user->name }}
                        </p>
                    </div>

                    {{-- Back, not away: the reader is one step into a decision
                         they have not made yet. --}}
                    <button type="button" class="btn-close"
                            data-modal-swap="review-{{ $change->id }}"
                            data-modal-swap-from="reject-{{ $change->id }}"
                            aria-label="Back to the request"></button>
                </div>

                <div class="modal-body">
                    <p class="small">
                        @if ($orphaned)
                            That address no longer exists, so this request can only be closed out.
                            Rejecting writes nothing.
                        @else
                            Rejecting closes the request. Nothing in the list changes.
                        @endif
                    </p>

                    <label class="form-hint" for="reason-{{ $change->id }}">
                        Reason (optional, shown to {{ $change->user->name }})
                    </label>
                    <textarea name="decision_note" id="reason-{{ $change->id }}" rows="3"
                              class="form-control" maxlength="1000"
                              placeholder="{{ $isAddition ? 'Why the address is not being added' : 'Why the change is being refused' }}"></textarea>
                </div>

                <div class="modal-footer action-bar">
                    <button type="button" class="btn btn-outline-secondary"
                            data-modal-swap="review-{{ $change->id }}"
                            data-modal-swap-from="reject-{{ $change->id }}">
                        Keep reviewing
                    </button>
                    <button class="btn btn-danger">Reject this request</button>
                </div>
            </form>
        </div>
    </div>
</div>
