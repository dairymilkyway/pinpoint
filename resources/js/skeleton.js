/**
 * Shared loading-state helper.
 *
 * A host element carries .skeleton-host and one .skeleton-overlay child. These
 * two calls fill that overlay with shimmer rows shaped like the content beneath
 * and toggle .is-loading on the host. All the visuals live in the .skeleton
 * rules in app.scss, so a new surface only needs the two classes and a call to
 * show()/hide() - nothing here is specific to the address table.
 */

/** Builds `rows` shimmer rows of `columns` cells each. */
export function buildRows(columns, rows = 6) {
    const fragment = document.createDocumentFragment();

    for (let rowIndex = 0; rowIndex < rows; rowIndex += 1) {
        const row = document.createElement('div');
        row.className = 'skeleton-row';

        for (let columnIndex = 0; columnIndex < columns; columnIndex += 1) {
            const cell = document.createElement('span');
            cell.className = 'skeleton skeleton--row';
            row.appendChild(cell);
        }

        fragment.appendChild(row);
    }

    return fragment;
}

export function show(host, columns) {
    if (!host) {
        return;
    }

    const overlay = host.querySelector('.skeleton-overlay');

    if (overlay && columns) {
        overlay.replaceChildren(buildRows(columns));
    }

    host.classList.add('is-loading');
}

export function hide(host) {
    host?.classList.remove('is-loading');
}
