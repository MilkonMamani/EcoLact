/*
 * Módulo Recetas. Una receta es solo configuración y es el ORIGEN del producto:
 * este archivo arma el modal único de Nueva / Editar receta (producto, imagen
 * con previsualización, fórmula e ingredientes con su unidad autocompletada).
 * No calcula ni descuenta inventario; el nombre real de producto y receta lo
 * genera el servidor.
 */
const pagina = document.querySelector('[data-recetas-page]');

if (pagina) {
    const modal = document.getElementById('modal-receta');
    const formulario = modal.querySelector('[data-receta-form]');
    const cuerpo = formulario.querySelector('[data-modal-cuerpo]');
    const titulo = formulario.querySelector('[data-receta-titulo]');
    const nota = formulario.querySelector('[data-nota-modo]');
    const notaInventario = formulario.querySelector('[data-nota-inventario]');
    const metodo = formulario.querySelector('[data-metodo]');
    const productoExistente = formulario.querySelector('[data-producto-existente]');
    const bloqueDatos = formulario.querySelector('[data-datos-producto]');
    const productoFijo = formulario.querySelector('[data-producto-fijo]');
    const formulaProducto = formulario.querySelector('[data-formula-producto]');
    const formulaAyuda = formulario.querySelector('[data-formula-ayuda]');
    const campos = {
        tipo: formulario.querySelector('[name="tipo_producto"]'),
        nombre: formulario.querySelector('[name="nombre_producto"]'),
        cantidad: formulario.querySelector('[name="presentacion_cantidad"]'),
        unidad: formulario.querySelector('[name="presentacion_unidad"]'),
        descripcion: formulario.querySelector('[name="descripcion"]'),
    };
    const imagenInput = formulario.querySelector('[data-imagen-input]');
    const imagenCaja = formulario.querySelector('[data-imagen-caja]');
    const imagenVista = formulario.querySelector('[data-imagen-vista]');
    const imagenVacia = formulario.querySelector('[data-imagen-vacia]');
    const imagenError = formulario.querySelector('[data-imagen-error]');
    const etiquetaImagen = formulario.querySelector('[data-etiqueta-imagen]');
    const botonImagen = formulario.querySelector('[data-imagen-boton]');
    const filas = formulario.querySelector('[data-ingredientes]');
    const ingredientesError = formulario.querySelector('[data-ingredientes-error]');
    const botonAgregar = formulario.querySelector('[data-agregar-ingrediente]');
    const botonGuardar = formulario.querySelector('[data-guardar]');

    const insumos = JSON.parse(pagina.dataset.insumos || '[]');
    const errores = JSON.parse(pagina.dataset.errores || '{}');
    const urlStore = pagina.dataset.urlStore;
    const urlUpdate = pagina.dataset.urlUpdate;
    const MAX_BYTES = 2 * 1024 * 1024;
    const TIPOS_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    const TEXTO_SIN_PRODUCTO = 'Completa el producto y la presentación para definir la fórmula.';

    let urlVistaPrevia = null;
    // Presentación del producto fijo (Editar / Configurar); en Nueva se lee de los campos.
    let productoActual = null;

    const insumoPorId = (id) => insumos.find((i) => String(i.id) === String(id));
    const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });

    const crear = (tag, clases, texto) => {
        const el = document.createElement(tag);
        if (clases) el.className = clases;
        if (texto !== undefined) el.textContent = texto;
        return el;
    };

    const claseCampo = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

    function abrir() {
        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
        cuerpo.scrollTop = 0;
    }

    /* ---------------------------------------------------------------- fórmula dinámica */

    // "Queso Paria · 1 kg" + "Las cantidades siguientes corresponden a una unidad de 1 kg."
    function actualizarFormula() {
        const nombre = productoActual ? productoActual.nombre : campos.nombre.value.trim().replace(/\s+/g, ' ');
        const presentacion = productoActual
            ? productoActual.presentacion
            : (Number(campos.cantidad.value) > 0 && campos.unidad.value ? `${numero(campos.cantidad.value)} ${campos.unidad.value}` : '');
        const completo = productoActual ? Boolean(nombre) : Boolean(nombre && presentacion);

        formulaProducto.textContent = completo ? [nombre, presentacion].filter(Boolean).join(' · ') : TEXTO_SIN_PRODUCTO;
        formulaProducto.classList.toggle('is-pendiente', !completo);
        formulaAyuda.textContent = presentacion
            ? `Define los insumos necesarios para producir una unidad. Las cantidades siguientes corresponden a una unidad de ${presentacion}.`
            : 'Define los insumos necesarios para producir una unidad.';
    }

    /* ---------------------------------------------------------------- ingredientes */

    function opcionesUnidad(select, insumoId, elegida) {
        select.replaceChildren();
        const insumo = insumoPorId(insumoId);

        if (!insumo) {
            const vacia = crear('option', '', '—');
            vacia.value = '';
            select.append(vacia);
            return;
        }

        // Solo unidades de la misma magnitud; por defecto la unidad base del insumo.
        Object.entries(insumo.compatibles).forEach(([codigo, etiqueta]) => {
            const opcion = crear('option', '', etiqueta);
            opcion.value = codigo;
            opcion.selected = codigo === (elegida ?? insumo.unidad_base);
            select.append(opcion);
        });
    }

    // Un insumo ya elegido en otra fila no se puede volver a elegir (el servidor también lo rechaza).
    function bloquearRepetidos() {
        const selects = [...filas.querySelectorAll('[data-insumo-select]')];
        const elegidos = selects.map((select) => select.value).filter(Boolean);

        selects.forEach((select) => {
            [...select.options].forEach((opcion) => {
                opcion.disabled = opcion.value !== '' && opcion.value !== select.value && elegidos.includes(opcion.value);
            });
        });
    }

    // Errores del servidor de esa fila (ingredientes.N.campo), junto a la fila y con el campo marcado.
    function marcarErroresFila(fila, indice, controles) {
        const mensajes = [];

        Object.entries(controles).forEach(([clave, control]) => {
            const propios = errores[`ingredientes.${indice}.${clave}`] ?? [];

            if (propios.length > 0) {
                control.classList.replace('border-text-muted/25', 'border-danger');
                control.setAttribute('aria-invalid', 'true');
                mensajes.push(...propios);
            }
        });

        const error = crear('p', 'recetas-ingrediente-error', [...new Set(mensajes)].join(' '));
        error.setAttribute('role', 'alert');
        fila.append(error);
    }

    function agregarFila(datos = {}, indiceServidor = null) {
        const indice = indiceServidor ?? Number(filas.dataset.contador || 0);
        filas.dataset.contador = String(Math.max(Number(filas.dataset.contador || 0), Number(indice) + 1));

        const fila = crear('div', 'recetas-ingrediente');

        const insumoSelect = crear('select', claseCampo);
        insumoSelect.name = `ingredientes[${indice}][insumo_id]`;
        insumoSelect.required = true;
        insumoSelect.dataset.insumoSelect = '';
        insumoSelect.setAttribute('aria-label', 'Insumo');
        const placeholder = crear('option', '', 'Elija un insumo');
        placeholder.value = '';
        insumoSelect.append(placeholder);
        insumos.forEach((insumo) => {
            const opcion = crear('option', '', insumo.nombre);
            opcion.value = insumo.id;
            opcion.selected = String(insumo.id) === String(datos.insumo_id ?? '');
            insumoSelect.append(opcion);
        });

        const cantidad = crear('input', claseCampo);
        cantidad.type = 'number';
        cantidad.step = 'any';
        cantidad.min = '0.0001';
        cantidad.required = true;
        cantidad.inputMode = 'decimal';
        cantidad.name = `ingredientes[${indice}][cantidad]`;
        cantidad.placeholder = '0';
        cantidad.setAttribute('aria-label', 'Cantidad');
        cantidad.value = datos.cantidad ?? '';

        const unidad = crear('select', claseCampo);
        unidad.name = `ingredientes[${indice}][unidad]`;
        unidad.required = true;
        unidad.setAttribute('aria-label', 'Unidad');
        opcionesUnidad(unidad, datos.insumo_id, datos.unidad);

        const observacion = crear('input', claseCampo);
        observacion.type = 'text';
        observacion.maxLength = 200;
        observacion.name = `ingredientes[${indice}][observacion]`;
        observacion.placeholder = 'Opcional';
        observacion.setAttribute('aria-label', 'Observación');
        observacion.value = datos.observacion ?? '';

        const quitar = crear('button', 'recetas-icono recetas-ingrediente-quitar');
        quitar.type = 'button';
        quitar.title = 'Eliminar ingrediente';
        quitar.setAttribute('aria-label', 'Eliminar ingrediente');
        quitar.innerHTML = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>';
        quitar.addEventListener('click', () => {
            fila.remove();
            bloquearRepetidos();
        });

        insumoSelect.addEventListener('change', () => {
            opcionesUnidad(unidad, insumoSelect.value);
            bloquearRepetidos();
        });

        fila.append(insumoSelect, cantidad, unidad, observacion, quitar);

        if (indiceServidor !== null) {
            marcarErroresFila(fila, indice, { insumo_id: insumoSelect, cantidad, unidad, observacion });
        }

        filas.append(fila);
        bloquearRepetidos();

        return fila;
    }

    /* ---------------------------------------------------------------- imagen */

    function mostrarVista(url) {
        imagenVista.src = url;
        imagenVista.classList.remove('hidden');
        imagenVacia.classList.add('hidden');
        imagenCaja.classList.add('tiene-imagen');
    }

    function limpiarImagen() {
        if (urlVistaPrevia) {
            URL.revokeObjectURL(urlVistaPrevia);
            urlVistaPrevia = null;
        }

        imagenInput.value = '';
        imagenVista.removeAttribute('src');
        imagenVista.classList.add('hidden');
        imagenVacia.classList.remove('hidden');
        imagenCaja.classList.remove('tiene-imagen');
    }

    imagenInput.addEventListener('change', () => {
        const archivo = imagenInput.files[0];
        imagenError.textContent = '';

        if (!archivo) {
            return;
        }

        if (!TIPOS_MIME.includes(archivo.type)) {
            imagenError.textContent = 'La imagen debe ser JPG, PNG o WEBP.';
            imagenInput.value = '';
            return;
        }

        if (archivo.size > MAX_BYTES) {
            imagenError.textContent = 'La imagen no puede pesar más de 2 MB.';
            imagenInput.value = '';
            return;
        }

        if (urlVistaPrevia) {
            URL.revokeObjectURL(urlVistaPrevia);
        }

        urlVistaPrevia = URL.createObjectURL(archivo);
        mostrarVista(urlVistaPrevia);
        botonImagen.textContent = 'Cambiar imagen';
    });

    /* ---------------------------------------------------------------- modos */

    // Los errores pintados por el servidor solo valen para el primer formulario reabierto.
    function limpiarErroresServidor() {
        formulario.querySelectorAll('[data-error-servidor], p.text-danger:not([data-imagen-error]):not([data-ingredientes-error])').forEach((el) => el.remove());
        formulario.querySelectorAll('.border-danger').forEach((el) => el.classList.replace('border-danger', 'border-text-muted/25'));
        imagenError.textContent = '';
        ingredientesError.textContent = '';
    }

    function pintarProductoFijo({ tipo = '', nombre = '', presentacion = '' }) {
        const valores = { tipo, nombre, presentacion };

        productoFijo.querySelectorAll('[data-fijo]').forEach((fila) => {
            const valor = valores[fila.dataset.fijo];
            fila.hidden = !valor;
            fila.querySelector('dd').textContent = valor || '';
        });
    }

    function preparar({ modo, receta = null, producto = null, viejo = null }) {
        if (!viejo) {
            limpiarErroresServidor();
        }

        formulario.reset();
        filas.replaceChildren();
        filas.dataset.contador = '0';
        limpiarImagen();
        productoActual = null;

        formulario.querySelector('[name="_modo"]').value = modo;
        formulario.querySelector('[name="_receta_id"]').value = receta?.id ?? viejo?._receta_id ?? '';
        metodo.disabled = modo !== 'editar';
        productoExistente.disabled = modo !== 'existente';
        productoExistente.value = modo === 'existente' ? producto.id : '';

        // Editar o configurar trabajan sobre un producto que ya existe: nunca se crea otro
        // y sus datos no se modifican (el servidor los rechaza), así que se muestran como texto.
        const conProducto = modo !== 'crear';
        bloqueDatos.hidden = conProducto;
        productoFijo.hidden = !conProducto;
        Object.values(campos).forEach((campo) => {
            if (campo !== campos.descripcion) {
                campo.disabled = conProducto;
            }
        });

        if (modo === 'crear') {
            titulo.textContent = 'Nueva receta';
            nota.textContent = 'Define el producto y su fórmula de producción.';
            formulario.action = urlStore;
            botonGuardar.textContent = 'Guardar receta';
            notaInventario.textContent = 'Guardar una receta no descuenta inventario.';
        } else if (modo === 'existente') {
            productoActual = { nombre: producto.nombre, presentacion: '' };
            titulo.textContent = 'Configurar receta';
            nota.textContent = producto.nombre;
            formulario.action = urlStore;
            botonGuardar.textContent = 'Guardar receta';
            notaInventario.textContent = 'Guardar una receta no descuenta inventario.';
            pintarProductoFijo({ nombre: producto.nombre });
        } else {
            productoActual = receta
                ? { nombre: receta.presentacion ? receta.productoNombreBase : receta.producto, presentacion: receta.presentacion ?? '' }
                : { nombre: '', presentacion: '' };
            titulo.textContent = 'Editar receta';
            nota.textContent = [productoActual.nombre, productoActual.presentacion].filter(Boolean).join(' · ');
            formulario.action = urlUpdate.replace('__ID__', receta?.id ?? viejo?._receta_id);
            botonGuardar.textContent = 'Guardar cambios';
            notaInventario.textContent = 'Guardar cambios no descuenta inventario.';
            pintarProductoFijo({ tipo: receta?.tipo, nombre: productoActual.nombre, presentacion: productoActual.presentacion });

            if (receta?.imagenUrl) {
                mostrarVista(receta.imagenUrl);
            }
        }

        const tieneImagen = modo === 'editar' && Boolean(receta?.imagenUrl);
        etiquetaImagen.textContent = tieneImagen ? 'Imagen actual' : 'Imagen del producto';
        botonImagen.textContent = tieneImagen ? 'Cambiar imagen' : 'Seleccionar imagen';

        const origen = viejo ?? receta;

        if (origen) {
            campos.descripcion.value = origen.descripcion ?? '';
        }

        if (viejo && modo === 'crear') {
            campos.tipo.value = viejo.tipo_producto ?? '';
            campos.nombre.value = viejo.nombre_producto ?? '';
            campos.cantidad.value = viejo.presentacion_cantidad ?? '';
            campos.unidad.value = viejo.presentacion_unidad ?? '';
        }

        if (viejo) {
            // Se conservan los índices originales para ubicar cada error en su fila.
            Object.entries(viejo.ingredientes ?? {}).forEach(([indice, ingrediente]) => agregarFila(ingrediente, indice));
        } else if (receta) {
            receta.ingredientes.forEach((i) => agregarFila({ insumo_id: i.insumoId, cantidad: i.cantidad, unidad: i.unidad, observacion: i.observacion }));
        }

        if (filas.children.length === 0) {
            agregarFila();
        }

        actualizarFormula();
        abrir();

        if (viejo) {
            irAlPrimerError();
        }
    }

    function irAlPrimerError() {
        const primero = [...formulario.querySelectorAll('[data-error-servidor], .border-danger, p.text-danger, .recetas-ingrediente-error')]
            .find((el) => el.textContent.trim() !== '' || el.classList.contains('border-danger'));

        if (primero) {
            requestAnimationFrame(() => primero.scrollIntoView({ block: 'center' }));
        }
    }

    [campos.nombre, campos.cantidad, campos.unidad].forEach((campo) => {
        campo.addEventListener('input', actualizarFormula);
        campo.addEventListener('change', actualizarFormula);
    });

    botonAgregar.addEventListener('click', () => {
        const fila = agregarFila();
        fila.scrollIntoView({ block: 'nearest' });
        fila.querySelector('select')?.focus();
    });

    document.addEventListener('click', (evento) => {
        // Menú "más acciones" (⋮): solo uno abierto a la vez y se cierra al hacer clic fuera.
        pagina.querySelectorAll('[data-recetas-menu][open]').forEach((menu) => {
            if (!menu.contains(evento.target)) {
                menu.open = false;
            }
        });

        const disparador = evento.target.closest('[data-receta-accion]');

        if (!disparador || !pagina.contains(disparador)) {
            return;
        }

        preparar({
            modo: disparador.dataset.recetaAccion,
            receta: disparador.dataset.receta ? JSON.parse(disparador.dataset.receta) : null,
        });
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            pagina.querySelectorAll('[data-recetas-menu][open]').forEach((menu) => {
                menu.open = false;
            });
        }
    });

    // Producto histórico sin receta: se abre directamente para configurarlo.
    if (pagina.dataset.configurar) {
        preparar({ modo: 'existente', producto: JSON.parse(pagina.dataset.configurar) });
    }

    // Si el servidor rechazó el formulario, se vuelve a abrir con lo que la persona había escrito.
    const viejo = pagina.dataset.old ? JSON.parse(pagina.dataset.old) : null;

    if (viejo && viejo._modo) {
        const modo = viejo._modo;
        const indice = JSON.parse(pagina.dataset.recetasIndice || '{}');
        const receta = viejo._receta_id ? indice[viejo._receta_id] ?? null : null;
        const producto = modo === 'existente' && viejo.producto_existente_id ? { id: viejo.producto_existente_id, nombre: 'Producto' } : null;
        preparar({ modo, receta, producto, viejo });
    }
}
