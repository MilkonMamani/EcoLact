import { getCsrfToken } from '../maps.js';

/**
 * Pantalla /admin/pagos/trabajadores. La selección de fila, el panel por fetch, el skeleton y
 * la entrada escalonada los resuelve admin-fichas.js. Aquí solo queda lo propio de esta pantalla:
 *
 *  - el comprobante del panel es la hoja A4 real, escalada al ancho (y alto) disponible;
 *  - en tablet/móvil el panel es un drawer que se abre al elegir una fila;
 *  - Registrar pago abre un modal de confirmación (nunca paga directo). Bonificación y
 *    descuento se guardan al vuelo mientras el pago está PENDIENTE y, al cerrar el modal, el
 *    comprobante se vuelve a pedir para reflejarlos;
 *  - Confirmar pago se deshabilita al enviar: un doble clic no manda dos pagos (el backend
 *    además rechaza pagar una obligación que ya no está PENDIENTE);
 *  - Imprimir comprobante imprime solo su hoja (iframe oculto con la página A4).
 */
const ANCHO_HOJA = 794;
const ALTO_HOJA = 1123;
const ID_MODAL = 'modal-registrar-pago';

const pagina = document.querySelector('[data-pago-trabajadores]');

if (pagina) {
    iniciarPantalla(pagina);
}

function formatearSoles(valor) {
    return `S/ ${Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function iniciarPantalla(pagina) {
    const panel = pagina.querySelector('[data-fichas-panel]');
    const velo = pagina.querySelector('[data-panel-velo]');
    const escritorio = window.matchMedia('(min-width: 1280px)');

    const abrirDrawer = (abrir) => {
        panel.classList.toggle('is-abierto', abrir);
        velo.hidden = !abrir || escritorio.matches;
        document.body.classList.toggle('pt-drawer-abierto', abrir && !escritorio.matches);
    };

    const alActualizarPanel = () => {
        abrirDrawer(Boolean(panel.querySelector('[data-fichas-contenido]')) && panel.classList.contains('is-abierto'));
        escalarComprobante(panel);
        montarModal(pagina, panel);
    };

    // Elegir una fila (clic o Enter/Espacio) abre el drawer en pantallas angostas.
    pagina.querySelector('[data-fichas-tabla]')?.addEventListener('click', (event) => {
        if (event.target.closest('[data-fichas-fila]') && !event.target.closest('button, input, select, textarea')) {
            abrirDrawer(true);
        }
    });
    pagina.querySelector('[data-fichas-tabla]')?.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.closest('[data-fichas-fila]')) {
            abrirDrawer(true);
        }
    });
    velo.addEventListener('click', () => panel.querySelector('[data-fichas-cerrar]')?.click());
    escritorio.addEventListener('change', () => abrirDrawer(panel.classList.contains('is-abierto')));

    document.addEventListener('fichas:panel-actualizado', alActualizarPanel);
    window.addEventListener('resize', () => escalarComprobante(panel));

    escalarComprobante(panel);
    montarModal(pagina, panel);
    iniciarImpresionComprobante(pagina);
}

// ------------------------------------------------------- comprobante escalado

/** La hoja A4 se ajusta al ancho del panel y, en escritorio, a la altura de la ventana. */
function escalarComprobante(panel) {
    const contenedor = panel.querySelector('[data-comprobante-preview]');
    const escala = contenedor?.querySelector('[data-comprobante-escala]');

    if (!contenedor || !escala) {
        return;
    }

    const disponible = contenedor.clientWidth || 360;
    const porAltura = window.matchMedia('(min-width: 1280px)').matches
        ? ((window.innerHeight - 250) * ANCHO_HOJA) / ALTO_HOJA
        : disponible;
    const ancho = Math.max(240, Math.min(disponible, porAltura));
    const factor = ancho / ANCHO_HOJA;

    escala.style.setProperty('--pt-escala', String(factor));
    escala.style.width = `${ancho}px`;
    escala.style.height = `${ALTO_HOJA * factor}px`;
}

// --------------------------------------------------------- registrar pago

/**
 * El modal llega dentro del fragmento del panel; se mueve a <body> para que el drawer (que usa
 * transform) no lo recorte. Solo existe uno: el del trabajador PENDIENTE a la vista.
 */
function montarModal(pagina, panel) {
    document.querySelectorAll(`body > #${ID_MODAL}`).forEach((anterior) => anterior.remove());

    const modal = panel.querySelector(`#${ID_MODAL}`);

    if (!modal) {
        return;
    }

    document.body.append(modal);

    const formulario = modal.querySelector('[data-form-confirmar-pago]');
    let ajustado = false;

    sincronizarMetodo(formulario);
    formulario.addEventListener('change', (event) => {
        if (event.target.closest('[data-campo-metodo]')) {
            sincronizarMetodo(formulario);
        }

        if (event.target.closest('[data-campo-bonificacion], [data-campo-descuento]')) {
            guardarAjuste(pagina, formulario).then((ok) => {
                ajustado ||= ok;
            });
        }
    });

    formulario.addEventListener('submit', (event) => {
        const boton = formulario.querySelector('[data-confirmar-pago]');

        if (!formulario.reportValidity() || boton.disabled) {
            event.preventDefault();

            return;
        }

        boton.disabled = true;
        boton.textContent = 'Registrando…';
    });

    // Al cerrar el modal con ajustes guardados, el comprobante del panel se vuelve a pedir.
    new MutationObserver(() => {
        if (!modal.classList.contains('is-open') && ajustado && document.body.contains(modal)) {
            ajustado = false;
            document.dispatchEvent(new CustomEvent('fichas:recargar', { detail: { id: formulario.dataset.obligacion } }));
        }
    }).observe(modal, { attributes: true, attributeFilter: ['class'] });
}

