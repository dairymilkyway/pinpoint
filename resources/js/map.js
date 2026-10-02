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

    try {
        const response = await fetch(element.dataset.url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(response.status);
        points = await response.json();
    } catch (error) {
        element.classList.remove('is-loading');
        element.classList.add('map--failed');
        element.textContent = 'Could not load the map.';
        return;
    }

    // Past the fetch, so the panel stops shimmering whatever happens next.
    element.classList.remove('is-loading');

    if (!points.length) {
        element.classList.add('map--empty');
        element.textContent = 'No addresses with coordinates yet.';
        return;
    }

    const markers = points.map((point) => {
        const where = [point.city, point.state].filter(Boolean).join(', ');

        return L.marker([point.lat, point.lng]).bindPopup(
            `<strong>${escape(point.label)}</strong><br>`
            + `${escape(point.line)}<br>`
            + `${escape(where)} ${escape(point.postal)}<br>`
            + `<span class="map__owner">${escape(point.owner)}</span>`
        );
    });

    const group = L.featureGroup(markers).addTo(map);
    map.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: 12 });

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
