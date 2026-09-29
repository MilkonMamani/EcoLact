/**
 * Pantallas "listado + panel" del admin (hoy Proveedores; el marcado es genérico para
 * poder reutilizarlo en Acopiadores). Todo es SSR: búsqueda, filtros y paginación son
 * GET normales; este script solo añade:
 *
 * - Skeleton + entrada escalonada de las filas (igual que Acopio): el HTML llega con
 *   .is-loading, el skeleton dura como mucho SKELETON_MS desde el inicio de la navegación
 *   y cada fila entra con retraso min(i × PASO_MS, TOPE_MS).
 * - Click (o Enter) en una fila → el panel derecho muestra un skeleton, pide el fragmento
 *   [data-url-panel] por fetch y lo reemplaza con una transición suave. La URL se actualiza
 *   con ?<param>=ID (replaceState) para que recargar o volver tras guardar mantenga la
 *   selección. Sin JS, el nombre de la fila es un enlace normal a esa misma URL.
 * - Pestañas del panel; la pestaña elegida se conserva al cambiar de registro.
 */
import { pintarQrs } from '../qr.js';
import { iniciarListaEscalonada } from '../lista-escalonada.js';

const PANEL_MIN_MS = 180;

const pagina = document.querySelector('[data-fichas-page]');
const tabla = pagina.querySelector('[data-fichas-tabla]');
const panel = pagina.querySelector('[data-fichas-panel]');
const plantillaSkeleton = pagina.querySelector('[data-fichas-panel-skeleton]');
const plantillaVacio = pagina.querySelector('[data-fichas-panel-vacio]');
const parametro = pagina.dataset.fichasParam;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const escritorio = window.matchMedia('(min-width: 1280px)');

let pestanaActiva = 'resumen';
let solicitudActual = null;

// ------------------------------------------------ listado: skeleton + entrada escalonada

iniciarListaEscalonada(tabla, {
    fila: '[data-fichas-fila]',
    variable: '--fichas-delay',
    filtros: pagina.querySelector('[data-fichas-filtros]'),
    enlaces: [...pagina.querySelectorAll('.ecolact-pager a, [data-fichas-filtros] a')],
});

// ------------------------------------------------ panel derecho

function marcarSeleccionada(id) {
    tabla?.querySelectorAll('[data-fichas-fila]').forEach((fila) => {
        const seleccionada = fila.dataset.fichaId === String(id);
        fila.classList.toggle('is-selected', seleccionada);
        fila.setAttribute('aria-selected', String(seleccionada));
    });
}

function actualizarUrl(id) {
    const url = new URL(window.location.href);

    if (id === null) {
        url.searchParams.delete(parametro);
    } else {
        url.searchParams.set(parametro, id);
    }

    window.history.replaceState(window.history.state, '', url);
}

function activarPestana(clave) {
    const botones = panel.querySelectorAll('[data-fichas-tab]');

    if (![...botones].some((boton) => boton.dataset.fichasTab === clave)) {
        clave = 'resumen';
    }

    pestanaActiva = clave;
    botones.forEach((boton) => {
        const activa = boton.dataset.fichasTab === clave;
        boton.setAttribute('aria-selected', String(activa));
        boton.tabIndex = activa ? 0 : -1;
    });
    panel.querySelectorAll('[data-fichas-tab-panel]').forEach((seccion) => {
        seccion.hidden = seccion.dataset.fichasTabPanel !== clave;
    });
}

function pintarPanel(html) {
    panel.innerHTML = html;

    const contenido = panel.firstElementChild;
    if (contenido && !reducedMotion) {
        contenido.classList.add('is-entering');
        contenido.addEventListener('animationend', () => contenido.classList.remove('is-entering'), { once: true });
    }

    activarPestana(pestanaActiva);
    pintarQrs(panel);

    // Para scripts que enganchan comportamiento al contenido del panel (p. ej. el calendario de asistencia).
    document.dispatchEvent(new CustomEvent('fichas:panel-actualizado', { detail: { panel } }));
}

function esperar(ms) {
    return new Promise((resolver) => window.setTimeout(resolver, ms));
}

/**
 * Muestra en el panel el registro de `fila`. Con `url` se pide esa variante del fragmento
 * (p. ej. otro mes del calendario); con `forzar` se vuelve a pedir aunque ya esté a la vista
 * (tras activar/desactivar, para que el panel refleje el estado y la zona nuevos); con
 * `desplazar: false` no se hace scroll al panel en pantallas angostas (p. ej. al elegir desde un mapa).
 */
