/**
 * Acopio: skeleton breve + entrada escalonada de las filas renderizadas por
 * el servidor. La página sigue siendo SSR (búsqueda, filtros y paginación son
 * GET normales); esto solo decora la transición.
 *
 * - El HTML llega con .is-loading: se ve el skeleton y las filas están ocultas.
 * - El skeleton dura como mucho SKELETON_MS desde el inicio de la navegación;
 *   si la página tardó más en llegar, desaparece al instante (sin esperas).
 * - Cada fila entra con un retraso min(i × PASO_MS, TOPE_MS): las primeras
 *   muestran la cascada y la tabla completa termina en menos de ~0.6 s.
 */
import { getCsrfToken } from '../maps.js';

const SKELETON_MS = 280;
const PASO_MS = 25;
const TOPE_MS = 300;
const DURACION_MS = 280;

const tabla = document.querySelector('[data-acopio-tabla]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function mostrarFilas() {
    if (!tabla.classList.contains('is-loading')) {
        return;
    }

    if (reducedMotion) {
        tabla.classList.remove('is-loading');

        return;
    }

    tabla.querySelectorAll('[data-acopio-fila]').forEach((fila, indice) => {
        fila.style.setProperty('--acopio-delay', `${Math.min(indice * PASO_MS, TOPE_MS)}ms`);
    });
    tabla.classList.add('is-revealing');
    tabla.classList.remove('is-loading');
    window.setTimeout(() => tabla.classList.remove('is-revealing'), TOPE_MS + DURACION_MS + 50);
}

function mostrarSkeleton() {
    if (tabla && !reducedMotion) {
        tabla.classList.remove('is-revealing');
        tabla.classList.add('is-loading');
    }
}

if (tabla) {
    window.setTimeout(mostrarFilas, reducedMotion ? 0 : Math.max(0, SKELETON_MS - performance.now()));

    // Al volver con el botón "atrás" (bfcache) la página se restaura tal cual: no dejar el skeleton puesto.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            tabla.classList.remove('is-loading', 'is-revealing');
        }
    });
}

// Filtrar, limpiar o cambiar de página: el skeleton aparece mientras llega la nueva página.
document.querySelector('[data-acopio-filtros]')?.addEventListener('submit', mostrarSkeleton);
document.querySelectorAll('[data-acopio-page] .ecolact-pager a, [data-acopio-filtros] a, [data-acopio-page] thead a').forEach((enlace) => {
    enlace.addEventListener('click', (event) => {
        if (!event.metaKey && !event.ctrlKey && !event.shiftKey && event.button === 0) {
            mostrarSkeleton();
        }
    });
});

// ------------------------------------------------ modal "Ver entrega" / "Ver análisis"

/**
 * El ícono de la fila abre el modal sin navegar y carga su contenido
 * (fragmento admin.entregas.detalle, o el que indique data-url-detalle). El
 * modal es #modal-ver-entrega salvo que la fila indique otro en
 * data-modal-detalle (p. ej. Calidad > Análisis). Sin JS, el enlace sigue
 * llevando a la página de detalle. Abrir/cerrar el modal lo resuelve app.js
 * (data-modal); mientras está abierto, la fila queda resaltada.
 */
const modalDetalle = document.getElementById('modal-ver-entrega');
const contenidoDetalle = modalDetalle?.querySelector('[data-entrega-detalle-contenido]');

function desmarcarFilas() {
    document.querySelectorAll('[data-acopio-fila].is-seleccionada').forEach((fila) => fila.classList.remove('is-seleccionada'));
}

document.querySelectorAll('[data-acopio-page] [data-modal]').forEach((modal) => {
    new MutationObserver(() => {
        if (!modal.classList.contains('is-open')) {
            desmarcarFilas();
        }
    }).observe(modal, { attributes: true, attributeFilter: ['class'] });
});

