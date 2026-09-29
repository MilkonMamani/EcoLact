/**
 * Personal → Trabajadores → Nuevo trabajador.
 *
 * - Elegir un rol (tarjetas = radios) cambia sin recargar: el destino del formulario (el alta
 *   real de ese rol: acopiadores.store, analistas-calidad.store, …), el enlace Cancelar y el
 *   campo Zona, que solo existe para el Acopiador (se oculta y se deshabilita para los demás,
 *   así no se envía). La URL queda con ?tipo= para que, si la validación falla, el servidor
 *   vuelva a esta misma selección.
 * - Botón para mostrar/ocultar la contraseña.
 */
const pagina = document.querySelector('[data-nuevo-trabajador]');
const formulario = pagina.querySelector('[data-nuevo-trabajador-form]');
const cancelar = pagina.querySelector('[data-nuevo-trabajador-cancelar]');
const campoZona = formulario.querySelector('[data-campo="zona"]');
const selectZona = campoZona?.querySelector('select');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function aplicarRol(radio, { animar = true } = {}) {
    formulario.action = radio.dataset.accion;
    cancelar.href = radio.dataset.cancelar;

    const conZona = radio.dataset.conZona === '1';

    if (campoZona && selectZona) {
        const estabaOculto = campoZona.hidden;
        campoZona.hidden = !conZona;
        selectZona.disabled = !conZona;

        if (conZona && estabaOculto && animar && !reducedMotion) {
            campoZona.classList.remove('is-entrando');
            void campoZona.offsetWidth;
            campoZona.classList.add('is-entrando');
        }
    }

    const url = new URL(window.location.href);
    url.searchParams.set('tipo', radio.value);
    window.history.replaceState(window.history.state, '', url);
}

pagina.querySelectorAll('input[name="tipo_trabajador"]').forEach((radio) => {
    radio.addEventListener('change', () => aplicarRol(radio));

    if (radio.checked) {
        aplicarRol(radio, { animar: false });
    }
});

// Mostrar / ocultar contraseña.
formulario.querySelectorAll('[data-ver-clave]').forEach((boton) => {
    const input = boton.parentElement.querySelector('input');

    boton.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        input.toggleAttribute('data-clave-visible', visible);
        boton.setAttribute('aria-pressed', String(visible));
        boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        boton.querySelector('[data-ver-clave-mostrar]').classList.toggle('hidden', visible);
        boton.querySelector('[data-ver-clave-ocultar]').classList.toggle('hidden', !visible);
        input.focus();
    });
});

// Cerrar el menú "Ver lista de trabajadores" al hacer clic fuera.
document.addEventListener('click', (event) => {
    const menu = pagina.querySelector('.nt-listas[open]');

    if (menu && !menu.contains(event.target)) {
        menu.removeAttribute('open');
    }
});
