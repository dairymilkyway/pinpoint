// Self-hosted typography, no remote font CDN.
import '@fontsource-variable/space-grotesk';
import '@fontsource-variable/jetbrains-mono';

import './bootstrap';

import { Modal } from 'bootstrap';

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

// The dashboard's region chart. chart.js is the largest dependency after
// Leaflet and only two of the three roles draw it, so it is fetched on demand
// alongside the map rather than shipped to every visitor.
const chartElement = document.querySelector('[data-region-chart]');

if (chartElement) {
    import('./chart').catch(() => {
        // Without this the panel would shimmer forever if the chunk 404s.
        chartElement.classList.remove('is-loading');
        chartElement.classList.add('chart--failed');
        chartElement.textContent = 'Could not load the chart.';
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

// The addresses table is rendered over ajax, so the delete and request buttons
// only exist after a draw. Each modal is re-pointed at whichever row was
// clicked, and each declares the elements that get filled rather than naming
// them here - so adding a second modal costs no JavaScript.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-row-modal]').forEach((modal) => {
        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;

            if (!trigger) {
                return;
            }

            const form = modal.querySelector(modal.dataset.formTarget);
            const label = modal.querySelector(modal.dataset.labelTarget);
            const value = modal.querySelector(modal.dataset.valueTarget);

            if (form && trigger.dataset.rowAction) {
                form.action = trigger.dataset.rowAction;
            }

            if (label) {
                label.textContent = trigger.dataset.rowLabel ?? '';
            }

            if (value) {
                value.value = trigger.dataset.rowValue ?? '';
            }
        });
    });
});

// A refusal is confirmed in a modal of its own rather than in a panel that
// unfolds inside the review modal. The two are siblings that take turns: the
// first is hidden, and the second opens once it has gone. A modal opened from
// inside a modal strands the first one's backdrop over the page, so this is a
// swap rather than a stack.
//
// Symmetric on purpose - stepping back to the proposal is the same click as
// leaving it, so a reader who opens the reason and changes their mind lands back
// on what they were reading.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('[data-modal-swap]');

    if (!trigger) {
        return;
    }

    const from = document.getElementById(trigger.dataset.modalSwapFrom);
    const to = document.getElementById(trigger.dataset.modalSwap);

    if (!from || !to) {
        return;
    }

    from.addEventListener('hidden.bs.modal', () => Modal.getOrCreateInstance(to).show(), { once: true });
    Modal.getOrCreateInstance(from).hide();
});
