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

/**
 * Engancha el calendario y el formulario de corrección que haya dentro de `raiz`. Se llama al
 * cargar la página y cada vez que un panel lateral llega por fetch (evento fichas:panel-actualizado).
 */
function iniciarCalendarios(raiz) {
    raiz.querySelectorAll('[data-attendance-calendar]').forEach((calendario) => {
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

    raiz.querySelectorAll('[data-form-corregir-perfil]').forEach((form) => {
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
}

iniciarCalendarios(document);
document.addEventListener('fichas:panel-actualizado', (event) => iniciarCalendarios(event.detail.panel));

// --------------------------------------------------- pestaña Asistencia de las fichas (x-fichas.asistencia)

/** Textos humanos por estado del día (nunca se muestran PRESENTE, FALTA, etc.). */
const DETALLE_POR_ESTADO = {
    PRESENTE: { titulo: '✓ Asistió', clase: 'is-presente', observacion: 'Sin observaciones' },
    FALTA: { titulo: '✕ Falta', clase: 'is-falta', observacion: 'No registró asistencia' },
    SIN_REGISTRO: { titulo: 'Pendiente', clase: 'is-neutro', nota: 'Aún puede registrarse hasta las 23:59', observacion: 'Sin observaciones' },
    NO_APLICABLE: { titulo: '— No correspondía', clase: 'is-neutro', nota: 'Fuera de su periodo laboral: antes de su fecha de ingreso o después de su salida.', observacion: 'Sin observaciones' },
};

/** "2026-09-22" → "Martes, 22 de septiembre de 2026". */
function fechaLarga(fechaIso) {
    const [anio, mes, dia] = fechaIso.split('-').map(Number);
    const texto = new Date(anio, mes - 1, dia).toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** "06:32" → "06:32 a. m."; "18:05" → "06:05 p. m.". */
function horaLegible(hora) {
    if (!hora) {
        return '—';
    }

    const [h, m] = hora.split(':').map(Number);
    const h12 = h % 12 === 0 ? 12 : h % 12;

    return `${String(h12).padStart(2, '0')}:${String(m).padStart(2, '0')} ${h < 12 ? 'a. m.' : 'p. m.'}`;
}

function mostrarDetalleDia(contenedor, boton) {
    const bloque = contenedor.querySelector('[data-detalle-asistencia]');

    contenedor.querySelectorAll('[data-dia-asistencia].is-selected').forEach((dia) => dia.classList.remove('is-selected'));
    boton.classList.add('is-selected');

    if (!bloque) {
        return;
    }

    const detalle = DETALLE_POR_ESTADO[boton.dataset.estado] ?? DETALLE_POR_ESTADO.SIN_REGISTRO;
    const estado = bloque.querySelector('[data-detalle-estado]');
    const nota = bloque.querySelector('[data-detalle-nota]');
    const contenido = bloque.querySelector('[data-detalle-contenido]');
    const esHoySinMarcar = boton.dataset.estado === 'SIN_REGISTRO' && boton.hasAttribute('data-hoy');

    bloque.querySelector('[data-detalle-vacio]').hidden = true;
    bloque.querySelector('[data-detalle-fecha]').textContent = fechaLarga(boton.dataset.fecha);
    estado.textContent = detalle.titulo;
    estado.className = `fichas-detalle-estado ${detalle.clase}`;
    nota.textContent = esHoySinMarcar ? 'Todavía no marca asistencia hoy.' : (detalle.nota ?? '');
    nota.hidden = !nota.textContent;
    bloque.querySelector('[data-detalle-hora]').textContent = horaLegible(boton.dataset.hora);
    bloque.querySelector('[data-detalle-origen]').textContent = boton.dataset.origen || '—';
    bloque.querySelector('[data-detalle-observacion]').textContent = boton.dataset.observacion || detalle.observacion;

    // Reinicia la transición suave del detalle al cambiar de día.
    contenido.hidden = true;
    void contenido.offsetWidth;
    contenido.hidden = false;
}

function iniciarAsistencias(raiz) {
    raiz.querySelectorAll('[data-asistencia-mensual]').forEach((contenedor) => {
        const dias = [...contenedor.querySelectorAll('[data-dia-asistencia]:not([disabled])')];

        dias.forEach((boton) => boton.addEventListener('click', () => mostrarDetalleDia(contenedor, boton)));

        // Por defecto: hoy si está en el mes; si no, el último día con asistencia o falta.
        const inicial = dias.find((boton) => boton.hasAttribute('data-hoy'))
            ?? dias.filter((boton) => ['PRESENTE', 'FALTA', 'SIN_REGISTRO'].includes(boton.dataset.estado)).at(-1);

        if (inicial) {
            mostrarDetalleDia(contenedor, inicial);
        }
    });
}

iniciarAsistencias(document);
document.addEventListener('fichas:panel-actualizado', (event) => iniciarAsistencias(event.detail.panel));
