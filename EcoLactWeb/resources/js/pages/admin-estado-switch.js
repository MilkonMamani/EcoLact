/**
 * Switch de estado Activo/Inactivo de los listados "listado + panel" (Proveedores y
 * Acopiadores). No hay un "update de estado" propio: el switch solo abre la confirmación de
 * las MISMAS acciones que ya existen (y que usan los Use Cases reales):
 *
 * - ACTIVO → pulsar: modal de desactivación; "Sí, desactivar" envía PATCH <data-url-desactivar>.
 * - INACTIVO → pulsar: modal de reactivación; hay que elegir la zona (y lo que pida el
 *   formulario, p. ej. la ruta en Proveedores) y pulsar "Reactivar": PATCH <data-url-reactivar>.
 *
 * La página declara en [data-fichas-page] los ids de sus modales (data-modal-desactivar,
 * data-modal-reactivar) y la clave del registro en la respuesta JSON (data-estado-clave).
 *
 * El switch nunca cambia "por adelantado": solo se mueve cuando el servidor confirma. Si la
 * petición falla, el modal queda abierto con el error y el estado sigue igual. Cerrar un modal
 * sin confirmar no cambia nada. Tras un cambio se actualizan la fila (switch y zona) y el panel.
 */
import { getCsrfToken } from '../maps.js';

const pagina = document.querySelector('[data-fichas-page]');
const modalDesactivar = document.getElementById(pagina?.dataset.modalDesactivar ?? '');
const modalReactivar = document.getElementById(pagina?.dataset.modalReactivar ?? '');
const formDesactivar = modalDesactivar?.querySelector('form');
const formReactivar = modalReactivar?.querySelector('form');
const clave = pagina?.dataset.estadoClave;

/** switch que abrió el modal que está a la vista */
let switchPendiente = null;

async function enviar(url, form) {
    const datos = new URLSearchParams(new FormData(form));
    datos.set('_method', 'PATCH');

    const respuesta = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        body: datos,
    });
    const cuerpo = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        const primerError = Object.values(cuerpo.errors ?? {})[0]?.[0];
        throw new Error(primerError || cuerpo.message || 'No se pudo cambiar el estado.');
    }

    return cuerpo;
}

/** Mismo formato que x-ui.estado-switch: sin texto visible, estado en title y aria-label. */
function pintarSwitch(boton, activo) {
    const entidad = boton.dataset.entidad || 'registro';
    const estado = activo ? 'activo' : 'inactivo';

    boton.setAttribute('aria-checked', String(activo));
    boton.setAttribute('aria-label', `${entidad.charAt(0).toUpperCase()}${entidad.slice(1)} ${estado}: ${boton.dataset.nombre}`);
    boton.title = activo ? 'Activo' : 'Inactivo';
}

function aviso(texto) {
    document.querySelector('.fichas-toast')?.remove();

    const caja = Object.assign(document.createElement('div'), { className: 'fichas-toast', textContent: texto });
    caja.setAttribute('role', 'status');
    document.body.append(caja);
    window.setTimeout(() => caja.remove(), 3500);
}

function abrir(modal) {
    modal.classList.add('is-open');
    document.body.classList.add('overflow-hidden');
}

function cerrar(modal) {
    modal.classList.remove('is-open');

    if (!document.querySelector('[data-modal].is-open')) {
        document.body.classList.remove('overflow-hidden');
    }
}

function mostrarError(form, texto) {
    const error = form.querySelector('[role="alert"]');
    error.textContent = texto;
    error.hidden = texto === '';
}

/** Refleja en la fila y en el panel lo que devolvió el servidor. */
function aplicarResultado(boton, registro) {
    pintarSwitch(boton, registro.estado === 'ACTIVO');

    const celdaZona = boton.closest('[data-fichas-fila]')?.querySelector('[data-fila-zona]');
    if (celdaZona && registro.zonaNombre) {
        celdaZona.textContent = registro.zonaNombre;
    }

    document.dispatchEvent(new CustomEvent('fichas:recargar', { detail: { id: String(registro.id) } }));
}

