@php
    use App\Geo\PhLocations;

    $regions = PhLocations::regions();

    // Province options per region, so changing the region repopulates the next
    // dropdown without a round trip. 82 entries in total, small enough to inline.
    $provincesByRegion = [];
    foreach ($regions as $code => $name) {
        $provincesByRegion[$code] = PhLocations::provincesIn($code);
    }

    $selectedRegion = old('region_code', $address->region_code);
    $selectedProvince = old('province_code', $address->province_code);
    $selectedCity = old('city_code', $address->city_code);
@endphp

<form method="POST" action="{{ $action }}" data-geo-picker
      data-cities-url="{{ route('geo.cities') }}"
      data-provinces='@json($provincesByRegion)'>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- What the form is for, when that is not simply the address: a request
         carries its type and the address it is about. --}}
    @foreach ($hidden ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <p class="eyebrow mb-3">Identity</p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="label" class="form-label">Label <span class="text-dim">*</span></label>
            <input type="text" name="label" id="label" required
                   value="{{ old('label', $address->label) }}"
                   class="form-control @error('label') is-invalid @enderror"
                   placeholder="Home, Office, Warehouse">
            @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-8">
            <label for="line1" class="form-label">Address line 1 <span class="text-dim">*</span></label>
            <input type="text" name="line1" id="line1" required
                   value="{{ old('line1', $address->line1) }}"
                   class="form-control @error('line1') is-invalid @enderror"
                   placeholder="Street, building, unit">
            @error('line1') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
            <label for="line2" class="form-label">Address line 2</label>
            <input type="text" name="line2" id="line2"
                   value="{{ old('line2', $address->line2) }}"
                   class="form-control @error('line2') is-invalid @enderror"
                   placeholder="Barangay, district, landmark">
            @error('line2') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex align-items-baseline justify-content-between mb-3">
        <p class="eyebrow mb-0">Location</p>
        <span class="form-hint">From the Philippine Standard Geographic Code</span>
    </div>

    <noscript>
        <div class="alert alert-warning py-2">
            The city and province lists need JavaScript. Without it, addresses cannot be saved from this form.
        </div>
    </noscript>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="region_code" class="form-label">Region <span class="text-dim">*</span></label>
            <select name="region_code" id="region_code" class="form-select @error('region_code') is-invalid @enderror">
                <option value="">Choose a region&hellip;</option>
                @foreach ($regions as $code => $name)
                    <option value="{{ $code }}" @selected($selectedRegion === $code)>{{ $name }}</option>
                @endforeach
            </select>
            @error('region_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="province_code" class="form-label">Province</label>
            <select name="province_code" id="province_code" data-selected="{{ $selectedProvince }}"
                    class="form-select @error('province_code') is-invalid @enderror">
                <option value="">Choose a region first</option>
            </select>
            <div class="form-hint">Empty for Metro Manila and the independent cities.</div>
            @error('province_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="city_code" class="form-label">City or municipality <span class="text-dim">*</span></label>

            {{-- Cities are the only field on this form fetched over the network,
                 so it is wrapped as a skeleton host and uses the shared shimmer. --}}
            <div class="skeleton-host" data-city-field>
                <select name="city_code" id="city_code" required data-selected="{{ $selectedCity }}"
                        class="form-select @error('city_code') is-invalid @enderror">
                    <option value="">Choose a region first</option>
                </select>
                <div class="skeleton-overlay skeleton-overlay--field" aria-hidden="true">
                    <div class="skeleton skeleton--field"></div>
                </div>
            </div>

            @error('city_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Kept in step with the city dropdown. The server re-derives these from
             the city code anyway, so they are a fallback, not the source. --}}
        <input type="hidden" name="city" data-city-name value="{{ old('city', $address->city) }}">

        <div class="col-md-4">
            <label for="postal_code" class="form-label">Postal code <span class="text-dim">*</span></label>
            <input type="text" name="postal_code" id="postal_code" required
                   value="{{ old('postal_code', $address->postal_code) }}"
                   class="form-control mono @error('postal_code') is-invalid @enderror">
            <div class="form-hint">Approximate, from GeoNames. Not authoritative.</div>
            @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="state" class="form-label">State or province</label>
            <input type="text" name="state" id="state" readonly
                   value="{{ old('state', $address->state) }}"
                   class="form-control @error('state') is-invalid @enderror">
            @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="country" class="form-label">Country <span class="text-dim">*</span></label>
            <input type="text" name="country" id="country" readonly required
                   value="{{ old('country', $address->country ?? 'Philippines') }}"
                   class="form-control @error('country') is-invalid @enderror">
            @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Hidden on a request form. The default is a marker on the owner's own
             book with its own immediate action, so letting it ride along in a
             proposal would mean asking permission for something the Customer can
             already do without one - and an approved proposal moving a marker
             they never asked to move. --}}
        @if ($showDefault ?? true)
            <div class="col-12">
                <div class="form-check mt-1">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" id="is_default" value="1"
                           class="form-check-input"
                           @checked(old('is_default', $address->is_default))>
                    <label class="form-check-label" for="is_default">Set as the default address</label>
                    <div class="form-hint">Shown first wherever this owner's addresses are listed.</div>
                </div>
            </div>
        @endif
    </div>

    @if ($withNote ?? false)
        <div class="row g-3 mb-4">
            <div class="col-12">
                <label for="note" class="form-label">Why this change?</label>
                <textarea name="note" id="note" rows="2" maxlength="1000"
                          class="form-control @error('note') is-invalid @enderror"
                          placeholder="Anything the reviewer should know.">{{ old('note') }}</textarea>
                <div class="form-hint">Optional, and shown to whoever reviews it.</div>
                @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    @endif

    <div class="d-flex gap-2 pt-3 border-top" style="border-color: var(--line) !important;">
        <button type="submit" class="btn btn-primary px-4">{{ $submitLabel }}</button>
        <a href="{{ $cancelRoute ?? route('addresses.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