document.addEventListener('click', (event) => {
    const enlace = event.target.closest('[data-entrega-ver]');
    const modal = enlace && document.getElementById(enlace.dataset.modalDetalle || 'modal-ver-entrega');

    if (!enlace || !modal || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
        return;
    }

    event.preventDefault();
    const contenido = modal.querySelector('[data-entrega-detalle-contenido]');
    desmarcarFilas();
    enlace.closest('[data-acopio-fila]')?.classList.add('is-seleccionada');
    contenido.innerHTML = '<div class="acopio-detalle-cargando"><span class="acopio-bone is-xl"></span><span class="acopio-bone is-lg"></span><span class="acopio-bone is-md"></span></div>';
    modal.classList.add('is-open');
    document.body.classList.add('overflow-hidden');

    fetch(enlace.dataset.urlDetalle, { headers: { Accept: 'text/html' } })
        .then((respuesta) => (respuesta.ok ? respuesta.text() : Promise.reject()))
        .then((html) => {
            contenido.innerHTML = html;
        })
        .catch(() => {
            contenido.innerHTML = '<p class="acopio-detalle-vacio">No se pudo cargar el detalle. <button type="button" data-modal-close class="acopio-detalle-reintentar">Cerrar</button></p>';
        });
});

// Cantidad por página: cambiar el select recarga el listado (con skeleton).
document.querySelector('[data-acopio-por-pagina] select')?.addEventListener('change', (event) => {
    mostrarSkeleton();
    event.target.form.submit();
});

// ------------------------------------------------ edición inline de litros

function modoEdicion(detalle, activo) {
    detalle.querySelector('[data-litros-lectura]').hidden = activo;
    detalle.querySelector('[data-litros-form]').hidden = !activo;
    detalle.querySelector('[data-litros-editar]').hidden = activo;
}

contenidoDetalle?.addEventListener('click', (event) => {
    const detalle = event.target.closest('[data-entrega-detalle]');

    if (!detalle) {
        return;
    }

    if (event.target.closest('[data-litros-editar]')) {
        detalle.querySelector('[data-litros-aviso]').hidden = true;
        modoEdicion(detalle, true);
        detalle.querySelector('[data-litros-input]').focus();
    }

    if (event.target.closest('[data-litros-cancelar]')) {
        const form = detalle.querySelector('[data-litros-form]');
        form.reset();
        form.querySelector('[data-litros-error]').hidden = true;
        modoEdicion(detalle, false);
    }
});

contenidoDetalle?.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-litros-form]');

    if (!form) {
        return;
    }

    event.preventDefault();

    const detalle = form.closest('[data-entrega-detalle]');
    const error = form.querySelector('[data-litros-error]');
    const boton = form.querySelector('[data-litros-guardar]');
    const input = form.querySelector('[data-litros-input]');
    boton.disabled = true;
    boton.textContent = 'Guardando…';
    error.hidden = true;

    try {
        const respuesta = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            body: new URLSearchParams({ _method: 'PATCH', litros: input.value.trim() }),
        });
        const cuerpo = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
            throw new Error(cuerpo.errors?.litros?.[0] || cuerpo.message || 'No se pudieron actualizar los litros.');
        }

        detalle.querySelector('[data-litros-lectura]').textContent = cuerpo.litros_formateados;
        input.defaultValue = Number(cuerpo.litros).toFixed(2);
        document.querySelector(`[data-entrega-litros="${detalle.dataset.entregaId}"]`)?.replaceChildren(cuerpo.litros_formateados);
        modoEdicion(detalle, false);

        const aviso = detalle.querySelector('[data-litros-aviso]');
        aviso.textContent = cuerpo.message;
        aviso.hidden = false;
        window.setTimeout(() => { aviso.hidden = true; }, 3500);
    } catch (err) {
        error.textContent = err.message;
        error.hidden = false;
        input.focus();
    } finally {
        boton.disabled = false;
        boton.textContent = 'Guardar cambios';
    }
});
