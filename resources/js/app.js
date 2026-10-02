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

// Leaflet is a third of the bundle and only one page uses it, so it is pulled in
// on demand rather than shipped to every visitor.
if (document.querySelector('[data-address-map]')) {
    import('./map');
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
