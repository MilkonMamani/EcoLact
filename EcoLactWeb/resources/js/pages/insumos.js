/*
 * Pantalla Insumos: modales "Nuevo insumo" y "Editar insumo".
 *
 * - "¿Cómo se mide este insumo?" (_selector-unidad): al elegir peso, volumen o unidades se muestran
 *   solo las unidades de esa magnitud (se propone la principal: kg, L, und) y un texto que explica
 *   en qué se controlará el stock. Solo `unidad_base` viaja; el servidor valida igual.
 * - Editar: rellena los datos del insumo elegido. La unidad solo se puede corregir si el panel
 *   dice que no tiene historial (`unidad_editable`); si no, se muestra bloqueada con el motivo y sus
 *   radios se deshabilitan para que no se envíen. Los insumos del sistema (Leche) tampoco permiten
 *   cambiar su tipo ni desactivarlos: el backend también lo impide.
 */
const pagina = document.querySelector('[data-insumos-page]');

const AYUDAS = {
    MASA: (nombre, unidad, otra) => `El stock de ${nombre} se controlará en ${unidad}. Podrás registrar compras en sacos o bolsas y usar ${otra} en las recetas.`,
    VOLUMEN: (nombre, unidad, otra) => `El stock de ${nombre} se controlará en ${unidad}. Podrás registrar compras en baldes o frascos y usar ${otra} en las recetas.`,
    CONTEO: (nombre, unidad) => `El stock de ${nombre} se controlará en ${unidad}: cada envase o pieza cuenta como una unidad.`,
};

const OTRA_UNIDAD = { kg: 'gramos', g: 'kilogramos', L: 'mililitros', ml: 'litros' };

/**
 * @returns {{ establecer: (codigo: ?string) => void, habilitar: (activo: boolean) => void }}
 */
function selectorUnidad(formulario) {
    const caja = formulario.querySelector('[data-selector-unidad]');
    const radiosFamilia = [...caja.querySelectorAll('[data-familia-radio]')];
    const radiosUnidad = [...caja.querySelectorAll('[data-unidad-radio]')];
    const ayuda = caja.querySelector('[data-ayuda-unidad]');
    const sufijos = formulario.querySelectorAll('[data-sufijo-unidad]');
    const nombre = formulario.querySelector('[data-insumo-nombre]');

    function pintar() {
        const familia = radiosFamilia.find((radio) => radio.checked)?.value ?? null;
        const unidad = radiosUnidad.find((radio) => radio.checked) ?? null;

        caja.querySelectorAll('[data-familia-unidades]').forEach((grupo) => {
            grupo.hidden = grupo.dataset.familiaUnidades !== familia;
        });
        sufijos.forEach((sufijo) => { sufijo.textContent = unidad?.dataset.simbolo ?? ''; });

        const insumo = nombre?.value.trim() || 'este insumo';
        ayuda.textContent = unidad
            ? AYUDAS[unidad.dataset.familia](insumo, unidad.dataset.etiqueta.toLowerCase(), OTRA_UNIDAD[unidad.value] ?? '')
            : 'Elige si lo controlas por peso, por volumen o por unidades.';
    }

    radiosFamilia.forEach((radio) => radio.addEventListener('change', () => {
        // Al cambiar de magnitud se propone su unidad principal (la primera: kg, L, und).
        const actual = radiosUnidad.find((r) => r.checked);
        if (!actual || actual.dataset.familia !== radio.value) {
            radiosUnidad.find((r) => r.dataset.familia === radio.value).checked = true;
        }
        pintar();
    }));
    radiosUnidad.forEach((radio) => radio.addEventListener('change', pintar));
    nombre?.addEventListener('input', pintar);

    pintar();

    return {
        establecer(codigo) {
            const unidad = radiosUnidad.find((radio) => radio.value === codigo) ?? null;
            radiosUnidad.forEach((radio) => { radio.checked = radio === unidad; });
            radiosFamilia.forEach((radio) => { radio.checked = radio.value === unidad?.dataset.familia; });
            pintar();
        },
        habilitar(activo) {
            [...radiosFamilia, ...radiosUnidad].forEach((radio) => { radio.disabled = !activo; });
        },
    };
}

if (pagina) {
    const modal = document.getElementById('modal-editar-insumo');
    const formulario = modal.querySelector('form');
    const plantilla = pagina.dataset.urlUpdate;
    const unidadEditar = selectorUnidad(formulario);

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

        // Unidad base: editable solo sin historial; si no, bloqueada y explicada.
        const editable = Boolean(insumo.unidad_editable);
        modal.querySelector('[data-unidad-editable]').hidden = !editable;
        modal.querySelector('[data-unidad-bloqueada]').hidden = editable;
        unidadEditar.habilitar(editable);
        unidadEditar.establecer(insumo.unidad_base ?? null);
        modal.querySelector('[data-unidad-base]').textContent = insumo.unidad_etiqueta;
        if (insumo.unidad_motivo) {
            modal.querySelector('[data-unidad-motivo]').textContent = insumo.unidad_motivo;
        }
        formulario.querySelectorAll('[data-sufijo-unidad]').forEach((sufijo) => { sufijo.textContent = insumo.unidad_simbolo ?? ''; });
    });

    const modalNuevo = document.getElementById('modal-nuevo-insumo');
    selectorUnidad(modalNuevo.querySelector('form'));

    // Si el servidor rechazó el alta, se vuelve a abrir con lo que la persona había escrito.
    if (pagina.hasAttribute('data-reabrir-nuevo')) {
        modalNuevo.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
