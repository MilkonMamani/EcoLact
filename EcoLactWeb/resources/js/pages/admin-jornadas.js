/**
 * "Control del periodo" (perfiles de personal + panel de Pagar a
 * trabajadores): carga perezosa del fragmento "Ver jornadas" la primera vez
 * que se abre ese modal. Abrir/cerrar modales lo resuelve app.js; aquí solo
 * se cargan los datos, igual que hace admin-pago-trabajadores.js con el
 * panel derecho. También controla el calendario mensual de asistencia
 * (x-pagos.attendance-calendar): clic en un día muestra su detalle, y desde
 * ahí un administrador puede abrir la corrección.
 */
import { getCsrfToken } from '../maps.js';

const contenidosCargados = new WeakSet();

document.addEventListener('click', (event) => {
    const disparador = event.target.closest('[data-jornadas-trigger]');

    if (!disparador) {
        return;
    }

    const modal = document.getElementById(disparador.dataset.modalOpen);
    const contenido = modal?.querySelector('[data-jornadas-contenido]');

    if (!contenido || contenidosCargados.has(contenido)) {
        return;
    }

    contenidosCargados.add(contenido);

    fetch(disparador.dataset.urlJornadas, { headers: { Accept: 'text/html' } })
        .then((respuesta) => (respuesta.ok ? respuesta.text() : Promise.reject()))
        .then((html) => {
            contenido.innerHTML = html;
        })
        .catch(() => {
            contenidosCargados.delete(contenido);
            contenido.innerHTML = '<p class="text-sm text-danger">No se pudieron cargar las jornadas.</p>';
        });
});

// Un modal de registrar jornada puede llegar ya abierto (:open) tras un error
// de validación; replica el mismo ajuste que hace admin-pagos.js.
if (document.querySelector('[data-modal].is-open')) {
    document.body.classList.add('overflow-hidden');
}

// --------------------------------------------------- calendario mensual

const ESTADO_ETIQUETA = { PRESENTE: 'Asistió', FALTA: 'Falta', SIN_REGISTRO: 'Sin registrar' };

document.querySelectorAll('[data-attendance-calendar]').forEach((calendario) => {
    const detalle = calendario.querySelector('[data-detalle-dia]');
    const botonCorregir = calendario.querySelector('[data-detalle-dia-corregir]');
    let diaSeleccionado = null;

    if (!detalle) {
        return;
    }

    calendario.querySelectorAll('[data-dia-calendario]:not([disabled])').forEach((boton) => {
        boton.addEventListener('click', () => {
            diaSeleccionado = boton.dataset;
            detalle.hidden = false;
            detalle.querySelector('[data-detalle-dia-fecha]').textContent = boton.dataset.fecha;
            detalle.querySelector('[data-detalle-dia-estado]').textContent = ESTADO_ETIQUETA[boton.dataset.estado] || (boton.dataset.estado === 'FUTURO' ? 'Futuro' : boton.dataset.estado);
            detalle.querySelector('[data-detalle-dia-hora]').textContent = boton.dataset.hora || '—';
            detalle.querySelector('[data-detalle-dia-origen]').textContent = boton.dataset.origen || '—';
            detalle.querySelector('[data-detalle-dia-observacion]').textContent = boton.dataset.observacion || '—';

            if (botonCorregir) {
                botonCorregir.hidden = !boton.dataset.jornadaId;
            }
        });
    });

    botonCorregir?.addEventListener('click', () => {
        if (!diaSeleccionado) {
            return;
        }

        const modal = calendario.querySelector('[id^="modal-corregir-perfil-"]');
        const form = modal?.querySelector('[data-form-corregir-perfil]');

        if (!modal || !form) {
            return;
        }

        form.action = `${form.dataset.urlBase}/${form.dataset.userId}/jornadas/${diaSeleccionado.fecha}/corregir`;
        modal.querySelector('[data-corregir-perfil-fecha]').textContent = diaSeleccionado.fecha;

        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    });
});

document.querySelectorAll('[data-form-corregir-perfil]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const error = form.querySelector('[data-corregir-perfil-error]');
        const boton = form.querySelector('button[type="submit"]');
        const textoOriginal = boton.textContent;
        boton.disabled = true;
        boton.textContent = 'Guardando corrección…';
        error.hidden = true;

        try {
            const respuesta = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: new FormData(form),
            });

            if (!respuesta.ok) {
                const cuerpo = await respuesta.json().catch(() => ({}));
                throw new Error(cuerpo.errors?.jornada?.[0] || cuerpo.message || 'No se pudo guardar la corrección.');
            }

            window.location.reload();
        } catch (err) {
            error.textContent = err.message || 'No se pudo guardar la corrección.';
            error.hidden = false;
            boton.disabled = false;
            boton.textContent = textoOriginal;
        }
    });
});
