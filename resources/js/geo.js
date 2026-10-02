import * as skeleton from './skeleton';

/**
 * Region -> province -> city picker for the address form.
 *
 * Provinces ship inline with the page, but cities are fetched per region, so
 * the city select is the one field here that waits on the network. It uses the
 * shared shimmer rather than a one-off busy style.
 */
const root = document.querySelector('[data-geo-picker]');

if (root) {
    const endpoint = root.dataset.citiesUrl;
    const byRegion = JSON.parse(root.dataset.provinces || '{}');

    const region = root.querySelector('[name="region_code"]');
    const province = root.querySelector('[name="province_code"]');
    const city = root.querySelector('[name="city_code"]');
    const cityField = city.closest('[data-city-field]');
    const cityName = root.querySelector('[data-city-name]');
    const state = root.querySelector('[name="state"]');
    const country = root.querySelector('[name="country"]');
    const postal = root.querySelector('[name="postal_code"]');

    const option = (label, value) => new Option(label, value);

    const fillProvinces = (regionCode, selected) => {
        const list = byRegion[regionCode] || {};
        const codes = Object.keys(list);

        province.innerHTML = '';
        province.append(option(codes.length ? 'Choose a province…' : 'No provinces in this region', ''));
        codes.forEach((code) => province.append(option(list[code], code)));

        // Metro Manila and the independent cities hang straight off their
        // region, so there is nothing to choose here.
        province.disabled = codes.length === 0;
        province.value = selected || '';
    };

    const applyCity = () => {
        const picked = city.selectedOptions[0];
        if (!picked || !picked.value) return;

        if (cityName) cityName.value = picked.textContent.trim();
        if (state) state.value = picked.dataset.state || '';
        if (country) country.value = 'Philippines';
        if (postal && picked.dataset.postal) postal.value = picked.dataset.postal;
    };

    const idle = (label) => {
        skeleton.hide(cityField);
        city.disabled = true;
        city.innerHTML = '';
        city.append(option(label, ''));
    };

    const loadCities = async (regionCode, provinceCode, selected) => {
        if (!regionCode) {
            idle('Choose a region first');
            return;
        }

        // A region with provinces needs one chosen before cities mean anything.
        if (Object.keys(byRegion[regionCode] || {}).length && !provinceCode) {
            idle('Choose a province first');
            return;
        }

        city.disabled = true;
        city.innerHTML = '';
        skeleton.show(cityField);

        const query = new URLSearchParams({ region: regionCode });
        if (provinceCode) query.set('province', provinceCode);

        let cities;
        try {
            const response = await fetch(`${endpoint}?${query}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(response.status);
            cities = await response.json();
        } catch (error) {
            skeleton.hide(cityField);
            idle('Could not load cities');
            return;
        }

        skeleton.hide(cityField);
        city.disabled = false;
        city.innerHTML = '';
        city.append(option('Choose a city or municipality…', ''));

        Object.entries(cities).forEach(([code, entry]) => {
            const node = option(entry.name, code);
            node.dataset.state = entry.state || '';
            node.dataset.postal = entry.postal || '';
            city.append(node);
        });

        if (selected) {
            city.value = selected;
            applyCity();
        }
    };

    region.addEventListener('change', () => {
        fillProvinces(region.value, '');
        loadCities(region.value, '');
    });

    province.addEventListener('change', () => loadCities(region.value, province.value, ''));
    city.addEventListener('change', applyCity);

    // Restore the stored selection when editing.
    if (region.value) {
        const wantedProvince = province.dataset.selected || '';
        fillProvinces(region.value, wantedProvince);
        loadCities(region.value, wantedProvince, city.dataset.selected || '');
    }
}
