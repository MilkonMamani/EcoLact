import { getCsrfToken } from '../maps.js';

/**
 * Pantalla /admin/pagos/trabajadores. La selección de fila, el panel por fetch, el
 * skeleton y la entrada escalonada los resuelve admin-fichas.js (mismo patrón que
 * Proveedores/Acopiadores). Aquí solo queda lo propio de pagos:
 *  - la bonificación y el descuento se ajustan desde el panel mientras el
 *    pago esté pendiente, y se guardan al vuelo;
 *  - registrar el pago sigue siendo un POST normal (con archivo adjunto).
 */
const pagina = document.querySelector('[data-pago-trabajadores]');

if (pagina) {
    iniciarPantalla(pagina);
}

function formatearSoles(valor) {
    return `S/ ${Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function iniciarPantalla(pagina) {
    const sincronizarFormularios = iniciarConfirmacionPago(pagina);

    // El panel llega por fetch (admin-fichas.js): sincroniza su formulario de pago.
    document.addEventListener('fichas:panel-actualizado', sincronizarFormularios);
    iniciarAjuste(pagina);
    iniciarImpresionComprobante(pagina);
}

// ------------------------------------------------------ imprimir comprobante

/**
 * Imprime EXACTAMENTE la hoja del comprobante (su propia página HTML A4) sin salir de
 * la pantalla: la carga en un iframe oculto y llama a print() de ese documento. Si el
 * navegador no lo permite, el enlace abre el comprobante en otra pestaña (sin JS igual).
 */
function iniciarImpresionComprobante(pagina) {
    let marco = null;

    pagina.addEventListener('click', (event) => {
        const enlace = event.target.closest('[data-imprimir-comprobante]');

        if (!enlace || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        marco?.remove();
        marco = document.createElement('iframe');
        marco.title = 'Comprobante para imprimir';
        marco.setAttribute('aria-hidden', 'true');
        marco.style.cssText = 'position:fixed;width:0;height:0;border:0;right:0;bottom:0;visibility:hidden;';
        marco.addEventListener('load', async () => {
            try {
                await marco.contentDocument?.fonts?.ready;
                marco.contentWindow.focus();
                marco.contentWindow.print();
            } catch {
                window.open(enlace.href, '_blank', 'noopener');
            }
        }, { once: true });
        marco.src = enlace.href;
        document.body.append(marco);
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

function actualizarFila(pagina, id, { total }) {
    const celdaTotal = pagina.querySelector(`[data-fichas-fila][data-ficha-id="${id}"] [data-celda-total]`);

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
