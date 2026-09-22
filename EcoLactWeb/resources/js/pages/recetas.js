/*
 * Módulo Recetas. Una receta es solo configuración y es el ORIGEN del producto:
 * este archivo arma el formulario (datos del producto, imagen con
 * previsualización, ingredientes con su unidad autocompletada) y muestra en
 * vivo el producto y el nombre de receta que se generarán. No calcula ni
 * descuenta inventario; el nombre real lo genera el servidor.
 */
const pagina = document.querySelector('[data-recetas-page]');

if (pagina) {
    const modal = document.getElementById('modal-receta');
    const formulario = modal.querySelector('[data-receta-form]');
    const titulo = formulario.querySelector('[data-receta-titulo]');
    const nota = formulario.querySelector('[data-nota-modo]');
    const metodo = formulario.querySelector('[data-metodo]');
    const productoExistente = formulario.querySelector('[data-producto-existente]');
    const bloqueDatos = formulario.querySelector('[data-datos-producto]');
    const productoFijo = formulario.querySelector('[data-producto-fijo]');
    const productoFijoNombre = formulario.querySelector('[data-producto-fijo-nombre]');
    const campos = {
        tipo: formulario.querySelector('[name="tipo_producto"]'),
        nombre: formulario.querySelector('[name="nombre_producto"]'),
        cantidad: formulario.querySelector('[name="presentacion_cantidad"]'),
        unidad: formulario.querySelector('[name="presentacion_unidad"]'),
        descripcion: formulario.querySelector('[name="descripcion"]'),
    };
    const imagenInput = formulario.querySelector('[data-imagen-input]');
    const imagenVista = formulario.querySelector('[data-imagen-vista]');
    const imagenVacia = formulario.querySelector('[data-imagen-vacia]');
    const imagenError = formulario.querySelector('[data-imagen-error]');
    const etiquetaImagen = formulario.querySelector('label[for="imagen"]');
    const resultadoTexto = formulario.querySelector('[data-resultado-texto]');
    const recetaNombre = formulario.querySelector('[data-receta-nombre]');
    const filas = formulario.querySelector('[data-ingredientes]');
    const botonAgregar = formulario.querySelector('[data-agregar-ingrediente]');
    const botonGuardar = formulario.querySelector('[data-guardar]');

    const insumos = JSON.parse(pagina.dataset.insumos || '[]');
    const urlStore = pagina.dataset.urlStore;
    const urlUpdate = pagina.dataset.urlUpdate;
    const MAX_BYTES = 2 * 1024 * 1024;
    const TIPOS_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    // Nombre del producto ya existente (modo editar / configurar): reemplaza a los campos del producto.
    let productoActual = null;
    let urlVistaPrevia = null;

    const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });
    const insumoPorId = (id) => insumos.find((i) => String(i.id) === String(id));

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
    }

    function opcionesUnidad(select, insumoId, elegida) {
        select.replaceChildren();
        const insumo = insumoPorId(insumoId);
        const disponibles = insumo ? insumo.compatibles : {};

        if (!insumo) {
            const vacia = crear('option', '', '—');
            vacia.value = '';
            select.append(vacia);
            return;
        }

        Object.entries(disponibles).forEach(([codigo, etiqueta]) => {
            const opcion = crear('option', '', etiqueta);
            opcion.value = codigo;
            opcion.selected = codigo === (elegida ?? insumo.unidad_base);
            select.append(opcion);
        });
    }

    function nombreCompleto() {
        if (productoActual) {
            return productoActual.nombre;
        }

        const base = campos.nombre.value.trim().replace(/\s+/g, ' ');
        const cantidad = campos.cantidad.value;
        const unidad = campos.unidad.value;

        if (!base || !cantidad || !unidad) {
            return '';
        }

        return `${base} ${Number(cantidad)} ${unidad}`;
    }

    function actualizarResultado() {
        const nombre = nombreCompleto();
        const base = productoActual ? productoActual.nombre : campos.nombre.value.trim();
        const presentacion = productoActual ? '' : `${campos.cantidad.value} ${campos.unidad.value}`.trim();

        resultadoTexto.textContent = nombre
            ? (productoActual ? `1 ${productoActual.nombre}` : `1 ${base} de ${presentacion}`)
            : '—';
        recetaNombre.textContent = nombre ? `Receta ${nombre}` : '—';
    }

    function agregarFila(datos = {}) {
        const indice = Number(filas.dataset.contador || 0);
        filas.dataset.contador = String(indice + 1);

        const fila = crear('div', 'grid grid-cols-12 items-start gap-2');

        const celdaInsumo = crear('div', 'col-span-12 sm:col-span-3');
        const insumoSelect = crear('select', claseCampo);
        insumoSelect.name = `ingredientes[${indice}][insumo_id]`;
        insumoSelect.required = true;
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
        celdaInsumo.append(insumoSelect);

        const celdaCantidad = crear('div', 'col-span-6 sm:col-span-2');
        const cantidad = crear('input', claseCampo);
        cantidad.type = 'number';
        cantidad.step = 'any';
        cantidad.min = '0';
        cantidad.required = true;
        cantidad.name = `ingredientes[${indice}][cantidad]`;
        cantidad.placeholder = 'Cantidad';
        cantidad.setAttribute('aria-label', 'Cantidad');
        cantidad.value = datos.cantidad ?? '';
        celdaCantidad.append(cantidad);

        const celdaUnidad = crear('div', 'col-span-6 sm:col-span-2');
        const unidad = crear('select', claseCampo);
        unidad.name = `ingredientes[${indice}][unidad]`;
        unidad.required = true;
        unidad.setAttribute('aria-label', 'Unidad');
        opcionesUnidad(unidad, datos.insumo_id, datos.unidad);
        celdaUnidad.append(unidad);

        const celdaObservacion = crear('div', 'col-span-10 sm:col-span-4');
        const observacion = crear('input', claseCampo);
        observacion.type = 'text';
        observacion.maxLength = 200;
        observacion.name = `ingredientes[${indice}][observacion]`;
        observacion.placeholder = 'Observación (opcional)';
        observacion.setAttribute('aria-label', 'Observación');
        observacion.value = datos.observacion ?? '';
        celdaObservacion.append(observacion);

        const celdaQuitar = crear('div', 'col-span-2 sm:col-span-1 sm:text-right');
        const quitar = crear('button', 'inline-flex h-9 w-9 items-center justify-center rounded-lg bg-danger/10 text-danger ring-1 ring-inset ring-danger/30 hover:brightness-95', '✕');
        quitar.type = 'button';
        quitar.title = 'Quitar ingrediente';
        quitar.setAttribute('aria-label', 'Quitar ingrediente');
        quitar.addEventListener('click', () => fila.remove());
        celdaQuitar.append(quitar);

        // La unidad se autocompleta con la unidad base del insumo elegido.
        insumoSelect.addEventListener('change', () => opcionesUnidad(unidad, insumoSelect.value));

        fila.append(celdaInsumo, celdaCantidad, celdaUnidad, celdaObservacion, celdaQuitar);
        filas.append(fila);
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
        imagenError.textContent = '';
    }

    function mostrarImagenActual(url) {
        if (url) {
            imagenVista.src = url;
            imagenVista.classList.remove('hidden');
            imagenVacia.classList.add('hidden');
        }
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
        imagenVista.src = urlVistaPrevia;
        imagenVista.classList.remove('hidden');
        imagenVacia.classList.add('hidden');
    });

    function preparar({ modo, receta = null, producto = null, viejo = null }) {
        formulario.reset();
        filas.replaceChildren();
        filas.dataset.contador = '0';
        limpiarImagen();
        productoActual = null;

        formulario.querySelector('[name="_modo"]').value = modo;
        formulario.querySelector('[name="_receta_id"]').value = receta?.id ?? '';
        metodo.disabled = modo !== 'editar';
        productoExistente.disabled = modo !== 'existente';
        productoExistente.value = modo === 'existente' ? producto.id : '';

        const conProducto = modo !== 'crear';
        bloqueDatos.classList.toggle('hidden', conProducto);
        Object.values(campos).forEach((campo) => {
            if (campo !== campos.descripcion) {
                campo.disabled = conProducto;
            }
        });
        productoFijo.classList.toggle('hidden', !conProducto);

        if (modo === 'crear') {
            titulo.textContent = 'Nueva receta';
            nota.textContent = 'Define el producto y los ingredientes necesarios para producir una unidad base.';
            formulario.action = urlStore;
            etiquetaImagen.textContent = 'Imagen del producto';
            botonGuardar.textContent = 'Guardar receta';
        } else if (modo === 'existente') {
            productoActual = { nombre: producto.nombre };
            productoFijoNombre.textContent = producto.nombre;
            titulo.textContent = 'Configurar receta';
            nota.textContent = 'Este producto ya existe pero todavía no tiene fórmula. Al guardar se crea su primera receta, sin duplicar el producto.';
            formulario.action = urlStore;
            etiquetaImagen.textContent = 'Imagen del producto';
            botonGuardar.textContent = 'Guardar receta';
        } else {
            productoActual = { nombre: receta.producto };
            productoFijoNombre.textContent = receta.producto;
            titulo.textContent = `Editar ${receta.nombre}`;
            nota.textContent = receta.enUso
                ? `Esta receta ya se usó en producciones: se conservará tal como está y se creará la versión ${receta.version + 1}. El producto y su imagen siguen siendo los mismos.`
                : 'Esta receta todavía no se usó en ninguna producción, por eso se edita directamente.';
            formulario.action = urlUpdate.replace('__ID__', receta.id);
            etiquetaImagen.textContent = 'Cambiar imagen del producto (opcional)';
            mostrarImagenActual(receta.imagenUrl);
            botonGuardar.textContent = receta.enUso ? 'Guardar como nueva versión' : 'Guardar cambios';
        }

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

        const ingredientes = viejo
            ? Object.values(viejo.ingredientes ?? {})
            : (receta ? receta.ingredientes.map((i) => ({ insumo_id: i.insumoId, cantidad: i.cantidad, unidad: i.unidad, observacion: i.observacion })) : []);

        if (ingredientes.length > 0) {
            ingredientes.forEach((ingrediente) => agregarFila(ingrediente));
        } else {
            agregarFila();
        }

        actualizarResultado();
        abrir();
    }

    [campos.nombre, campos.cantidad, campos.unidad].forEach((campo) => {
        campo.addEventListener('input', actualizarResultado);
        campo.addEventListener('change', actualizarResultado);
    });
    botonAgregar.addEventListener('click', () => agregarFila());

    document.addEventListener('click', (evento) => {
        const disparador = evento.target.closest('[data-receta-accion]');

        if (!disparador || !pagina.contains(disparador)) {
            return;
        }

        preparar({
            modo: disparador.dataset.recetaAccion,
            receta: disparador.dataset.receta ? JSON.parse(disparador.dataset.receta) : null,
        });
    });

    // Producto histórico sin receta: se abre directamente para configurarlo.
    if (pagina.dataset.configurar) {
        preparar({ modo: 'existente', producto: JSON.parse(pagina.dataset.configurar) });
    }

    // Si el servidor rechazó el formulario, se vuelve a abrir con lo que la persona había escrito.
    const viejo = pagina.dataset.old ? JSON.parse(pagina.dataset.old) : null;

    if (viejo && viejo.ingredientes) {
        const modo = viejo._modo || 'crear';
        const indice = JSON.parse(pagina.dataset.recetasIndice || '{}');
        const receta = viejo._receta_id ? indice[viejo._receta_id] ?? null : null;
        const producto = modo === 'existente' && viejo.producto_existente_id ? { id: viejo.producto_existente_id, nombre: 'Producto' } : null;
        preparar({ modo, receta, producto, viejo });
    }
}
