import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet resolves its default marker images relative to its own stylesheet,
// which no longer resolves once a bundler rewrites the paths. Point the default
// icon at the copies Vite has hashed instead.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

const escape = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
};

async function drawMap(element) {
    const map = L.map(element, { scrollWheelZoom: false }).setView([12.8797, 121.774], 5);

    // Standard OpenStreetMap tiles. Attribution is required by the tile usage
    // policy, and bulk or prefetched downloading is not permitted - this only
    // requests the tiles the user actually looks at.
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    let points = [];

    if (element.dataset.points !== undefined) {
        // A page that already holds its pins sends them in the markup, the same
        // way the chart does, so there is no request and no endpoint to guard.
        // A malformed attribute is a build fault rather than something a viewer
        // could retry, so it falls back to the empty state instead of the
        // failure state.
        try {
            points = JSON.parse(element.dataset.points) || [];
        } catch {
            points = [];
        }
    } else {
        try {
            const response = await fetch(element.dataset.url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(response.status);
            points = await response.json();
        } catch (error) {
            element.classList.remove('is-loading');
            element.classList.add('map--failed');
            element.setAttribute('role', 'alert');
            element.textContent = 'Could not load the map.';
            return;
        }
    }

    // Past the load, so the panel stops shimmering whatever happens next.
    element.classList.remove('is-loading');

    if (!points.length) {
        element.classList.add('map--empty');
        element.textContent = 'No addresses with coordinates yet.';
        return;
    }

    const pins = points.map((point) => {
        const where = [point.city, point.state].filter(Boolean).join(', ');

        // The owner line only appears where there is more than one owner to tell
        // apart. The dashboard map is the user's own pins, so it omits the field
        // and the line goes with it.
        const owner = point.owner
            ? `<br><span class="map__owner">${escape(point.owner)}</span>`
            : '';

        // A pin for a city the dataset cannot place is drawn at the centre of
        // its province or region, which is a stand-in rather than a position.
        // It is drawn hollow and says so in the popup: a guess that looks like a
        // measurement is worse than no pin, and the whole point of surfacing it
        // is that the reader can tell which is which.
        const approximate = point.approximate
            ? '<br><span class="map__approx">Approximate location. The dataset has no'
                + ' coordinates for this city, so this is the centre of its province or region.</span>'
            : '';

        const popup = `<strong>${escape(point.label)}</strong><br>`
            + `${escape(point.line)}<br>`
            + `${escape(where)} ${escape(point.postal)}`
            + owner
            + approximate;

        // The ring colour is a fixed amber darker than the theme accent: map.js
        // loads one light OSM tile layer in both themes, so a theme token would
        // resolve to the accent in dark mode and sit at 2.17:1 on the tiles.
        // #8a5a0e reads on tiles and the dashed hollow ring stays distinct from
        // the solid red default marker.
        const marker = point.approximate
            ? L.circleMarker([point.lat, point.lng], {
                radius: 7,
                color: '#8a5a0e',
                weight: 2,
                dashArray: '3 3',
                fillColor: '#8a5a0e',
                fillOpacity: 0.15,
            }).bindPopup(popup)
            : L.marker([point.lat, point.lng]).bindPopup(popup);

        return { marker, region: point.region || null };
    });

    // Every pin starts on the map. The region label beside it is the join key a
    // chart bar click arrives with, so focusing hides the rest without a rebuild.
    pins.forEach(({ marker }) => marker.addTo(map));

    const all = L.featureGroup(pins.map(({ marker }) => marker));
    map.fitBounds(all.getBounds(), { padding: [30, 30], maxZoom: 12 });

    // The chart is a separate lazily imported module, so a bar click reaches the
    // map as a document event rather than a call. The caption is built here and
    // not in a view, so the dashboard and the profile page both get it.
    const focus = document.createElement('div');
    focus.className = 'map__focus';
    focus.hidden = true;

    const focusLabel = document.createElement('span');

    const focusClear = document.createElement('button');
    focusClear.type = 'button';
    focusClear.className = 'map__focus-all';
    focusClear.textContent = 'Show all';
    // The same event a bar click sends, so the map and the chart's highlight
    // reset together.
    focusClear.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('region:focus', { detail: { region: null } }));
    });

    focus.append(focusLabel, focusClear);
    element.append(focus);

    document.addEventListener('region:focus', (event) => {
        const region = event.detail ? event.detail.region : null;

        if (region === null) {
            pins.forEach(({ marker }) => marker.addTo(map));
            map.fitBounds(all.getBounds(), { padding: [30, 30], maxZoom: 12 });
            focus.hidden = true;
            return;
        }

        // A pin whose region is null or "" matches no bar, so it hides here
        // along with every other pin outside the focused region.
        const matched = pins.filter((pin) => pin.region === region);

        pins.forEach((pin) => {
            if (pin.region === region) {
                pin.marker.addTo(map);
            } else {
                pin.marker.remove();
            }
        });

        focusLabel.textContent = matched.length
            ? `Showing ${region}`
            : `${region} - no mapped addresses`;
        focus.hidden = false;

        // A region can hold bars but no pins - its addresses have no
        // coordinates. Fitting to nothing would move the view for no reason, so
        // the caption says so and the view stays where it was.
        if (matched.length) {
            map.fitBounds(
                L.featureGroup(matched.map(({ marker }) => marker)).getBounds(),
                { padding: [30, 30], maxZoom: 12 },
            );
        }
    });

    // The panel is laid out alongside the table, so the container can still have
    // no height at the moment Leaflet first measures it. Re-measuring whenever
    // the box actually changes size covers that first paint and any later one.
    new ResizeObserver(() => map.invalidateSize()).observe(element);
}

const element = document.querySelector('[data-address-map]');

if (element) {
    // app.js only pulls this chunk in after the document has been parsed, so by
    // now DOMContentLoaded has almost always already fired - waiting on it
    // unconditionally registered the listener too late and the map never drew.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => drawMap(element));
    } else {
        drawMap(element);
    }
}
