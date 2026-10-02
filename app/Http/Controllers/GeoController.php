<?php

namespace App\Http\Controllers;

use App\Geo\PhLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    /**
     * Cities for the address form's cascading dropdown.
     *
     * Served from the bundled dataset, so this is a local array read with no
     * upstream call, no API key and nothing to rate-limit. The payload carries
     * each city's postal code and its resolved state so the form can fill those
     * fields as soon as a city is picked, instead of the user typing them.
     */
    public function cities(Request $request): JsonResponse
    {
        $region = $request->string('region')->toString() ?: null;
        $province = $request->string('province')->toString() ?: null;

        // Reject unknown codes rather than silently returning an empty list.
        if ($region !== null && ! array_key_exists($region, PhLocations::regions())) {
            abort(422, 'Unknown region code.');
        }

        if ($province !== null && ! array_key_exists($province, PhLocations::provinces())) {
            abort(422, 'Unknown province code.');
        }

        $cities = [];

        foreach (PhLocations::citiesIn($region, $province) as $code => $name) {
            $geo = PhLocations::coordinatesFor($code);

            $cities[$code] = [
                'name' => $name,
                'state' => PhLocations::stateFor(PhLocations::find($code)),
                'postal' => $geo['postal'] ?? null,
            ];
        }

        return response()->json($cities);
    }
}
