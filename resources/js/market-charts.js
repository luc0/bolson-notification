// Análisis section: one line chart per price series with a median line per place (data from data-market-chart).
// The place picker (chips to remove + search box to add) drives every chart and is kept in the `places` param.
import Chart from 'chart.js/auto';

const isDark = () => document.documentElement.classList.contains('dark');

// Fixed color per place (never by position), so a place keeps its color when others are added or removed.
// Comarca is the neutral reference line; cities take the categorical slots in City enum order.
const PLACE_COLORS = {
    comarca: { light: '#374151', dark: '#e5e7eb' },
    el_bolson: { light: '#2a78d6', dark: '#3987e5' },
    lago_puelo: { light: '#eb6834', dark: '#d95926' },
    el_hoyo: { light: '#1baf7a', dark: '#199e70' },
    epuyen: { light: '#eda100', dark: '#c98500' },
    cholila: { light: '#e87ba4', dark: '#d55181' },
};
const FALLBACK_COLOR = { light: '#6250d6', dark: '#9085e9' };

const colorOf = place => (PLACE_COLORS[place] ?? FALLBACK_COLOR)[isDark() ? 'dark' : 'light'];

const withAlpha = (hex, alpha) => hex + Math.round(alpha * 255).toString(16).padStart(2, '0');

const formatPrice = (value, symbol) =>
    symbol + new Intl.NumberFormat('es-AR', { maximumFractionDigits: value < 10 ? 2 : 0 }).format(value);

// "Epuyén" matches "epuyen".
const normalize = text => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

const picker = document.querySelector('[data-place-picker]');
const places = JSON.parse(picker.dataset.places);
let selected = JSON.parse(picker.dataset.selected);

const charts = [...document.querySelectorAll('[data-market-chart]')].map(canvas => ({
    canvas,
    data: JSON.parse(canvas.dataset.marketChart),
    instance: null,
}));

function datasetsFor(chart) {
    const lines = chart.data.lines.filter(line => selected.includes(line.place));
    const showsBand = lines.length === 1;
    const datasets = [];

    if (showsBand) {
        const [line] = lines;
        const values = key => line.points.map(point => point?.[key] ?? null);
        // The band is drawn by filling the p25 line up to the p75 one ('-1' = previous dataset).
        datasets.push(
            { label: 'p75', data: values('p75'), borderWidth: 0, pointRadius: 0, fill: false, isBand: true },
            { label: 'p25', data: values('p25'), borderWidth: 0, pointRadius: 0, backgroundColor: withAlpha(colorOf(line.place), 0.15), fill: '-1', isBand: true },
        );
    }

    // Comarca last so it's drawn on top of the cities.
    const ordered = [...lines.filter(line => line.place !== 'comarca'), ...lines.filter(line => line.place === 'comarca')];
    ordered.forEach(line => {
        const color = colorOf(line.place);
        datasets.push({
            label: line.label,
            place: line.place,
            points: line.points,
            data: line.points.map(point => point?.median ?? null),
            borderColor: color,
            backgroundColor: color,
            borderWidth: line.place === 'comarca' ? 3 : 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.2,
            spanGaps: true,
        });
    });

    return { datasets, showsBand, isEmpty: lines.length === 0 };
}

function tooltipLines(item, chart, showsBand) {
    const point = item.dataset.points[item.dataIndex];
    const avisos = `${point.count} ${point.count === 1 ? 'aviso' : 'avisos'}`;

    if (!showsBand) {
        return `${item.dataset.label}: ${formatPrice(point.median, chart.symbol)} (${avisos})`;
    }

    return [
        `Mediana: ${formatPrice(point.median, chart.symbol)}`,
        `50% central: ${formatPrice(point.p25, chart.symbol)} a ${formatPrice(point.p75, chart.symbol)}`,
        `Promedio: ${formatPrice(point.average, chart.symbol)}`,
        avisos,
    ];
}

