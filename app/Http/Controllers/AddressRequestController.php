<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressChangeRequest;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AddressRequestDecided;
use App\Notifications\AddressRequestSettled;
use App\Rbac;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The queue: Customers propose a change to an address, the two reading roles
 * decide it.
 *
 * Both roles reach the same screen and it is the scoping, not the door, that
 * separates them - a Customer sees the requests they raised, a reader sees the
 * whole pending queue. A separate "my requests" page would have been a second
 * screen showing the same rows.
 */
class AddressRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AddressRequest::class);

        $user = $request->user();

        return view('requests.index', [
            'pending' => AddressRequest::query()
                ->visibleTo($user)
                ->pending()
                // Oldest first: the queue is worked from the front, and a
                // request that has waited longest is the one that has waited
                // too long.
                ->orderBy('created_at')
                ->with(['user', 'address', 'decider'])
                ->get(),
            'decided' => AddressRequest::query()
                ->visibleTo($user)
                ->where('status', '!=', AddressRequest::STATUS_PENDING)
                ->latest('decided_at')
                ->limit(20)
                ->with(['user', 'address', 'decider'])
                ->get(),
            'canDecide' => $user->can(Rbac::APPROVE_PERMISSION),
        ]);
    }

    /**
     * The form for raising one. An addition starts blank; an edit starts from
     * the address as it stands, so the Customer is editing real values rather
     * than retyping them.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', AddressRequest::class);

        $type = $request->input('type') === AddressRequest::TYPE_CREATE
            ? AddressRequest::TYPE_CREATE
            : AddressRequest::TYPE_UPDATE;

        $address = $request->filled('address')
            ? Address::findOrFail($request->integer('address'))
            : null;

        if ($address !== null) {
            $this->authorize('requestFor', $address);
        }

        // An edit request names an address; there is nothing to edit without one.
        abort_if($type === AddressRequest::TYPE_UPDATE && $address === null, 404);

        return view('requests.create', [
            'type' => $type,
            'address' => $address ?? new Address,
        ]);
    }

    public function store(StoreAddressChangeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['type'];

        $address = empty($data['address_id'])
            ? null
            : Address::findOrFail($data['address_id']);

        if ($address !== null) {
            $this->authorize('requestFor', $address);
        }

        AddressRequest::raise([
            'user_id' => $request->user()->id,
            'address_id' => $address?->id,
            'type' => $type,
            'payload' => $this->proposedValues($data, $type),
            // The address as it stands, so a reader can see what the request was
            // written against when it was written.
            'before' => $address?->snapshot(),
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('requests.index')
            ->with('success', 'Request submitted. An administrator will review it.');
    }

    public function approve(Request $request, AddressRequest $addressRequest): RedirectResponse
    {
        $this->authorize('decide', $addressRequest);

        $refused = $this->apply($addressRequest);

        if ($refused !== null) {
            return back()->with('error', $refused);
        }

        $this->settle($request, $addressRequest, AddressRequest::STATUS_APPROVED, null);

        return back()->with('success', 'Request approved and applied.');
    }

    public function reject(Request $request, AddressRequest $addressRequest): RedirectResponse
    {
        $this->authorize('decide', $addressRequest);

        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->settle(
            $request,
            $addressRequest,
            AddressRequest::STATUS_REJECTED,
            $validated['decision_note'] ?? null,
        );

        return back()->with('success', 'Request rejected. The address is unchanged.');
    }

    /**
     * Applies an approved proposal. Returns the reason it could not, or null.
     *
     * The types write through the same model paths a reader's own write uses, so
     * the audit observer sees an approved request exactly as it sees a direct
     * edit.
     */
    private function apply(AddressRequest $change): ?string
    {
        if ($change->isOrphaned()) {
            return 'That address no longer exists, so this request cannot be applied. Reject it instead.';
        }

        match ($change->type) {
            AddressRequest::TYPE_CREATE => $change->user->addresses()->create($change->payload ?? []),
            AddressRequest::TYPE_UPDATE => $change->address->update($change->payload ?? []),
            AddressRequest::TYPE_DELETE => $change->address->delete(),
            // All or nothing: a half-applied import would leave the Customer's
            // book in a state neither party chose, which is the thing the
            // per-file decision exists to avoid. If any row fails, none land.
            AddressRequest::TYPE_IMPORT => DB::transaction(function () use ($change): void {
                foreach ($change->payload ?? [] as $row) {
                    $change->user->addresses()->create($row);
                }
            }),
        };

        return null;
    }

    private function settle(
        Request $request,
        AddressRequest $change,
        string $status,
        ?string $decisionNote,
    ): void {
        $change->update([
            'status' => $status,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $decisionNote,
        ]);

        // Reloaded so the decider and the decision are on the instance the
        // notifications read from - they were written after the request was
        // loaded, and the requester's message names who decided it.
        $change->load('decider');

        $event = $status === AddressRequest::STATUS_APPROVED
            ? AuditLog::REQUEST_APPROVED
            : AuditLog::REQUEST_REJECTED;

        AuditLog::record($event, $change, null, [
            'type' => $change->type,
            'address' => $change->subjectLabel(),
            'requester' => $change->user->name,
            'note' => $decisionNote,
        ]);

        $change->user->notify(new AddressRequestDecided($change));

        foreach ($this->approvers($request->user()) as $approver) {
            $approver->notify(new AddressRequestSettled($change));
        }
    }

    /**
     * The proposal, without the fields that describe the request rather than the
     * address.
     *
     * is_default is dropped on purpose. A default is a marker on the owner's own
     * book and has its own action, which is immediate; letting it ride along in
     * a proposal would mean an approved request silently moving a marker the
     * Customer could have moved themselves without asking.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function proposedValues(array $data, string $type): ?array
    {
        if ($type === AddressRequest::TYPE_DELETE) {
            return null;
        }

        return Arr::except($data, ['type', 'address_id', 'note', 'is_default']);
    }

    /**
     * Everyone who may decide a request, minus whoever just acted. Excluding the
     * actor matters for the settled notice: telling an approver what they
     * themselves just did is noise.
     *
     * @return Collection<int, User>
     */
    private function approvers(?User $except = null): Collection
    {
        return User::query()
            ->permission(Rbac::APPROVE_PERMISSION)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }
}
