/**
 * Caja (admin/caja). Todo es SSR (filtros y paginación son GET normales); este script solo añade:
 *
 * - Filtros sin botón: al cambiar un select o una fecha se envía el formulario; la búsqueda se
 *   envía al dejar de escribir (o con Enter). Sin JS se muestra el botón Filtrar.
 * - Ojo del saldo: oculta/muestra el monto y recuerda la preferencia en este navegador.
 * - Clic en una fila → panel lateral con el detalle pedido por fetch; la URL guarda
 *   ?movimiento=ID (replaceState) para que recargar mantenga el detalle abierto.
 */
const pagina = document.querySelector('[data-caja-page]');
const filtros = pagina.querySelector('[data-caja-filtros]');
const panel = pagina.querySelector('[data-caja-panel]');
const velo = pagina.querySelector('[data-caja-velo]');
const plantillaSkeleton = pagina.querySelector('[data-caja-panel-skeleton]');
const saldo = pagina.querySelector('[data-caja-saldo]');
const CLAVE_OCULTO = 'ecolact.caja.saldoOculto';

let solicitudActual = null;

// ------------------------------------------------ filtros automáticos

let esperaBusqueda = null;

filtros.querySelectorAll('select, input[type="date"]').forEach((campo) => {
    campo.addEventListener('change', () => filtros.requestSubmit());
});

filtros.querySelector('input[name="q"]')?.addEventListener('input', () => {
    clearTimeout(esperaBusqueda);
    esperaBusqueda = setTimeout(() => filtros.requestSubmit(), 500);
});

filtros.addEventListener('submit', () => {
    // Una URL limpia: sin parámetros vacíos ni la página/detalle anterior.
    filtros.querySelectorAll('input, select').forEach((campo) => {
        campo.disabled = campo.value === '';
    });
});

// ------------------------------------------------ ojo del saldo

function leerOculto() {
    try {
        return window.localStorage.getItem(CLAVE_OCULTO) === '1';
    } catch {
        return false;
    }
}

function aplicarOculto(oculto) {
    const boton = saldo.querySelector('[data-caja-ojo]');
    const texto = oculto ? 'Mostrar saldo' : 'Ocultar saldo';

    saldo.classList.toggle('is-oculto', oculto);
    boton.setAttribute('aria-pressed', String(oculto));
    boton.setAttribute('aria-label', texto);
    boton.title = texto;
}

aplicarOculto(leerOculto());

saldo.querySelector('[data-caja-ojo]').addEventListener('click', () => {
    const oculto = ! saldo.classList.contains('is-oculto');
    aplicarOculto(oculto);

    try {
        window.localStorage.setItem(CLAVE_OCULTO, oculto ? '1' : '0');
    } catch {
        // Sin almacenamiento (modo privado): la preferencia dura solo esta visita.
    }
});

// ------------------------------------------------ panel de detalle

function actualizarUrl(id) {
    const url = new URL(window.location.href);

    if (id === null) {
        url.searchParams.delete('movimiento');
    } else {
        url.searchParams.set('movimiento', id);
    }

    window.history.replaceState(window.history.state, '', url);
}

function marcarSeleccionada(id) {
    pagina.querySelectorAll('[data-caja-fila]').forEach((fila) => {
        fila.classList.toggle('is-selected', fila.dataset.movimientoId === String(id));
    });
}

function abrirPanel() {
    panel.classList.add('is-abierto');
    panel.removeAttribute('aria-hidden');
    velo.hidden = false;
    document.body.classList.add('caja-drawer-abierto');
}

function cerrarPanel() {
    solicitudActual?.abort();
    panel.classList.remove('is-abierto');
    panel.setAttribute('aria-hidden', 'true');
    velo.hidden = true;
    document.body.classList.remove('caja-drawer-abierto');
    marcarSeleccionada(null);
    actualizarUrl(null);
}

async function mostrarDetalle(id) {
    solicitudActual?.abort();
    solicitudActual = new AbortController();

    marcarSeleccionada(id);
    actualizarUrl(id);
    panel.replaceChildren(plantillaSkeleton.content.cloneNode(true));
    abrirPanel();

    try {
        const respuesta = await fetch(pagina.dataset.urlPanel.replace('__ID__', id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            signal: solicitudActual.signal,
        });

        if (! respuesta.ok) {
            throw new Error(`HTTP ${respuesta.status}`);
        }

        panel.innerHTML = await respuesta.text();
        panel.querySelector('[data-caja-cerrar]')?.focus();
    } catch (error) {
        if (error.name !== 'AbortError') {
            panel.innerHTML = '<div class="caja-detalle"><div class="caja-detalle-cabecera"><p class="caja-detalle-kicker">Detalle del movimiento</p><button type="button" class="caja-cerrar" data-caja-cerrar aria-label="Cerrar detalle">✕</button></div><p class="caja-vacio">No se pudo cargar el detalle. Inténtalo de nuevo.</p></div>';
        }
    }
}

pagina.addEventListener('click', (evento) => {
    const fila = evento.target.closest('[data-caja-fila]');

    if (fila && ! evento.ctrlKey && ! evento.metaKey && ! evento.shiftKey) {
        evento.preventDefault();
        mostrarDetalle(fila.dataset.movimientoId);

        return;
    }

    if (evento.target.closest('[data-caja-cerrar]') || evento.target === velo) {
        cerrarPanel();
    }
});

document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && panel.classList.contains('is-abierto')) {
        cerrarPanel();
    }
});

if (panel.classList.contains('is-abierto')) {
    document.body.classList.add('caja-drawer-abierto');
}
