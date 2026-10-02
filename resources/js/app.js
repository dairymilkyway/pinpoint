// Self-hosted typography, no remote font CDN.
import '@fontsource-variable/space-grotesk';
import '@fontsource-variable/jetbrains-mono';

import './bootstrap';

import 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import './buttons.server-side';

import 'bootstrap-icons/font/bootstrap-icons.css';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';

import * as skeleton from './skeleton';

// The address form's region/province/city picker. It is small, so it is bundled
// rather than lazily imported, and guards itself on the form's presence.
import './geo';

// Leaflet is a third of the bundle and only one page uses it, so it is pulled in
// on demand rather than shipped to every visitor.
const mapElement = document.querySelector('[data-address-map]');

if (mapElement) {
    import('./map').catch(() => {
        // Without this the panel would shimmer forever if the chunk 404s.
        mapElement.classList.remove('is-loading');
        skeleton.hide(mapElement);
        mapElement.classList.add('map--failed');
        mapElement.textContent = 'Could not load the map.';
    });
}

// The directory table is filled over ajax on every search, sort and page change,
// so it is the surface that blanks most often. DataTables fires preXhr before
// each request and draw once the rows are in.
const table = document.getElementById('addresses-table');

if (table) {
    const host = table.closest('.skeleton-host');
    const columns = table.querySelectorAll('thead th').length;
    const $table = window.jQuery(table);

    // Bound before DataTables initialises, so the very first request is covered.
    $table.on('preXhr.dt', () => skeleton.show(host, columns));
    $table.on('draw.dt', () => skeleton.hide(host));
    $table.on('xhr.dt', () => skeleton.hide(host));
}

// A submitted form leaves the page waiting on the server with nothing on screen
// until the next one paints. The bar waits a beat before appearing so a fast
// response does not flash it, and the sweep is the same one the skeletons use.
const progress = document.querySelector('.page-progress');

if (progress) {
    document.addEventListener('submit', () => {
        window.setTimeout(() => progress.classList.add('is-visible'), 120);
    });
}

// The addresses table is rendered over ajax, so the delete buttons only exist
// after a draw. One shared modal is re-pointed at whichever row was clicked.
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('deleteAddressModal');

    if (!modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;

        if (!trigger) {
            return;
        }

        modal.querySelector('#deleteAddressForm').action = trigger.dataset.deleteAction;
        modal.querySelector('#deleteAddressLabel').textContent = trigger.dataset.deleteLabel;
    });
});
