import { getCsrfToken } from '../maps.js';

/**
 * Pantalla /admin/pagos/trabajadores (tabla + panel lateral):
 *  - clic en una fila carga al trabajador en el panel derecho sin recargar
 *    la página (fragmento HTML que ya renderiza el servidor, igual que
 *    /admin/zonas);
 *  - la bonificación y el descuento se ajustan desde el panel mientras el
 *    pago esté pendiente, y se guardan al vuelo;
 *  - confirmar el pago sigue siendo un POST normal (con archivo adjunto),
 *    igual que en la pantalla general de Pagos.
 */
const pagina = document.querySelector('[data-pago-trabajadores]');

if (pagina) {
    iniciarPantalla(pagina);
}

function formatearSoles(valor) {
    return `S/ ${Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function iniciarPantalla(pagina) {
    const panel = pagina.querySelector('[data-panel-trabajador]');
    const sincronizarFormularios = iniciarConfirmacionPago(pagina);

    iniciarSeleccionDeFila(pagina, panel, sincronizarFormularios);
    iniciarAjuste(pagina);
}

// --------------------------------------------------------- selección de fila

function iniciarSeleccionDeFila(pagina, panel, sincronizarFormularios) {
    const marcarSeleccion = (id) => {
        pagina.querySelectorAll('[data-fila-trabajador]').forEach((fila) => {
            fila.dataset.selected = fila.dataset.id === String(id) ? 'true' : 'false';
        });
    };

    const seleccionar = async (id) => {
        try {
            const url = new URL(pagina.dataset.urlPanel.replace('__ID__', id), window.location.href);
            // Los filtros activos (q, rol, periodo, estado) viajan al fragmento para
            // que el formulario de "Confirmar pago" los conserve al redirigir.
            new URLSearchParams(window.location.search).forEach((valor, clave) => {
                if (clave !== 'trabajador') {
                    url.searchParams.set(clave, valor);
                }
            });

            const respuesta = await fetch(url, { headers: { Accept: 'text/html' } });

            if (!respuesta.ok) {
                throw new Error('No se pudo cargar el trabajador.');
            }

            panel.innerHTML = await respuesta.text();
            marcarSeleccion(id);
            sincronizarFormularios();

            const url2 = new URL(window.location.href);
            url2.searchParams.set('trabajador', id);
            window.history.replaceState({}, '', url2);
        } catch {
            // El panel conserva su contenido anterior; la fila sigue disponible para reintentar.
        }
    };

    pagina.addEventListener('click', (event) => {
        const fila = event.target.closest('[data-fila-trabajador]');

        if (fila && !event.target.closest('a, button')) {
            seleccionar(fila.dataset.id);
        }
    });
}

// -------------------------------------------------------------------- ajuste

function iniciarAjuste(pagina) {
    let temporizador;

    const enviar = async (panelObligacion) => {
        const url = pagina.dataset.urlAjuste.replace('__ID__', panelObligacion.dataset.panelObligacion);
        const bonificacion = panelObligacion.querySelector('[data-campo-bonificacion]');
        const descuento = panelObligacion.querySelector('[data-campo-descuento]');
        const total = panelObligacion.querySelector('[data-campo-total]');
        const error = panelObligacion.querySelector('[data-ajuste-error]');

        try {
            const respuesta = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({
                    bonificacion: bonificacion?.value || 0,
                    descuento: descuento?.value || 0,
                }),
            });

            const json = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                throw new Error(json.message || 'No se pudo guardar el ajuste.');
            }

            error.hidden = true;
            total.textContent = formatearSoles(json.total);
            actualizarFila(pagina, panelObligacion.dataset.panelObligacion, json);
        } catch (err) {
            error.textContent = err.message || 'No se pudo guardar el ajuste.';
            error.hidden = false;
        }
    };

    pagina.addEventListener('change', (event) => {
        const campo = event.target.closest('[data-campo-bonificacion], [data-campo-descuento]');

        if (!campo) {
            return;
        }

        window.clearTimeout(temporizador);
        temporizador = window.setTimeout(() => enviar(campo.closest('[data-panel-obligacion]')), 150);
    });
}

function actualizarFila(pagina, id, { bonificacion, descuento, total }) {
    const fila = pagina.querySelector(`[data-fila-trabajador][data-id="${id}"]`);

    if (!fila) {
        return;
    }

    const ajuste = bonificacion - descuento;
    const celdaAjuste = fila.querySelector('[data-celda-ajuste]');
    const celdaTotal = fila.querySelector('[data-celda-total]');

    if (celdaAjuste) {
        celdaAjuste.textContent = ajuste === 0 ? '—' : `${ajuste > 0 ? '+ ' : '− '}${formatearSoles(Math.abs(ajuste))}`;
        celdaAjuste.classList.remove('text-success', 'text-danger', 'text-text-muted');
        celdaAjuste.classList.add(ajuste > 0 ? 'text-success' : ajuste < 0 ? 'text-danger' : 'text-text-muted');
    }

    if (celdaTotal) {
        celdaTotal.textContent = formatearSoles(total);
    }
}

// --------------------------------------------------------- confirmar pago

const METODOS_QUE_REQUIEREN_OPERACION_TEXTO = 'Obligatorio para este método de pago.';
const OPERACION_TEXTO_OPCIONAL = 'Opcional para efectivo; obligatorio para métodos electrónicos.';

function iniciarConfirmacionPago(pagina) {
    const sincronizar = (form) => {
        const metodosElectronicos = JSON.parse(form.dataset.metodosElectronicos || '[]');
        const metodo = form.querySelector('[data-campo-metodo]');
        const operacion = form.querySelector('[data-campo-operacion]');
        const marca = form.querySelector('[data-campo-operacion-req]');
        const ayuda = form.querySelector('[data-campo-operacion-ayuda]');

        if (!metodo || !operacion) {
            return;
        }

        const obligatorio = metodosElectronicos.includes(metodo.value);
        operacion.required = obligatorio;
        if (marca) {
            marca.hidden = !obligatorio;
        }
        if (ayuda) {
            ayuda.textContent = obligatorio ? METODOS_QUE_REQUIEREN_OPERACION_TEXTO : OPERACION_TEXTO_OPCIONAL;
        }
    };

    const sincronizarTodos = () => pagina.querySelectorAll('[data-form-confirmar-pago]').forEach(sincronizar);

    sincronizarTodos();

    pagina.addEventListener('change', (event) => {
        const metodo = event.target.closest('[data-campo-metodo]');

        if (metodo) {
            sincronizar(metodo.closest('[data-form-confirmar-pago]'));
        }
    });

    pagina.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-form-confirmar-pago]');

        if (form && !form.reportValidity()) {
            event.preventDefault();
        }
    });

    return sincronizarTodos;
}
