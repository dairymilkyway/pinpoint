<?php

namespace App\Http\Controllers;

use App\DataTables\AddressDataTable;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AddressController extends Controller
{
    use AuthorizesRequests;

    public function index(AddressDataTable $dataTable): mixed
    {
        $this->authorize('viewAny', Address::class);

        return $dataTable->render('addresses.index');
    }

    /**
     * Addresses that can be pinned, for the map beside the directory.
     *
     * Only rows with coordinates are returned, and the fields are trimmed to what
     * a popup shows - the map has the same visibility as the table next to it,
     * gated by the same policy.
     */
    public function map(): JsonResponse
    {
        $this->authorize('viewAny', Address::class);

        $points = Address::query()
            ->with('user:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'user_id', 'label', 'line1', 'line2', 'city', 'state', 'postal_code', 'latitude', 'longitude'])
            ->map(fn (Address $address) => [
                'label' => $address->label,
                'line' => trim($address->line1.($address->line2 ? ', '.$address->line2 : '')),
                'city' => $address->city,
                'state' => $address->state,
                'postal' => $address->postal_code,
                'owner' => $address->user?->name,
                'lat' => $address->latitude,
                'lng' => $address->longitude,
            ]);

        return response()->json($points);
    }

    public function create(): View
    {
        $this->authorize('create', Address::class);

        return view('addresses.create', ['address' => new Address]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $request->user()->addresses()->create($request->validated());

        return redirect()
            ->route('addresses.index')
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

        return redirect()
            ->route('addresses.index')
            ->with('success', 'Address updated.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return redirect()
            ->route('addresses.index')
            ->with('success', 'Address deleted.');
    }
}
