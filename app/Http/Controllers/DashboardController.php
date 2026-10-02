<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use App\Rbac;
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
     * Deliberately not gated on addresses.view. Registration does not assign a
     * role, so a brand new account holds no permissions at all - authorizing
     * here would make the landing page a 403 for them. Instead the panels are
     * built from what the user is actually allowed to see, which is also what
     * makes the page differ per role.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $mayReadDirectory = $user->can('addresses.view');

        return view('dashboard.index', [
            'mayReadDirectory' => $mayReadDirectory,
            'cards' => $mayReadDirectory ? $this->cards() : [],
            'coverage' => $mayReadDirectory ? $this->coverage() : null,
            'recent' => $mayReadDirectory ? $this->recent() : new Collection(),
            'access' => $user->can(Rbac::MANAGE_PERMISSION) ? $this->access() : null,
            'mayCreate' => $user->can('addresses.create'),
        ]);
    }

    /** @return array<int, array{label: string, value: int, hint: string}> */
    private function cards(): array
    {
        return [
            [
                'label' => 'Addresses',
                'value' => Address::count(),
                'hint' => 'on file across every owner',
            ],
            [
                'label' => 'Owners',
                'value' => Address::distinct()->count('user_id'),
                'hint' => 'people holding at least one',
            ],
            [
                'label' => 'Cities',
                'value' => Address::whereNotNull('city_code')->distinct()->count('city_code'),
                'hint' => 'distinct cities and municipalities',
            ],
            [
                'label' => 'Regions',
                'value' => Address::whereNotNull('region_code')->distinct()->count('region_code'),
                'hint' => 'regions represented',
            ],
        ];
    }

    /**
     * How much of the directory can be pinned. Counted in SQL rather than by
     * loading every row, since this is the only thing the page needs from them.
     *
     * @return array{pinned: int, total: int, percent: int}
     */
    private function coverage(): array
    {
        $total = Address::count();
        $pinned = Address::whereNotNull('latitude')->whereNotNull('longitude')->count();

        return [
            'pinned' => $pinned,
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) round($pinned / $total * 100),
        ];
    }

    /** @return Collection<int, Address> */
    private function recent(): Collection
    {
        return Address::query()
            ->with('user:id,name')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'user_id', 'label', 'line1', 'city', 'state', 'postal_code', 'is_default']);
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
