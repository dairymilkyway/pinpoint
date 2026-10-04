import {
    BarController, BarElement, CategoryScale, Chart, LinearScale, LineController,
    LineElement, PointElement, Tooltip,
} from 'chart.js';

// Only the pieces these two charts need are registered. Chart.js bundles what
// it is given, so an unregistered controller or scale costs nothing.
Chart.register(
    BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Tooltip,
);

// Read the theme rather than restating it. The chart then follows the token
// block in app.scss, which is where every other colour on the page comes from.
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// The tooltip chrome both charts share, so the two cannot drift apart.
const tooltip = () => ({
    backgroundColor: token('--ink-800'),
    borderColor: token('--line-strong'),
    borderWidth: 1,
    titleColor: token('--text'),
    bodyColor: token('--text-dim'),
    padding: 10,
    displayColors: false,
});

function drawChart(element) {
    const canvas = element.querySelector('canvas');

    if (!canvas) {
        return;
    }

    let points = [];

    try {
        points = JSON.parse(element.dataset.points || '[]');
    } catch {
        points = [];
    }

    // Past the parse, so the panel stops shimmering whatever happens next.
    element.classList.remove('is-loading');

    if (!points.length) {
        element.classList.add('chart--empty');
        element.textContent = 'No addresses on file yet.';
        return;
    }

    // Height follows the bar count - sixteen regions need far more room than
    // two - so the labels stay readable instead of being crushed together.
    element.style.height = `${Math.max(220, points.length * 28 + 48)}px`;

    Chart.defaults.font.family = token('--bs-body-font-family');

    const labels = points.map((point) => point.label);

    // The region a bar click has focused, if any. It toggles, and the map
    // listens for the same choice, so the two views cannot disagree.
    let focused = null;

    const barColours = () => labels.map((label) => (
        label === focused ? token('--amber-hot') : token('--amber')
    ));

    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: points.map((point) => point.value),
                backgroundColor: barColours(),
                hoverBackgroundColor: token('--amber-hot'),
                borderRadius: 3,
                barPercentage: 0.72,
                categoryPercentage: 0.82,
            }],
        },
        options: {
            // Long region names read badly rotated under a column, so the bars
            // run horizontally and the labels sit on their own line.
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: reduced ? false : { duration: 320 },
            // A bar focuses the map, so the pointer is a control's - but only
            // over a bar, not over the panel's empty corners.
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            },
            onClick: (event, elements) => {
                if (!elements.length) {
                    return;
                }

                const label = labels[elements[0].index];

                // Toggle: the same bar again clears the focus rather than
                // re-applying it, so a reader can undo without a second control.
                focused = focused === label ? null : label;

                chart.data.datasets[0].backgroundColor = barColours();
                chart.update();

                // The map is a separate lazily imported module and cannot be
                // imported here, so the choice travels as a document event.
                document.dispatchEvent(new CustomEvent('region:focus', {
                    detail: { region: focused },
                }));
            },
            plugins: {
                // One series, so a legend would only restate the panel heading.
                legend: { display: false },
                tooltip: {
                    ...tooltip(),
                    callbacks: {
                        label: (item) => `${item.parsed.x} ${item.parsed.x === 1 ? 'address' : 'addresses'}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: token('--line'), drawTicks: false },
                    border: { display: false },
                    // Addresses are whole things; "2.5" on an axis is a lie.
                    ticks: { color: token('--text-faint'), precision: 0, padding: 8 },
                },
                y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: token('--text-dim'), padding: 8, font: { size: 11 } },
                },
            },
        },
    });
}

function drawSparkline(element) {
    const canvas = element.querySelector('canvas');

    if (!canvas) {
        return;
    }

    let points = [];

    try {
        points = JSON.parse(element.dataset.points || '[]');
    } catch {
        points = [];
    }

    if (!points.length) {
        return;
    }

    Chart.defaults.font.family = token('--bs-body-font-family');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map((point) => point.label),
            datasets: [{
                data: points.map((point) => point.value),
                borderColor: token('--amber'),
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 3,
                pointHoverBackgroundColor: token('--amber-hot'),
                pointHoverBorderColor: token('--amber-hot'),
                tension: 0.3,
                fill: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduced ? false : { duration: 320 },
            // A sparkline is a shape, not a chart to read values off. Axes and
            // grid would be noise at this size, so both are off; hover is the
            // one way to read a single point, so the tooltip stays.
            scales: {
                x: { display: false },
                y: { display: false },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltip(),
                    callbacks: {
                        label: (item) => `${item.parsed.y} ${item.parsed.y === 1 ? 'address' : 'addresses'}`,
                    },
                },
            },
        },
    });
}

// app.js only pulls this chunk in after the document has been parsed, so by now
// DOMContentLoaded has almost always already fired.
function whenReady(draw) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        draw();
    }
}

const chartElement = document.querySelector('[data-region-chart]');

if (chartElement) {
    whenReady(() => drawChart(chartElement));
}

const sparkElement = document.querySelector('[data-metric-spark]');

if (sparkElement) {
    whenReady(() => drawSparkline(sparkElement));
}
