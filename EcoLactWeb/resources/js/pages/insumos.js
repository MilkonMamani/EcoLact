/*
 * Pantalla Insumos: rellena el modal de edición con los datos del insumo
 * elegido (nombre, tipo, descripción, stock mínimo, estado). Los insumos del
 * sistema (Leche) no permiten cambiar su tipo ni desactivarlos: el backend
 * también lo impide; aquí solo se bloquean los controles para que se entienda.
 */
const pagina = document.querySelector('[data-insumos-page]');

if (pagina) {
    const modal = document.getElementById('modal-editar-insumo');
    const formulario = modal.querySelector('form');
    const plantilla = pagina.dataset.urlUpdate;

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-insumo-editar]');

        if (!boton || !pagina.contains(boton)) {
            return;
        }

        const insumo = JSON.parse(boton.dataset.insumo);
        formulario.action = plantilla.replace('__ID__', insumo.id);
        formulario.querySelector('[name="nombre"]').value = insumo.nombre;
        formulario.querySelector('[name="descripcion"]').value = insumo.descripcion ?? '';
        formulario.querySelector('[name="stock_minimo"]').value = insumo.stock_minimo;

        const tipo = formulario.querySelector('[name="tipo"]');
        const estado = formulario.querySelector('[name="estado"]');
        tipo.value = insumo.tipo;
        estado.value = insumo.estado;
        tipo.disabled = insumo.del_sistema;
        estado.disabled = insumo.del_sistema;

        // Un control deshabilitado no se envía: se reemplaza por un campo oculto con el mismo valor.
        formulario.querySelectorAll('[data-espejo]').forEach((oculto) => oculto.remove());

        if (insumo.del_sistema) {
            [tipo, estado].forEach((control) => {
                const oculto = document.createElement('input');
                oculto.type = 'hidden';
                oculto.name = control.name;
                oculto.value = control.value;
                oculto.dataset.espejo = '';
                formulario.append(oculto);
            });
        }
        modal.querySelector('[data-aviso-sistema]').hidden = !insumo.del_sistema;
        modal.querySelector('[data-unidad-base]').textContent = insumo.unidad_etiqueta;
    });

    // Modal "Nuevo insumo": las cantidades de stock se muestran en la unidad base elegida.
    const modalNuevo = document.getElementById('modal-nuevo-insumo');
    const selectUnidad = modalNuevo.querySelector('[data-unidad-base-select]');
    const sufijos = modalNuevo.querySelectorAll('[data-sufijo-unidad]');

    const actualizarSufijo = () => sufijos.forEach((sufijo) => {
        sufijo.textContent = selectUnidad.value;
    });

    selectUnidad.addEventListener('change', actualizarSufijo);
    actualizarSufijo();

    // Si el servidor rechazó el alta, se vuelve a abrir con lo que la persona había escrito.
    if (pagina.hasAttribute('data-reabrir-nuevo')) {
        modalNuevo.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