// ------------------------------------------------ selects dependientes (Zona → Ruta)

/**
 * Un <select data-depende-de="zona_id"> solo muestra las opciones cuyo data-padre coincide
 * con el valor elegido en el select padre; si queda una sola opción válida, la elige.
 */
function filtrarDependientes(form) {
    form.querySelectorAll('select[data-depende-de]').forEach((hijo) => {
        const padre = form.elements[hijo.dataset.dependeDe];
        const opciones = [...hijo.options].filter((opcion) => opcion.value !== '');
        const validas = opciones.filter((opcion) => opcion.dataset.padre === padre?.value);

        opciones.forEach((opcion) => {
            opcion.hidden = !validas.includes(opcion);
            opcion.disabled = !validas.includes(opcion);
        });

        hijo.value = validas.length === 1 ? validas[0].value : '';
        hijo.disabled = !padre?.value || validas.length === 0;

        const aviso = form.querySelector(`[data-sin-opciones="${hijo.name}"]`);
        if (aviso) {
            aviso.hidden = !padre?.value || validas.length > 0;
        }
    });
}

// ------------------------------------------------ flujo

function prepararModal(modal, form, boton, url) {
    switchPendiente = boton;
    form.action = url;
    modal.querySelectorAll('[data-estado-nombre]').forEach((nodo) => {
        nodo.textContent = boton.dataset.nombre;
    });
    form.querySelectorAll('select').forEach((select) => {
        select.value = '';
    });
    filtrarDependientes(form);
    mostrarError(form, '');
    abrir(modal);
}

/** Envía el formulario del modal; solo si el servidor confirma se mueve el switch y se cierra. */
async function confirmar(modal, form, textoOcupado) {
    const boton = switchPendiente;
    const confirmarBoton = form.querySelector('[type="submit"]');
    const textoOriginal = confirmarBoton.textContent;

    if (!boton) {
        return;
    }

    mostrarError(form, '');
    confirmarBoton.disabled = true;
    confirmarBoton.textContent = textoOcupado;

    try {
        const cuerpo = await enviar(form.action, form);
        switchPendiente = null;
        cerrar(modal);
        aplicarResultado(boton, cuerpo[clave]);
        aviso(cuerpo.message);
    } catch (err) {
        mostrarError(form, err.message);
    } finally {
        confirmarBoton.disabled = false;
        confirmarBoton.textContent = textoOriginal;
    }
}

document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-estado-switch][data-registro-id]');

    if (!boton || !modalDesactivar || !modalReactivar) {
        return;
    }

    if (boton.getAttribute('aria-checked') === 'true') {
        prepararModal(modalDesactivar, formDesactivar, boton, boton.dataset.urlDesactivar);
        formDesactivar.querySelector('[type="submit"]').focus();
    } else {
        prepararModal(modalReactivar, formReactivar, boton, boton.dataset.urlReactivar);
        formReactivar.querySelector('select')?.focus();
    }
});

formDesactivar?.addEventListener('submit', (event) => {
    event.preventDefault();
    confirmar(modalDesactivar, formDesactivar, 'Desactivando…');
});

formReactivar?.addEventListener('submit', (event) => {
    event.preventDefault();

    // Nunca se reactiva sin zona (el servidor también lo exige).
    const vacio = [...formReactivar.querySelectorAll('select[required]')].find((select) => select.value === '');
    if (vacio) {
        mostrarError(formReactivar, vacio.dataset.mensajeRequerido || 'Completa los campos obligatorios.');
        vacio.focus();

        return;
    }

    confirmar(modalReactivar, formReactivar, 'Reactivando…');
});

formReactivar?.addEventListener('change', (event) => {
    if (event.target.matches('select')) {
        mostrarError(formReactivar, '');
    }

    if (formReactivar.querySelector(`select[data-depende-de="${event.target.name}"]`)) {
        filtrarDependientes(formReactivar);
    }
});
