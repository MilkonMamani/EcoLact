import { Chart, BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip } from 'chart.js';
import '../../css/pages/admin-dashboard.css';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip);

const root = document.querySelector('[data-admin-dashboard]');

if (root) {
    const days = JSON.parse(root.dataset.litrosPorDia);
    const quality = JSON.parse(root.dataset.distribucionCalidad);
    const styles = getComputedStyle(document.documentElement);
    const color = (name) => styles.getPropertyValue(`--color-${name}`).trim();
    const number = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 2 });
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const common = {
        responsive: true,
        maintainAspectRatio: false,
        animation: reducedMotion ? false : { duration: 300 },
        locale: 'es-PE',
        font: { family: styles.getPropertyValue('--font-sans').trim() },
    };

    root.querySelector('[data-custom-period]')?.addEventListener('click', () => {
        root.querySelector('#dashboard-personalizado').open = true;
        root.querySelector('[name="desde"]').focus();
    });

    const volumeCanvas = root.querySelector('[data-volume-chart]');
    if (volumeCanvas && days.length) {
        volumeCanvas.parentElement.hidden = false;
        const volumeChart = new Chart(volumeCanvas, {
            type: 'bar',
            data: {
                labels: days.map((day) => day.etiqueta),
                datasets: [{
                    label: 'Litros',
                    data: days.map((day) => day.litros),
                    backgroundColor: color('primary'),
                    hoverBackgroundColor: color('primary-dark'),
                    borderRadius: 5,
                    maxBarThickness: 42,
                }],
            },
            options: {
                ...common,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: (items) => days[items[0].dataIndex].fecha,
                            label: (item) => `${item.dataset.label}: ${number.format(item.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: color('text-muted'), maxTicksLimit: 8 } },
                    y: { beginAtZero: true, border: { display: false }, title: { display: true, text: 'Litros' }, ticks: { color: color('text-muted') } },
                },
            },
        });
        root.querySelector('[data-chart-table]').open = false;

        root.querySelectorAll('[data-chart-metric]').forEach((button) => {
            button.addEventListener('click', () => {
                const metric = button.dataset.chartMetric;
                const label = metric === 'entregas' ? 'N° de entregas' : 'Litros';
                volumeChart.data.datasets[0].data = days.map((day) => day[metric]);
                volumeChart.data.datasets[0].label = label;
                volumeChart.options.scales.y.title.text = label;
                volumeChart.options.scales.y.ticks.precision = metric === 'entregas' ? 0 : 2;
                volumeChart.update();
                root.querySelectorAll('[data-chart-metric]').forEach((control) => {
                    control.setAttribute('aria-pressed', String(control === button));
                });
            });
        });
    }

    const qualityCanvas = root.querySelector('[data-quality-chart]');
    if (qualityCanvas && quality.some((item) => item.cantidad > 0)) {
        qualityCanvas.parentElement.hidden = false;
        new Chart(qualityCanvas, {
            type: 'doughnut',
            data: {
                labels: quality.map((item) => item.resultado),
                datasets: [{
                    data: quality.map((item) => item.cantidad),
                    backgroundColor: [color('success'), color('warning'), color('danger')],
                    borderColor: color('surface'),
                    borderWidth: 4,
                    hoverOffset: reducedMotion ? 0 : 3,
                }],
            },
            options: {
                ...common,
                cutout: '74%',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (item) => `${item.label}: ${item.parsed} (${number.format(quality[item.dataIndex].porcentaje)}%)`,
                        },
                    },
                },
            },
        });
    }
}
