/**
 * Configuración de pagos. La tabla + panel de sueldos por rol la maneja admin-fichas.js;
 * aquí solo:
 *
 * - Pestañas Trabajadores / Proveedores sin recargar (?tab= en la URL para volver a la misma).
 * - Valores calculados que el administrador no escribe: valor por día (sueldo ÷ 30) y
 *   precio resultante de la semana OBSERVADA (precio base − descuento). El backend
 *   calcula lo mismo; esto solo lo muestra mientras se escribe.
 *
 * Los formularios del panel llegan por fetch, por eso se escucha por delegación.
 */
const DIAS_BASE = 30;

const pagina = document.querySelector('[data-config-pagos-page]');
const soles = (valor) => `S/ ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

function activarPestana(clave) {
    pagina.querySelectorAll('[data-config-tab]').forEach((boton) => {
        boton.setAttribute('aria-selected', String(boton.dataset.configTab === clave));
    });

    pagina.querySelectorAll('[data-config-seccion]').forEach((seccion) => {
        seccion.hidden = seccion.dataset.configSeccion !== clave;
    });

    const url = new URL(window.location.href);
    url.searchParams.set('tab', clave);
    window.history.replaceState(window.history.state, '', url);
}

function actualizarCalculos(formulario) {
    const sueldo = formulario.querySelector('[data-sueldo]');

    if (sueldo) {
        const valor = parseFloat(sueldo.value);
        formulario.querySelector('[data-valor-dia]').textContent = valor > 0 ? soles(Math.round((valor / DIAS_BASE) * 100) / 100) : '—';
    }

    const precio = formulario.querySelector('[data-precio-base]');

    if (precio) {
        const base = parseFloat(precio.value);
        const descuento = parseFloat(formulario.querySelector('[data-descuento-observada]')?.value) || 0;
        formulario.querySelector('[data-precio-observada]').textContent = base > 0 ? soles(Math.max(0, base - descuento)) : '—';
    }
}

pagina?.querySelectorAll('[data-config-tab]').forEach((boton) => {
    boton.addEventListener('click', () => activarPestana(boton.dataset.configTab));
});

document.addEventListener('input', (evento) => {
    const formulario = evento.target.closest('[data-config-sueldo], [data-config-proveedor]');

    if (formulario) {
        actualizarCalculos(formulario);
    }
});

document.querySelectorAll('[data-config-sueldo], [data-config-proveedor]').forEach(actualizarCalculos);

// ------------------------------------------------ sueldo por rol: paso de confirmación

/**
 * El primer "Guardar configuración" no envía: el navegador valida el formulario y se muestra el
 * resumen (rol, sueldo actual y nuevo, vigencia, a quién afecta). Solo "Confirmar y guardar"
 * envía. "Volver a editar" o cerrar el modal regresan al paso de edición.
 */
function pasoConfirmacion(formulario, confirmar) {
    formulario.querySelector('[data-paso-edicion]').hidden = confirmar;
    formulario.querySelector('[data-paso-confirmacion]').hidden = !confirmar;
    formulario.querySelector('[data-boton-revisar]').hidden = !confirmar;
    formulario.querySelector('[data-boton-cancelar]').hidden = confirmar;
    formulario.querySelector('[data-boton-guardar]').textContent = confirmar ? 'Confirmar y guardar' : 'Guardar configuración';
    formulario.dataset.confirmando = confirmar ? '1' : '';
}

document.addEventListener('submit', (evento) => {
    const formulario = evento.target.closest('[data-config-sueldo-rol]');

    if (!formulario || formulario.dataset.confirmando === '1') {
        return;
    }

    evento.preventDefault();

    const sueldo = parseFloat(formulario.querySelector('[data-sueldo]').value);
    const [anio, mes, dia] = formulario.querySelector('[data-fecha-inicio]').value.split('-');

    formulario.querySelector('[data-resumen-sueldo]').textContent = soles(sueldo);
    formulario.querySelector('[data-resumen-fecha]').textContent = `${dia}/${mes}/${anio}`;
    pasoConfirmacion(formulario, true);
    formulario.querySelector('[data-boton-guardar]').focus();
});

document.addEventListener('click', (evento) => {
    const revisar = evento.target.closest('[data-boton-revisar]');
    const cerrar = evento.target.closest('[data-modal-close]');
    const abrir = evento.target.closest('[data-modal-open]');
    const formulario = (revisar ?? cerrar)?.closest('[data-config-sueldo-rol]')
        ?? cerrar?.closest('[data-modal]')?.querySelector('[data-config-sueldo-rol]')
        ?? (abrir ? document.getElementById(abrir.dataset.modalOpen)?.querySelector('[data-config-sueldo-rol]') : null);

    if (formulario) {
        pasoConfirmacion(formulario, false);
    }
});
