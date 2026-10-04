<?php

namespace App\Http\Controllers;

use App\AddressStats;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\User;
use App\Rbac;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * The page every signed-in user lands on.
     *
     * Deliberately not gated on addresses.view. An account whose role was
     * revoked holds no permissions at all, and authorizing here would make the
     * landing page a 403 for it. Instead the panels are built from what the user
     * is actually allowed to see, which is also what makes the page differ per
     * role.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $mayReadDirectory = $user->can('addresses.view');

        return view('dashboard.index', [
            'mayReadDirectory' => $mayReadDirectory,
            'cards' => $mayReadDirectory ? $this->cards($user) : [],
            'myRequests' => $mayReadDirectory ? $this->myRequests($user) : new Collection,
            'waitingOnADecision' => $mayReadDirectory ? $this->waitingOnADecision($user) : 0,
            'chart' => $mayReadDirectory ? $this->regions($user) : null,
            'mayRaiseRequests' => $user->can(Rbac::REQUEST_PERMISSION),
            'access' => $user->can(Rbac::MANAGE_PERMISSION) ? $this->access() : null,
            'mayCreate' => $user->can('addresses.create'),
        ]);
    }

    /**
     * The region distribution, scoped to what the caller can see.
     *
     * No longer gated on seesEveryAddress(): a Customer's own rows are few, but
     * the chart is over those rows and the map it drives is theirs too, so the
     * same panel is more use to them than it was withheld for. A roleless account
     * still never reaches this - index() only calls it for a reader of the
     * directory.
     *
     * @return array<int, array{label: string, value: int}>|null
     */
    private function regions(User $user): ?array
    {
        return AddressStats::regions(Address::query()->visibleTo($user));
    }

    /**
     * Every figure describes what this user can actually see, which for anyone
     * but an Admin is their own addresses.
     *
     * Owners is dropped outside the Admin role: scoped to yourself it would
     * always read 1, and a card that can only say 1 is noise.
     *
     * Only the lead card carries a `trend`: the other three have no series behind
     * them, so the view reads it as optional.
     *
     * @return array<int, array{label: string, value: int, hint: string, trend?: array<int, array{label: string, value: int}>}>
     */
    private function cards(User $user): array
    {
        $visible = Address::query()->visibleTo($user);
        $counts = AddressStats::counts($visible);

        $cards = [[
            'label' => 'Addresses',
            'value' => $counts['addresses'],
            'hint' => $user->seesEveryAddress() ? 'on file across every owner' : 'on file under your name',
            'trend' => AddressStats::trend($visible),
        ]];

        if ($user->seesEveryAddress()) {
            $cards[] = [
                'label' => 'Owners',
                'value' => (clone $visible)->distinct()->count('user_id'),
                'hint' => 'people holding at least one',
            ];
        }

        $cards[] = [
            'label' => 'Cities',
            'value' => $counts['cities'],
            'hint' => 'distinct cities and municipalities',
        ];

        $cards[] = [
            'label' => 'Regions',
            'value' => $counts['regions'],
            'hint' => 'regions represented',
        ];

        return $cards;
    }

    /**
     * What this account has asked an administrator to change.
     *
     * Only ever filled for a role that can raise one. A reader looks at the whole
     * queue on /requests, and repeating it here as "your requests" would be the
     * same rows under a wrong name.
     *
     * Waiting first, then the settled ones: a request nobody has ruled on yet is
     * the only entry on this list the reader of it can still act on.
     *
     * @return Collection<int, AddressRequest>
     */
    private function myRequests(User $user): Collection
    {
        if (! $user->can(Rbac::REQUEST_PERMISSION)) {
            return new Collection;
        }

        return AddressRequest::query()
            ->visibleTo($user)
            ->with('decider:id,name')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AddressRequest::STATUS_PENDING])
            ->latest('id')
            ->limit(5)
            ->get();
    }

    /** Counted in SQL and not from the list above, which is capped at five. */
    private function waitingOnADecision(User $user): int
    {
        if (! $user->can(Rbac::REQUEST_PERMISSION)) {
            return 0;
        }

        return AddressRequest::query()->visibleTo($user)->pending()->count();
    }

    /**
     * The same scope as every other figure on the page, so the map agrees with
     * the cards above it: Admin pins the whole book, everyone else pins their
     * own. The owner is only sent when there is more than one to tell apart.
     */
    public function map(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(AddressStats::mapPoints(
            Address::query()->visibleTo($user),
            $user->seesEveryAddress(),
        ));
    }

    /**
     * Admin-only panel. Only reached for users holding rbac.manage, which is the
     * same permission the /rbac page itself is gated on.
     *
     * @return array{users: int, roles: int, permissions: int}
     */
    private function access(): array
    {
        return [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
        ];
    }
}