/** El N.° de operación solo aparece (y es obligatorio) para métodos electrónicos; Efectivo no lo pide. */
function sincronizarMetodo(formulario) {
    const electronicos = JSON.parse(formulario.dataset.metodosElectronicos || '[]');
    const metodo = formulario.querySelector('[data-campo-metodo]');
    const grupo = formulario.querySelector('[data-campo-operacion-grupo]');
    const operacion = formulario.querySelector('[data-campo-operacion]');
    const obligatorio = electronicos.includes(metodo.value);

    grupo.hidden = !obligatorio;
    operacion.required = obligatorio;

    if (!obligatorio) {
        operacion.value = '';
    }
}

async function guardarAjuste(pagina, formulario) {
    const id = formulario.dataset.obligacion;
    const error = formulario.querySelector('[data-ajuste-error]');
    const boton = formulario.querySelector('[data-confirmar-pago]');

    boton.disabled = true;

    try {
        const respuesta = await fetch(pagina.dataset.urlAjuste.replace('__ID__', id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            body: JSON.stringify({
                bonificacion: formulario.querySelector('[data-campo-bonificacion]').value || 0,
                descuento: formulario.querySelector('[data-campo-descuento]').value || 0,
            }),
        });
        const json = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
            throw new Error(json.message || 'No se pudo guardar el ajuste.');
        }

        error.hidden = true;
        formulario.querySelector('[data-campo-total]').textContent = formatearSoles(json.total);
        formulario.querySelector('[data-campo-monto]').value = Number(json.total).toFixed(2);

        const celdaTotal = pagina.querySelector(`[data-fichas-fila][data-ficha-id="${id}"] [data-celda-total]`);

        if (celdaTotal) {
            celdaTotal.textContent = formatearSoles(json.total);
        }

        return true;
    } catch (err) {
        error.textContent = err.message || 'No se pudo guardar el ajuste.';
        error.hidden = false;

        return false;
    } finally {
        boton.disabled = false;
    }
}

// ------------------------------------------------------ imprimir comprobante

/**
 * Imprime EXACTAMENTE la hoja del comprobante (su propia página HTML A4) sin salir de la
 * pantalla: la carga en un iframe oculto y llama a print() de ese documento. Si el navegador
 * no lo permite, el enlace abre el comprobante en otra pestaña (sin JS igual).
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
