/*
 * Pantalla Insumos (administrador): modales del panel lateral.
 *
 * - Presentación comercial (nueva/editar), con vista previa de la equivalencia.
 * - "Editar presentaciones" y ajuste de stock (merma, pérdida, corrección, inventario físico).
 *
 * Las compras ya NO se registran aquí: "Comprar este insumo" lleva a Inventario > Compras y gastos
 * (resources/js/pages/compras.js), el único registro de adquisiciones.
 */
const pagina = document.querySelector('[data-insumos-page]');

const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });

function abrirModal(modal) {
    modal.classList.add('is-open');
    document.body.classList.add('overflow-hidden');
}

// ------------------------------------------------------------ modales del panel

if (pagina) {
    // ---------------- presentación de compra: la equivalencia se ve mientras se escribe.
    const modalPresentacion = document.getElementById('modal-presentacion');
    const formPresentacion = modalPresentacion?.querySelector('[data-presentacion-form]');
    let presentacionActual = null;

    // "1 saco = 25 kg de Sal" / "1 balde = 20 L = 20,000 ml de Cuajo" (el servidor la recalcula al guardar).
    function pintarEquivalencia() {
        if (!formPresentacion || !presentacionActual) return;
        const nombre = (formPresentacion.querySelector('[name="nombre"]').value.trim() || 'presentación').toLowerCase();
        const cantidad = Number(formPresentacion.querySelector('[name="cantidad"]').value);
        const unidad = formPresentacion.querySelector('[data-presentacion-unidades]').value;
        const simbolo = unidad === 'unidad' ? 'und' : unidad;
        const factor = presentacionActual.factores?.[unidad];
        const base = presentacionActual.unidad_base;
        const valido = cantidad > 0 && factor;
        const enBase = valido ? cantidad * factor : 0;

        formPresentacion.querySelector('[data-presentacion-equivalencia]').textContent = valido
            ? `1 ${nombre} = ${numero(cantidad)} ${simbolo}${simbolo === base ? '' : ` = ${numero(enBase)} ${base}`} de ${presentacionActual.insumo}`
            : `Indica cuánto contiene cada ${nombre}.`;
        formPresentacion.querySelector('[data-presentacion-ejemplo]').textContent = valido
            ? `Si compras 2, entrarán ${numero(enBase * 2)} ${base} al stock.`
            : '';
    }

    formPresentacion?.addEventListener('input', pintarEquivalencia);
    formPresentacion?.addEventListener('change', pintarEquivalencia);

    // ---------------- ajustar stock (merma, pérdida, corrección, inventario físico)
    const modalAjuste = document.getElementById('modal-ajuste-insumo');
    const formAjuste = modalAjuste?.querySelector('[data-ajuste-form]');
    const insumosAjuste = formAjuste ? JSON.parse(formAjuste.dataset.insumos || '[]') : [];
    let insumoAjuste = null;

    function pintarAjuste() {
        if (!formAjuste) return;
        const motivo = formAjuste.querySelector('[name="motivo"]').value;
        const entradaCantidad = formAjuste.querySelector('[name="cantidad"]');
        const cantidad = Number(entradaCantidad.value);
        const soloResta = Boolean(insumoAjuste?.solo_resta);
        const esCorreccion = motivo === 'CORRECCION';
        const esInventario = motivo === 'INVENTARIO_FISICO';

        // La leche solo admite restas: la corrección no puede sumar.
        const sumar = formAjuste.querySelector('[name="direccion"][value="SUMA"]');
        sumar.disabled = soloResta;
        if (soloResta && sumar.checked) {
            formAjuste.querySelector('[name="direccion"][value="RESTA"]').checked = true;
        }
        const direccion = formAjuste.querySelector('[name="direccion"]:checked')?.value ?? 'RESTA';

        // La dirección solo viaja en una corrección.
        modalAjuste.querySelector('[data-ajuste-direccion]').hidden = !esCorreccion;
        formAjuste.querySelectorAll('[name="direccion"]').forEach((radio) => {
            if (radio.value !== 'SUMA' || !soloResta) radio.disabled = !esCorreccion;
        });

        modalAjuste.querySelector('[data-ajuste-cantidad-rotulo]').textContent = esInventario
            ? 'Stock contado'
            : (esCorreccion ? 'Cantidad a corregir' : 'Cantidad a restar');
        modalAjuste.querySelector('[data-ajuste-ayuda]').textContent = esInventario
            ? 'Lo que hay físicamente en almacén. El stock pasará a ser esta cantidad.'
            : 'En la unidad base del insumo.';
        modalAjuste.querySelector('[data-ajuste-unidad]').textContent = insumoAjuste?.unidad ?? '';
        modalAjuste.querySelector('[data-ajuste-solo-salida]').hidden = !soloResta;

        const stock = modalAjuste.querySelector('[data-ajuste-stock]');
        stock.hidden = !insumoAjuste;
        if (insumoAjuste) {
            stock.querySelector('[data-ajuste-stock-valor]').textContent = `${numero(insumoAjuste.stock)} ${insumoAjuste.unidad}`;
        }

        // Vista previa: el servidor vuelve a calcular y rechaza un stock negativo.
        const resultado = modalAjuste.querySelector('[data-ajuste-resultado]');
        const visible = Boolean(insumoAjuste) && entradaCantidad.value !== '' && cantidad >= 0;
        resultado.hidden = !visible;
        if (visible) {
            const resta = motivo === 'MERMA' || motivo === 'PERDIDA' || (esCorreccion && direccion === 'RESTA');
            const nuevo = esInventario ? cantidad : insumoAjuste.stock + (resta ? -cantidad : cantidad);
            const valor = resultado.querySelector('[data-ajuste-resultado-valor]');
            valor.textContent = nuevo < 0 ? 'no alcanza el stock disponible' : `${numero(nuevo)} ${insumoAjuste.unidad}`;
            valor.className = nuevo < 0 ? 'text-danger' : 'text-text';
        }

        modalAjuste.querySelector('[data-ajuste-enviar]').disabled = !insumoAjuste;
    }

    function elegirInsumoAjuste(id) {
        insumoAjuste = insumosAjuste.find((insumo) => String(insumo.id) === String(id)) ?? null;
        formAjuste.action = insumoAjuste?.action ?? '#';
        pintarAjuste();
    }

    formAjuste?.addEventListener('input', pintarAjuste);
    formAjuste?.addEventListener('change', (evento) => {
        if (evento.target.matches('[data-ajuste-insumo-select]')) {
            elegirInsumoAjuste(evento.target.value);
        } else {
            pintarAjuste();
        }
    });
    formAjuste?.addEventListener('submit', (evento) => {
        if (!insumoAjuste) evento.preventDefault();
    });

    document.addEventListener('click', (evento) => {
        const botonPresentacion = evento.target.closest('[data-presentacion]');
        if (botonPresentacion) {
            const datos = JSON.parse(botonPresentacion.dataset.presentacion);
            presentacionActual = datos;
            modalPresentacion.querySelector('h2').textContent = datos.titulo;
            modalPresentacion.querySelector('h2 + p').textContent = datos.subtitulo;
            formPresentacion.action = datos.action;
            formPresentacion.querySelector('[data-presentacion-metodo]').value = datos.metodo;
            formPresentacion.querySelector('[name="nombre"]').value = datos.nombre;
            formPresentacion.querySelector('[name="cantidad"]').value = datos.cantidad;

            const unidades = formPresentacion.querySelector('[data-presentacion-unidades]');
            unidades.replaceChildren(...Object.entries(datos.unidades).map(([codigo, etiqueta]) => new Option(etiqueta, codigo)));
            unidades.value = datos.unidad;

            formPresentacion.querySelector('[name="estado"]').value = datos.estado;
            formPresentacion.querySelector('[data-presentacion-estado-campo]').hidden = !datos.editar;
            pintarEquivalencia();
            return;
        }

        // "Editar presentaciones": muestra u oculta el botón Editar de cada presentación.
        const botonEditarLista = evento.target.closest('[data-presentaciones-editar]');
        if (botonEditarLista) {
            const editando = botonEditarLista.getAttribute('aria-pressed') !== 'true';
            botonEditarLista.setAttribute('aria-pressed', String(editando));
            botonEditarLista.querySelector('[data-presentaciones-editar-texto]').textContent = editando ? 'Listo' : 'Editar';
            botonEditarLista.closest('[data-presentaciones]').querySelectorAll('[data-presentacion-fila-editar]').forEach((boton) => {
                boton.hidden = !editando;
            });
            return;
        }

        const botonAjuste = evento.target.closest('[data-ajuste-abrir]');
        if (botonAjuste && modalAjuste) {
            const fijo = botonAjuste.dataset.ajusteInsumo ?? null;
            formAjuste.reset();
            formAjuste.querySelector('[data-ajuste-insumo-select]').value = fijo ?? '';
            // Desde una fila o el panel el insumo ya está elegido; desde el encabezado se elige aquí.
            modalAjuste.querySelector('[data-ajuste-campo-insumo]').hidden = fijo !== null;
            elegirInsumoAjuste(fijo ?? '');
            modalAjuste.querySelector('h2').textContent = fijo && insumoAjuste ? `Ajustar stock de ${insumoAjuste.nombre}` : 'Ajustar stock';
            abrirModal(modalAjuste);
        }
    });
}
