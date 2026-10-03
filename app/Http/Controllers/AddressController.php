<?php

namespace App\Http\Controllers;

use App\AddressStats;
use App\DataTables\AddressDataTable;
use App\DataTables\UserDataTable;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AddressController extends Controller
{
    use AuthorizesRequests;

    /**
     * A reader of the whole book gets the list of users and walks in from there;
     * everyone else lands on their own addresses, because a user list would hold
     * one row - themselves - and add only a click.
     */
    public function index(Request $request, AddressDataTable $dataTable, UserDataTable $users): mixed
    {
        $this->authorize('viewAny', Address::class);

        return $request->user()->seesEveryAddress()
            ? $users->render('addresses.users')
            : $dataTable->render('addresses.index');
    }

    /**
     * One owner's profile, reached from the users list.
     *
     * The scope is narrow enough that a Customer could only ever ask for their
     * own, so anyone else's page is refused outright rather than answered with
     * an empty table, which would hide the boundary instead of enforcing it.
     *
     * Every figure on the page reads from one scoped builder, so the cards, the
     * chart and the map cannot disagree about whose addresses they are counting.
     */
    public function forUser(Request $request, User $user, AddressDataTable $dataTable): mixed
    {
        $this->authorize('viewAny', Address::class);

        abort_unless(
            $request->user()->seesEveryAddress() || $request->user()->id === $user->id,
            403,
        );

        $owned = Address::query()
            ->visibleTo($request->user())
            ->where('user_id', $user->id);

        $coverage = AddressStats::coverage($owned);

        return $dataTable->forUser($user)->render('addresses.show', [
            'owner' => $user->load('roles'),
            'initials' => $this->initials($user),
            'cards' => $this->profileCards($owned, $coverage),
            'coverage' => $coverage,
            'chart' => AddressStats::regions($owned),
            // Sent in the markup rather than fetched: the page has already
            // authorized these rows, so there is no second endpoint to guard.
            'pins' => AddressStats::mapPoints($owned, false),
        ]);
    }

    /**
     * The four figures that describe this account. Deliberately not the
     * dashboard's card list: an Owners card would read 1 here, and every hint
     * would be phrased for a reader rather than for the account on screen.
     *
     * @param  array{pinned: int, total: int, percent: int}  $coverage
     * @return array<int, array{label: string, value: int, hint: string}>
     */
    private function profileCards(Builder $owned, array $coverage): array
    {
        $counts = AddressStats::counts($owned);

        return [
            [
                'label' => 'Addresses',
                'value' => $counts['addresses'],
                'hint' => 'held by this account',
            ],
            [
                'label' => 'Cities',
                'value' => $counts['cities'],
                'hint' => 'distinct cities and municipalities',
            ],
            [
                'label' => 'Regions',
                'value' => $counts['regions'],
                'hint' => 'regions represented',
            ],
            [
                'label' => 'Pinned',
                'value' => $coverage['pinned'],
                'hint' => $coverage['percent'].'% of them have coordinates',
            ],
        ];
    }

    /** Two letters for the avatar, from the first two words of the name. */
    private function initials(User $user): string
    {
        return Str::of($user->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => Str::substr($part, 0, 1))
            ->take(2)
            ->implode('');
    }

    public function create(): View
    {
        $this->authorize('create', Address::class);

        return view('addresses.create', ['address' => new Address]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $request->user()->addresses()->create($request->validated());

        return $this->afterWrite($request, $request->user())
            ->with('success', 'Address created.');
    }

    public function edit(Address $address): View
    {
        $this->authorize('update', $address);

        return view('addresses.edit', compact('address'));
    }

    public function update(UpdateAddressRequest $request, Address $address): RedirectResponse
    {
        $address->update($request->validated());

        return $this->afterWrite($request, $address->user)
            ->with('success', 'Address updated.');
    }

    /**
     * Move the default marker on your own book.
     *
     * The one write a Customer performs directly, and it is owner-only: the
     * marker is theirs, and it changes nothing anyone else sees. The old default
     * is cleared through the models rather than with one bulk update, because a
     * query-builder update fires no events and the change would be missing from
     * the audit log.
     */
    public function setDefault(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('setDefault', $address);

        $address->user->addresses()
            ->where('is_default', true)
            ->whereKeyNot($address->getKey())
            ->get()
            ->each(fn (Address $other) => $other->update(['is_default' => false]));

        $address->update(['is_default' => true]);

        return redirect()->route('addresses.index')
            ->with('success', 'Default address updated.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        $owner = $address->user;

        $address->delete();

        return $this->afterWrite($request, $owner)
            ->with('success', 'Address deleted.');
    }

    /**
     * Where a write lands.
     *
     * For a reader of the whole book the index is now the users list, so
     * returning there would leave the address they just touched a click away.
     * They go to the owner's page instead. For everyone else this is the index,
     * which is what it has always been.
     */
    private function afterWrite(Request $request, ?User $owner): RedirectResponse
    {
        if ($owner !== null && $request->user()->seesEveryAddress()) {
            return redirect()->route('addresses.user', $owner);
        }

        return redirect()->route('addresses.index');
    }
}
