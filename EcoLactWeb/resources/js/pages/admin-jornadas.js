/**
 * "Control del periodo" (perfiles de personal + panel de Pagar a
 * trabajadores): carga perezosa del fragmento "Ver jornadas" la primera vez
 * que se abre ese modal. Abrir/cerrar modales lo resuelve app.js; aquí solo
 * se cargan los datos, igual que hace admin-pago-trabajadores.js con el
 * panel derecho.
 */

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