function renderChart(chart) {
    const { datasets, showsBand, isEmpty } = datasetsFor(chart);
    chart.canvas.parentElement.hidden = isEmpty;
    chart.canvas.parentElement.nextElementSibling.hidden = !isEmpty;

    const textColor = isDark() ? '#9ca3af' : '#6b7280';
    const gridColor = isDark() ? 'rgba(156, 163, 175, 0.15)' : 'rgba(107, 114, 128, 0.15)';
    const options = {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: !showsBand,
                position: 'bottom',
                labels: { color: textColor, usePointStyle: true, pointStyle: 'line', filter: item => !datasets[item.datasetIndex]?.isBand },
            },
            tooltip: {
                filter: item => !item.dataset.isBand && item.raw !== null,
                itemSort: (a, b) => b.raw - a.raw,
                callbacks: {
                    title: items => items[0]?.label + (showsBand ? ` · ${items[0].dataset.label}` : ''),
                    label: item => tooltipLines(item, chart.data, showsBand),
                },
            },
        },
        scales: {
            x: { ticks: { color: textColor }, grid: { color: gridColor } },
            y: { ticks: { color: textColor, callback: value => formatPrice(value, chart.data.symbol) }, grid: { color: gridColor } },
        },
    };

    if (chart.instance) {
        chart.instance.data.datasets = datasets;
        chart.instance.options = options;
        chart.instance.update();
        return;
    }

    chart.instance = new Chart(chart.canvas, { type: 'line', data: { labels: chart.data.months, datasets }, options });
}

// Picker -------------------------------------------------------------------------------------------

const chipList = picker.querySelector('[data-place-chips]');
const search = picker.querySelector('[data-place-search]');
const optionList = picker.querySelector('[data-place-options]');

function swatch(place) {
    const dot = document.createElement('span');
    dot.className = 'inline-block h-2.5 w-2.5 shrink-0 rounded-full';
    dot.style.backgroundColor = colorOf(place);
    return dot;
}

function renderChips() {
    chipList.replaceChildren(...selected.map(place => {
        const chip = document.createElement('li');
        chip.className = 'filter-chip gap-2';
        chip.append(swatch(place), places[place]);

        const canRemove = selected.length > 1;
        if (!canRemove) {
            return chip;
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = '-mr-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-100';
        remove.setAttribute('aria-label', `Quitar ${places[place]}`);
        remove.textContent = '×';
        remove.addEventListener('click', () => setSelected(selected.filter(other => other !== place)));
        chip.append(remove);
        return chip;
    }));
}

function matchingPlaces() {
    const query = normalize(search.value);
    return Object.keys(places).filter(place => !selected.includes(place) && normalize(places[place]).includes(query));
}

function renderOptions() {
    const matches = matchingPlaces();
    const isOpen = document.activeElement === search;
    optionList.hidden = !isOpen;
    search.setAttribute('aria-expanded', String(isOpen));

    if (matches.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'px-3 py-2 text-sm text-gray-500 dark:text-gray-400';
        empty.textContent = Object.keys(places).length === selected.length ? 'Ya están todos los lugares' : 'Sin resultados';
        optionList.replaceChildren(empty);
        return;
    }

    optionList.replaceChildren(...matches.map(place => {
        const option = document.createElement('li');
        option.setAttribute('role', 'option');
        option.className = 'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
        option.append(swatch(place), places[place]);
        // mousedown (not click) so it runs before the input loses focus and closes the list.
        option.addEventListener('mousedown', event => {
            event.preventDefault();
            addPlace(place);
        });
        return option;
    }));
}

function addPlace(place) {
    search.value = '';
    setSelected([...selected, place]);
    renderOptions();
}

function syncUrl() {
    const showsEveryPlace = selected.length === Object.keys(places).length;
    const update = url => {
        showsEveryPlace ? url.searchParams.delete('places') : url.searchParams.set('places', selected.join(','));
        return url;
    };

    history.replaceState(null, '', update(new URL(location.href)));
    document.querySelectorAll('[data-keeps-places] a').forEach(link => {
        link.href = update(new URL(link.href));
    });
}

function setSelected(next) {
    // Keep the picker order (Comarca first, then cities) whatever the order they were added in.
    selected = Object.keys(places).filter(place => next.includes(place));
    renderChips();
    charts.forEach(renderChart);
    syncUrl();
}

search.addEventListener('focus', renderOptions);
search.addEventListener('input', renderOptions);
search.addEventListener('blur', () => {
    optionList.hidden = true;
    search.setAttribute('aria-expanded', 'false');
});
search.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
        event.preventDefault();
        const [first] = matchingPlaces();
        first && addPlace(first);
    }
    if (event.key === 'Escape') {
        search.blur();
    }
});

renderChips();
charts.forEach(renderChart);
