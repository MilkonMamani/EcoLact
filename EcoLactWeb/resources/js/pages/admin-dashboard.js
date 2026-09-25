import { Chart, CategoryScale, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip } from 'chart.js';
import '../../css/pages/admin-dashboard.css';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip);

const root = document.querySelector('[data-admin-dashboard]');
const canvas = root?.querySelector('[data-acopio-chart]');

/**
 * Franja gris detrás de los intervalos que aún no ocurren: así un día futuro
 * (sin valor, la línea se corta) no se confunde con un día ya transcurrido
 * sin entregas (que sí vale 0).
 */
const franjaFutura = {
    id: 'franjaFutura',
    beforeDatasetsDraw(chart, _args, options) {
        const { ctx, chartArea, scales } = chart;
        const futuros = options.futuros ?? [];
        const primero = futuros.indexOf(true);

        if (primero === -1) {
            return;
        }

        const paso = futuros.length > 1 ? scales.x.getPixelForValue(1) - scales.x.getPixelForValue(0) : chartArea.width;
        const inicio = Math.max(chartArea.left, scales.x.getPixelForValue(primero) - paso / 2);

        ctx.save();
        ctx.fillStyle = options.color;
        ctx.fillRect(inicio, chartArea.top, chartArea.right - inicio, chartArea.bottom - chartArea.top);
        ctx.restore();
    },
};

if (canvas) {
    const intervalos = JSON.parse(root.dataset.acopio);
    const porHoras = root.dataset.porHoras === 'true';
    const styles = getComputedStyle(root);
    const token = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;
    const litros = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const muted = token('--dash-muted', '#667085');
    const verde = token('--dash-green-bright', '#01aa62');

    try {
        canvas.parentElement.hidden = false;
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: intervalos.map((intervalo) => intervalo.etiqueta),
                datasets: [{
                    label: 'Litros',
                    data: intervalos.map((intervalo) => intervalo.litros),
                    borderColor: verde,
                    borderWidth: 2,
                    tension: 0.35,
                    cubicInterpolationMode: 'monotone',
                    fill: 'origin',
                    backgroundColor: (context) => {
                        const { ctx, chartArea } = context.chart;

                        if (!chartArea) {
                            return 'rgb(1 170 98 / 0.1)';
                        }

                        const degradado = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        degradado.addColorStop(0, 'rgb(1 170 98 / 0.18)');
                        degradado.addColorStop(1, 'rgb(1 170 98 / 0.01)');

                        return degradado;
                    },
                    pointRadius: intervalos.length > 16 ? 2 : 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: verde,
                    pointBorderWidth: 1.5,
                    spanGaps: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reducedMotion ? false : { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 4 } },
                plugins: {
                    franjaFutura: { futuros: intervalos.map((intervalo) => intervalo.futuro), color: token('--dash-future', '#f4f6f8') },
                    tooltip: {
                        backgroundColor: '#1d2939',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        filter: (item) => item.raw !== null,
                        callbacks: {
                            title: (items) => intervalos[items[0].dataIndex].titulo,
                            label: (item) => {
                                const intervalo = intervalos[item.dataIndex];
                                const entregas = intervalo.entregas === 1 ? '1 entrega' : `${intervalo.entregas} entregas`;

                                return `${litros.format(intervalo.litros)} L · ${entregas}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: (context) => (intervalos[context.index]?.futuro ? '#c0c7d0' : muted),
                            autoSkip: true,
                            maxRotation: 0,
                            maxTicksLimit: porHoras ? 12 : 16,
                            font: { size: 11 },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: intervalos.some((intervalo) => intervalo.litros > 0) ? undefined : 10,
                        border: { display: false },
                        grid: { color: '#eef1f4' },
                        ticks: {
                            color: muted,
                            font: { size: 11 },
                            maxTicksLimit: 6,
                            callback: (valor) => `${litros.format(valor).replace(/[.,]00$/, '')} L`,
                        },
                    },
                },
            },
            plugins: [franjaFutura],
        });
        const tabla = root.querySelector('[data-chart-table]');

        if (tabla) {
            tabla.open = false;
        }
    } catch (error) {
        canvas.parentElement.hidden = true;
        root.querySelector('[data-chart-error]').hidden = false;
        console.error(error);
    }
}
