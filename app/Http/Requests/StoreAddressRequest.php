<?php

namespace App\Http\Requests;

use App\Geo\PhLocations;
use App\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Address::class);
    }

    /**
     * When a city code is supplied, the bundled dataset becomes authoritative
     * for everything it knows.
     *
     * Derived here rather than validated and trusted, which is what makes the
     * address lookup real: a client cannot pair Cebu's city code with a Manila
     * postal code, because the code decides all of it. An address with no city
     * code still takes the free-text path, so the existing behaviour and its
     * tests are untouched.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_default' => $this->boolean('is_default')]);

        if ($this->filled('city_code')) {
            $this->merge(Address::attributesFromCityCode((string) $this->input('city_code')));
        }
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country' => ['required', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', Rule::in(array_keys(PhLocations::regions()))],
            'province_code' => ['nullable', 'string', Rule::in(array_keys(PhLocations::provinces()))],
            'city_code' => ['nullable', 'string', Rule::in(array_keys(PhLocations::cities()))],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['boolean'],
        ];
    }
}
