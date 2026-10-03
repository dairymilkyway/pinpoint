import {
    BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip,
} from 'chart.js';

// Only the pieces a horizontal bar needs are registered. Chart.js bundles what
// it is given, so an unregistered controller or scale costs nothing.
Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// Read the theme rather than restating it. The chart then follows the token
// block in app.scss, which is where every other colour on the page comes from.
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

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

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    Chart.defaults.font.family = token('--bs-body-font-family');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: points.map((point) => point.label),
            datasets: [{
                data: points.map((point) => point.value),
                backgroundColor: token('--amber'),
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
            plugins: {
                // One series, so a legend would only restate the panel heading.
                legend: { display: false },
                tooltip: {
                    backgroundColor: token('--ink-800'),
                    borderColor: token('--line-strong'),
                    borderWidth: 1,
                    titleColor: token('--text'),
                    bodyColor: token('--text-dim'),
                    padding: 10,
                    displayColors: false,
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

const element = document.querySelector('[data-region-chart]');

if (element) {
    // app.js only pulls this chunk in after the document has been parsed, so by
    // now DOMContentLoaded has almost always already fired.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => drawChart(element));
    } else {
        drawChart(element);
    }
}
