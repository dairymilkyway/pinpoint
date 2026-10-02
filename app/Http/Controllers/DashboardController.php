<?php

namespace App\Http\Controllers;

use App\Models\Address;
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
            'coverage' => $mayReadDirectory ? $this->coverage($user) : null,
            'recent' => $mayReadDirectory ? $this->recent($user) : new Collection(),
            'access' => $user->can(Rbac::MANAGE_PERMISSION) ? $this->access() : null,
            'mayCreate' => $user->can('addresses.create'),
            'mayMap' => $user->addresses()->whereNotNull('latitude')->exists(),
        ]);
    }

    /**
     * Every figure describes what this user can actually see, which for anyone
     * but an Admin is their own addresses.
     *
     * Owners is dropped outside the Admin role: scoped to yourself it would
     * always read 1, and a card that can only say 1 is noise.
     *
     * @return array<int, array{label: string, value: int, hint: string}>
     */
    private function cards(User $user): array
    {
        $visible = Address::query()->visibleTo($user);

        $cards = [[
            'label' => 'Addresses',
            'value' => (clone $visible)->count(),
            'hint' => $user->seesEveryAddress() ? 'on file across every owner' : 'on file under your name',
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
            'value' => (clone $visible)->whereNotNull('city_code')->distinct()->count('city_code'),
            'hint' => 'distinct cities and municipalities',
        ];

        $cards[] = [
            'label' => 'Regions',
            'value' => (clone $visible)->whereNotNull('region_code')->distinct()->count('region_code'),
            'hint' => 'regions represented',
        ];

        return $cards;
    }

    /**
     * How much of what the user can see can be pinned. Counted in SQL rather
     * than by loading every row, since this is the only thing the page needs
     * from them.
     *
     * @return array{pinned: int, total: int, percent: int}
     */
    private function coverage(User $user): array
    {
        $visible = Address::query()->visibleTo($user);

        $total = (clone $visible)->count();
        $pinned = (clone $visible)->whereNotNull('latitude')->whereNotNull('longitude')->count();

        return [
            'pinned' => $pinned,
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) round($pinned / $total * 100),
        ];
    }

    /** @return Collection<int, Address> */
    private function recent(User $user): Collection
    {
        return Address::query()
            ->visibleTo($user)
            ->with('user:id,name')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'user_id', 'label', 'line1', 'city', 'state', 'postal_code', 'is_default']);
    }

    /**
     * The dashboard map is deliberately not the scoped listing. It is the
     * signed-in user's own pins, which means Admin sees fewer here than in the
     * directory beside it.
     */
    public function map(Request $request): JsonResponse
    {
        $points = $request->user()->addresses()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'label', 'line1', 'line2', 'city', 'state', 'postal_code', 'latitude', 'longitude'])
            ->map(fn (Address $address) => [
                'label' => $address->label,
                'line' => trim($address->line1.($address->line2 ? ', '.$address->line2 : '')),
                'city' => $address->city,
                'state' => $address->state,
                'postal' => $address->postal_code,
                'lat' => $address->latitude,
                'lng' => $address->longitude,
            ]);

        return response()->json($points);
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
