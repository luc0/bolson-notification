// Análisis section: one line chart per price series (median + p25-p75 band), data from data-market-chart.
import Chart from 'chart.js/auto';

const isDark = () => document.documentElement.classList.contains('dark');

const formatPrice = (value, symbol) =>
    symbol + new Intl.NumberFormat('es-AR', { maximumFractionDigits: value < 10 ? 2 : 0 }).format(value);

function renderChart(canvas) {
    const chart = JSON.parse(canvas.dataset.marketChart);
    const points = chart.points;
    const textColor = isDark() ? '#9ca3af' : '#6b7280';
    const gridColor = isDark() ? 'rgba(156, 163, 175, 0.15)' : 'rgba(107, 114, 128, 0.15)';
    const lineColor = '#2563eb';
    const bandColor = 'rgba(37, 99, 235, 0.15)';

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map(point => point.month),
            datasets: [
                // The band is drawn by filling the p25 line up to the p75 one ('-1' = previous dataset).
                { label: 'p75', data: points.map(point => point.p75), borderWidth: 0, pointRadius: 0, fill: false },
                { label: 'p25', data: points.map(point => point.p25), borderWidth: 0, pointRadius: 0, backgroundColor: bandColor, fill: '-1' },
                { label: 'Mediana', data: points.map(point => point.median), borderColor: lineColor, backgroundColor: lineColor, borderWidth: 2, pointRadius: 3, tension: 0.2 },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: item => item.dataset.label === 'Mediana',
                    callbacks: {
                        label: item => `Mediana: ${formatPrice(item.parsed.y, chart.symbol)}`,
                        afterLabel: item => {
                            const point = points[item.dataIndex];
                            return [
                                `50% central: ${formatPrice(point.p25, chart.symbol)} a ${formatPrice(point.p75, chart.symbol)}`,
                                `Promedio: ${formatPrice(point.average, chart.symbol)}`,
                                `${point.count} ${point.count === 1 ? 'aviso' : 'avisos'}`,
                            ];
                        },
                    },
                },
            },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor, callback: value => formatPrice(value, chart.symbol) }, grid: { color: gridColor } },
            },
        },
    });
}

document.querySelectorAll('[data-market-chart]').forEach(renderChart);
