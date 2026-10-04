// Self-hosted typography, no remote font CDN.
import '@fontsource-variable/ibm-plex-sans';
import '@fontsource/ibm-plex-mono';

// The public pages' display serif. Per-weight imports: the latin- prefix pulls
// one font file each, where the bare 400.css would ship five scripts.
import '@fontsource/ibm-plex-serif/latin-400.css';
import '@fontsource/ibm-plex-serif/latin-600.css';

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
        // role=alert only on failure: on success Leaflet fills this panel and a
        // live region there would narrate every tile and marker.
        mapElement.setAttribute('role', 'alert');
        mapElement.textContent = 'Could not load the map.';
    });
}

// The region chart and the metric band's sparkline. chart.js is the largest
// dependency after Leaflet, so it is fetched on demand rather than shipped to
// every visitor; either host pulls the module in.
const chartElement = document.querySelector('[data-region-chart], [data-metric-spark]');

if (chartElement) {
    import('./chart').catch(() => {
        // Without this the panel would shimmer forever if the chunk 404s.
        chartElement.classList.remove('is-loading');
        chartElement.classList.add('chart--failed');
        chartElement.setAttribute('role', 'alert');
        chartElement.textContent = 'Could not load the chart.';
    });
}

// Flash messages arrive as toasts, already visible in the server-rendered
// markup. Only the success toast dismisses itself, which is all this module
// adds: the close button rides on Bootstrap's own dismiss handler. The module
// is tiny, but it is still fetched on demand, matching the feature modules
// above.
const toasts = document.querySelectorAll('[data-toast]');

if (toasts.length) {
    import('./toast').catch(() => {
        // The message stays on screen, only the auto-dismiss is lost. That is
        // the right way to fail: nothing is hidden.
    });
}

// The directory tables are filled over ajax on every search, sort and page
// change, so they are the surfaces that blank most often. DataTables fires
// preXhr before each request and draw once the rows are in. Both the addresses
// table and the users table are served here, so the module is not per-page.
const tables = document.querySelectorAll('#addresses-table, #users-table');

if (tables.length) {
    const $ = window.jQuery;

    tables.forEach((table) => {
        const host = table.closest('.skeleton-host');
        const columns = table.querySelectorAll('thead th').length;
        const $table = $(table);

        // A first run and a search that matched nothing are different screens.
        // The copy travels on the host, so the wording is the screen's and this
        // module only decides which one applies.
        if (host?.dataset.tableEmpty || host?.dataset.tableZero) {
            // A failed fetch is answered by the panel's own message, so the
            // stock blocking alert is off.
            $.fn.dataTableExt.errMode = 'none';

            $.fn.dataTable.defaults.language = {
                ...$.fn.dataTable.defaults.language,
                // DataTables picks emptyTable when no rows exist at all and
                // zeroRecords when a filter removed them.
                emptyTable: host.dataset.tableEmpty || 'Nothing on file yet.',
                zeroRecords: host.dataset.tableZero || 'Nothing matches that search.',
                // The panel has its own shimmer while loading; the stock text
                // would sit underneath it.
                loadingRecords: '',
                processing: '',
                infoEmpty: '',
            };
        }

        // Bound before DataTables initialises, so the very first request is covered.
        $table.on('preXhr.dt', () => {
            host?.classList.remove('table--failed');
            skeleton.show(host, columns);
        });
        $table.on('draw.dt', () => {
            skeleton.hide(host);
            host?.classList.remove('table--failed');
        });
        $table.on('xhr.dt', (event, settings, json) => {
            skeleton.hide(host);

            // A failed request hands back no payload. The panel shows its own
            // message naming the cause rather than a table that looks empty.
            const failed = json === null;

            host?.classList.toggle('table--failed', failed);

            // The panel's words are only revealed by a display change, which
            // assistive tech does not reliably announce. Copy them into a live
            // region that is always rendered, so the announcement rides on the
            // text changing instead.
            const alert = host?.querySelector('[data-table-alert]');

            if (alert) {
                const panel = host.querySelector('.table-state--failed');

                alert.textContent = failed && panel
                    ? Array.from(panel.children).map((line) => line.textContent.trim()).join(' ')
                    : '';
            }
        });
    });
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