async function seleccionar(fila, { url = null, forzar = false, desplazar = true } = {}) {
    const id = fila.dataset.fichaId;

    if (!forzar && url === null && panel.querySelector(`[data-fichas-contenido][data-ficha-id="${id}"]`) && !panel.querySelector('.fichas-panel-cargando')) {
        return;
    }

    solicitudActual?.abort();
    const solicitud = new AbortController();
    solicitudActual = solicitud;

    marcarSeleccionada(id);
    actualizarUrl(id);
    panel.replaceChildren(plantillaSkeleton.content.cloneNode(true));
    panel.setAttribute('aria-busy', 'true');

    if (!escritorio.matches && !forzar && desplazar) {
        panel.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
    }

    try {
        const [respuesta] = await Promise.all([
            fetch(url ?? fila.dataset.urlPanel, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: solicitud.signal }),
            esperar(reducedMotion ? 0 : PANEL_MIN_MS),
        ]);

        if (!respuesta.ok) {
            throw new Error('respuesta no válida');
        }

        const html = await respuesta.text();

        if (solicitudActual === solicitud) {
            pintarPanel(html);
        }
    } catch (error) {
        if (error.name !== 'AbortError' && solicitudActual === solicitud) {
            panel.innerHTML = '<div class="fichas-panel-vacio"><p class="font-semibold text-text">No se pudo cargar el registro</p><p>Revisa tu conexión e inténtalo de nuevo.</p></div>';
        }
    } finally {
        if (solicitudActual === solicitud) {
            panel.removeAttribute('aria-busy');
        }
    }
}

function cerrarPanel() {
    solicitudActual?.abort();
    solicitudActual = null;
    marcarSeleccionada(null);
    actualizarUrl(null);
    pintarPanel(plantillaVacio.innerHTML);
}

function filaPorId(id) {
    return tabla?.querySelector(`[data-fichas-fila][data-ficha-id="${id}"]`) ?? null;
}

/** Controles propios dentro de la fila (p. ej. el switch de estado): no seleccionan la fila. */
function esControlInterno(elemento) {
    return Boolean(elemento.closest('button, input, select, textarea, a:not([data-fichas-seleccionar])'));
}

tabla?.addEventListener('click', (event) => {
    const fila = event.target.closest('[data-fichas-fila]');

    if (!fila || esControlInterno(event.target) || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
        return;
    }

    event.preventDefault();
    seleccionar(fila);
});

tabla?.addEventListener('keydown', (event) => {
    const fila = event.target.closest('[data-fichas-fila]');

    if (!fila || (event.target !== fila && esControlInterno(event.target))) {
        return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        seleccionar(fila);
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        (event.key === 'ArrowDown' ? fila.nextElementSibling : fila.previousElementSibling)?.focus();
    }
});

// Otro script pide volver a pintar un registro (detail: { id }): solo si es el que está a la vista.
document.addEventListener('fichas:recargar', (event) => {
    const fila = filaPorId(event.detail?.id);

    if (fila && panel.querySelector(`[data-fichas-contenido][data-ficha-id="${event.detail.id}"]`)) {
        seleccionar(fila, { forzar: true });
    }
});

// Otro script pide seleccionar un registro (detail: { id, desplazar }), p. ej. el mapa general de Zonas.
// Si la fila no está en la página actual de la tabla, se navega a la URL con ese registro seleccionado.
document.addEventListener('fichas:seleccionar', (event) => {
    const id = event.detail?.id;

    if (id === undefined || id === null) {
        return;
    }

    const fila = filaPorId(id);

    if (fila) {
        seleccionar(fila, { desplazar: event.detail.desplazar ?? true });

        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set(parametro, id);
    window.location.assign(url);
});

panel.addEventListener('click', (event) => {
    const pestana = event.target.closest('[data-fichas-tab]');

    if (pestana) {
        activarPestana(pestana.dataset.fichasTab);

        return;
    }

    if (event.target.closest('[data-fichas-cerrar]')) {
        cerrarPanel();

        return;
    }

    // Enlaces del panel que apuntan a esta misma pantalla (p. ej. "mes anterior" del calendario):
    // se resuelven dentro del panel, pidiendo el fragmento con esos parámetros.
    const enlace = event.target.closest('a[href]');
    const contenido = panel.querySelector('[data-fichas-contenido]');
    const fila = contenido ? filaPorId(contenido.dataset.fichaId) : null;

    if (!enlace || !fila || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
        return;
    }

    const destino = new URL(enlace.href, window.location.href);
    const urlPanel = new URL(fila.dataset.urlPanel, window.location.href);

    if (destino.origin === urlPanel.origin && (destino.pathname === window.location.pathname || destino.pathname === urlPanel.pathname)) {
        event.preventDefault();
        urlPanel.search = destino.search;
        seleccionar(fila, { url: urlPanel.toString() });
    }
});

panel.addEventListener('keydown', (event) => {
    const pestana = event.target.closest('[data-fichas-tab]');

    if (!pestana || (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft')) {
        return;
    }

    const botones = [...panel.querySelectorAll('[data-fichas-tab]')];
    const siguiente = botones[(botones.indexOf(pestana) + (event.key === 'ArrowRight' ? 1 : -1) + botones.length) % botones.length];
    activarPestana(siguiente.dataset.fichasTab);
    siguiente.focus();
});
