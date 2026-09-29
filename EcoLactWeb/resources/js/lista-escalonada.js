/**
 * Skeleton breve + entrada escalonada de las filas de una tabla renderizada por el servidor
 * (mismo efecto que Acopio). Lo usan las pantallas "listado + panel" y Asistencia.
 *
 * - La tabla llega con .is-loading: el CSS muestra el skeleton y oculta las filas.
 * - El skeleton dura como mucho SKELETON_MS desde el inicio de la navegación; si la página
 *   tardó más en llegar, desaparece al instante.
 * - Cada fila entra con retraso min(i × PASO_MS, TOPE_MS) vía la variable CSS `variable`.
 * - Enviar `filtros` o pulsar alguno de `enlaces` vuelve a poner el skeleton mientras llega
 *   la nueva página.
 */
const SKELETON_MS = 280;
const PASO_MS = 25;
const TOPE_MS = 300;
const DURACION_MS = 280;

export function iniciarListaEscalonada(tabla, { fila, variable, filtros = null, enlaces = [] }) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const mostrarFilas = () => {
        if (!tabla?.classList.contains('is-loading')) {
            return;
        }

        if (reducedMotion) {
            tabla.classList.remove('is-loading');

            return;
        }

        tabla.querySelectorAll(fila).forEach((elemento, indice) => {
            elemento.style.setProperty(variable, `${Math.min(indice * PASO_MS, TOPE_MS)}ms`);
        });
        tabla.classList.add('is-revealing');
        tabla.classList.remove('is-loading');
        window.setTimeout(() => tabla.classList.remove('is-revealing'), TOPE_MS + DURACION_MS + 50);
    };

    const mostrarSkeleton = () => {
        if (tabla && !reducedMotion) {
            tabla.classList.remove('is-revealing');
            tabla.classList.add('is-loading');
        }
    };

    if (tabla) {
        window.setTimeout(mostrarFilas, reducedMotion ? 0 : Math.max(0, SKELETON_MS - performance.now()));

        // Al volver con "atrás" (bfcache) la página se restaura tal cual: no dejar el skeleton puesto.
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                tabla.classList.remove('is-loading', 'is-revealing');
            }
        });
    }

    filtros?.addEventListener('submit', mostrarSkeleton);
    enlaces.forEach((enlace) => {
        enlace.addEventListener('click', (event) => {
            if (!event.metaKey && !event.ctrlKey && !event.shiftKey && event.button === 0) {
                mostrarSkeleton();
            }
        });
    });

    return { mostrarSkeleton };
}
