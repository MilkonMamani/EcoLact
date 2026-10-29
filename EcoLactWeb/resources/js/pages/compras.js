/*
 * Inventario > Compras y gastos (administrador).
 *
 * - Historial: filtros que se aplican solos y fila clicable (abre la ficha de la compra).
 * - Ficha: pestañas Resumen / Artículos / Comprobantes / Pagos sin recargar (?tab= se mantiene) y
 *   formulario de pago protegido contra doble clic.
 * - Formulario único "Registrar compra", en 3 etapas: datos (con la boleta escaneada como
 *   opción) -> artículos -> revisar y confirmar (condición de pago y recepción, sin marcar nada
 *   por defecto). El OCR solo PROPONE; totales, conversiones a unidad base y stock resultante los calcula el
 *   SERVIDOR (previsualizar). Aquí no hay reglas de negocio: el servidor vuelve a validar todo y
 *   nada se registra (ni stock ni Caja) hasta "Confirmar y registrar".
 */
const pagina = document.querySelector('[data-compras-page]');
const ficha = document.querySelector('[data-compra-ficha]');
const raiz = document.querySelector('[data-compra-form-raiz]');

const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });
const soles = (valor) => `S/ ${Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const simbolo = (unidad) => (unidad === 'unidad' ? 'und' : unidad);
const claveNueva = () => (window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`);

const crear = (tag, clases, texto) => {
    const el = document.createElement(tag);
    if (clases) el.className = clases;
    if (texto !== undefined && texto !== null) el.textContent = texto;
    return el;
};

const claseCampo = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

function campo(rotulo, control, columnas) {
    const etiqueta = crear('label', `block ${columnas}`);
    etiqueta.append(crear('span', 'mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted', rotulo), control);
    return etiqueta;
}

// ------------------------------------------------------------ historial

if (pagina) {
    const filtros = pagina.querySelector('[data-compras-filtros]');
    let esperaBusqueda = null;

    filtros?.querySelectorAll('select, input[type="date"]').forEach((control) => {
        control.addEventListener('change', () => filtros.requestSubmit());
    });
    filtros?.querySelector('input[name="q"]')?.addEventListener('input', () => {
        clearTimeout(esperaBusqueda);
        esperaBusqueda = setTimeout(() => filtros.requestSubmit(), 500);
    });
    filtros?.addEventListener('submit', () => {
        // URL limpia: sin parámetros vacíos.
        filtros.querySelectorAll('input, select').forEach((control) => {
            control.disabled = control.value === '';
        });
    });

    pagina.querySelectorAll('[data-compra-fila]').forEach((fila) => {
        fila.addEventListener('click', (evento) => {
            if (!evento.target.closest('a, button')) window.location.href = fila.dataset.url;
        });
        fila.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter') window.location.href = fila.dataset.url;
        });
    });
}

// ------------------------------------------------------------ ficha de la compra

if (ficha) {
    const pestanas = [...ficha.querySelectorAll('[data-tab]')];
    const paneles = [...ficha.querySelectorAll('[data-panel]')];

    pestanas.forEach((pestana) => pestana.addEventListener('click', (evento) => {
        evento.preventDefault();
        const elegida = pestana.dataset.tab;
        pestanas.forEach((otra) => {
            const activa = otra.dataset.tab === elegida;
            otra.classList.toggle('is-activa', activa);
            otra.setAttribute('aria-selected', String(activa));
        });
        paneles.forEach((panel) => { panel.hidden = panel.dataset.panel !== elegida; });

        const url = new URL(window.location.href);
        url.searchParams.set('tab', elegida);
        window.history.replaceState(null, '', url);
    }));

    ficha.querySelector('[data-pago-form]')?.addEventListener('submit', (evento) => {
        const boton = evento.currentTarget.querySelector('[data-pago-enviar]');
        if (boton.disabled) {
            evento.preventDefault();
            return;
        }
        boton.disabled = true;
        boton.textContent = 'Registrando...';
    });
}

// ------------------------------------------------------------ formulario de compra

if (raiz) {
    const PASOS = ['datos', 'articulos', 'revision'];

    const modal = raiz.closest('[data-modal]');
    const formulario = raiz.querySelector('[data-compra-form]');
    const entradasFoto = [...raiz.querySelectorAll('[data-foto]')];
    const escaneoResultado = raiz.querySelector('[data-escaneo-resultado]');
    const escaneoImg = raiz.querySelector('[data-escaneo-img]');
    const leyendo = raiz.querySelector('[data-leyendo]');
    const errorOcr = raiz.querySelector('[data-error-ocr]');
    const errorOcrTexto = raiz.querySelector('[data-error-ocr-texto]');
    const ocrLectura = raiz.querySelector('[data-ocr-lectura]');
    const documentoEscaneado = raiz.querySelector('[data-documento-escaneado]');
    const claveEnvio = raiz.querySelector('[data-clave-envio]');
    const contenedorLineas = raiz.querySelector('[data-lineas]');
    const calculando = raiz.querySelector('[data-calculando]');
    const mensaje = raiz.querySelector('[data-mensaje]');
    const ocultos = raiz.querySelector('[data-ocultos]');
    const selectCategoria = raiz.querySelector('[data-categoria]');
    const campoImpuesto = raiz.querySelector('[data-impuesto]');
    const botonAtras = raiz.querySelector('[data-atras]');
    const botonSiguiente = raiz.querySelector('[data-siguiente]');
    const botonConfirmar = raiz.querySelector('[data-confirmar]');
    const documentos = raiz.querySelector('[data-documentos]');

    const catalogo = JSON.parse(raiz.dataset.catalogo || '[]');
    const categorias = JSON.parse(raiz.dataset.categorias || '[]');
    const tratamientos = JSON.parse(raiz.dataset.tratamientos || '{}');
    const token = formulario.querySelector('input[name="_token"]').value;
    const insumoPorId = (id) => catalogo.find((insumo) => String(insumo.id) === String(id));
    const categoriaElegida = () => categorias.find((c) => String(c.id) === selectCategoria.value) ?? null;
    const categoriaPorCodigo = (codigo) => categorias.find((c) => c.codigo === codigo) ?? null;
    const icono = (nombre) => raiz.querySelector(`template[data-icono="${nombre}"]`)?.content.cloneNode(true) ?? document.createTextNode('');
    const valor = (nombre) => formulario.querySelector(`[name="${nombre}"]`);
    const marcado = (nombre) => formulario.querySelector(`[name="${nombre}"]:checked`)?.value ?? null;

    let paso = 0;
    let archivo = null;
    let urlEscaneo = null;
    let lectura = null;
    let lineas = [];
    let calculo = null;
    let secuencia = 0;
    let temporizador = null;
    let enviando = false;

    const tratamientoSugerido = () => categoriaElegida()?.tratamiento ?? 'INVENTARIO';

    const nuevaLinea = (datos = {}) => ({
        tratamiento: datos.tratamiento ?? (datos.insumo_id ? 'INVENTARIO' : tratamientoSugerido()),
        texto: datos.texto ?? null,
        estado: datos.estado ?? null,
        presentacionDetectada: datos.presentacion_detectada ?? null,
        opciones: (datos.opciones_presentacion ?? []).map(String),
        insumo_id: datos.insumo_id ? String(datos.insumo_id) : '',
        presentacion_id: datos.presentacion_id ? String(datos.presentacion_id) : '',
        descripcion: datos.descripcion ?? '',
        cantidad: datos.cantidad !== undefined && datos.cantidad !== null ? String(datos.cantidad) : '',
        unidad: datos.unidad ?? '',
        precio_unitario: datos.precio_unitario !== undefined && datos.precio_unitario !== null ? String(datos.precio_unitario) : '',
    });

    const esInventario = (linea) => linea.tratamiento === 'INVENTARIO';
    const completa = (linea) => Number(linea.cantidad) > 0 && linea.precio_unitario !== '' && Number(linea.precio_unitario) >= 0
        && (esInventario(linea) ? Number(linea.insumo_id) > 0 && Number(linea.presentacion_id) > 0 : linea.descripcion.trim() !== '');

    /**
     * Tipo de compra: el elegido en «Más datos» o el que deducirá el servidor de las líneas
     * (RegistrarCompra::categoria): todo inventario, todo bienes, todo servicios u otros gastos.
     */
    function categoriaEfectiva() {
        const elegida = categoriaElegida();
        if (elegida) return { categoria: elegida, automatica: false };
        const distintos = [...new Set(lineas.map((l) => l.tratamiento))];
        const unico = distintos.length === 1 ? distintos[0] : null;
        const codigo = { INVENTARIO: 'INSUMOS_PRODUCCION', BIEN: 'EQUIPOS_HERRAMIENTAS', SERVICIO: 'SERVICIOS' }[unico] ?? 'OTROS_GASTOS';
        return { categoria: categoriaPorCodigo(codigo) ?? categoriaPorCodigo('OTROS_GASTOS'), automatica: true };
    }

    /** tipo: 'error' (lo rechazó el servidor o falta algo para avanzar) o 'pendiente' (solo informa). */
    function pintarMensaje(texto, tipo = 'error') {
        mensaje.replaceChildren();
        if (!texto) return;
        const clases = tipo === 'error' ? 'border-danger/25 bg-danger-soft text-danger' : 'border-text-muted/15 bg-background text-text-muted';
        const caja = crear('div', `flex items-start gap-2 rounded-lg border px-4 py-3 text-sm ${clases}`);
        caja.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        caja.append(icono('alerta'), crear('span', tipo === 'error' ? 'font-medium' : '', texto));
        mensaje.append(caja);
    }

    // ------------------------------------------------ apertura y etapas

    function limpiarFoto() {
        archivo = null;
        lectura = null;
        if (urlEscaneo) URL.revokeObjectURL(urlEscaneo);
        urlEscaneo = null;
        documentoEscaneado.value = '';
        entradasFoto.forEach((entrada) => { entrada.value = ''; });
        escaneoResultado.hidden = true;
        errorOcr.hidden = true;
        leyendo.hidden = true;
        ocrLectura.hidden = true;
    }

    function reiniciar(insumoId = null) {
        calculo = null;
        enviando = false;
        formulario.reset();
        limpiarFoto();
        claveEnvio.value = claveNueva();
        botonConfirmar.textContent = 'Confirmar y registrar';
        raiz.querySelector('[data-documentos-lista]').replaceChildren();
        raiz.querySelector('.compra-mas-datos')?.removeAttribute('open');
        pintarMensaje(null);

        const insumo = insumoId ? insumoPorId(insumoId) : null;
        const unica = insumo && insumo.presentaciones.length === 1 ? insumo.presentaciones[0].id : '';
        lineas = [nuevaLinea(insumo ? { tratamiento: 'INVENTARIO', insumo_id: insumo.id, presentacion_id: unica, descripcion: insumo.nombre } : {})];

        irAPaso(0);
    }

    function irAPaso(indice) {
        paso = indice;
        PASOS.forEach((nombre, i) => {
            formulario.querySelector(`[data-paso="${nombre}"]`).hidden = i !== paso;
            const indicador = formulario.querySelector(`[data-indicador="${nombre}"]`);
            indicador.classList.toggle('is-actual', i === paso);
            indicador.classList.toggle('is-hecho', i < paso);
        });
        botonAtras.hidden = paso === 0;
        botonSiguiente.hidden = paso === PASOS.length - 1;
        botonConfirmar.hidden = paso !== PASOS.length - 1;
        botonConfirmar.disabled = enviando;
        pintarMensaje(null);

        if (PASOS[paso] === 'articulos') pintarLineas();
        if (PASOS[paso] === 'revision') pintarRevision();
        modal.querySelector('[data-modal-panel]')?.scrollTo({ top: 0 });
    }

    /** Lo mínimo para avanzar; el servidor valida todo otra vez al confirmar. */
    function errorDelPaso() {
        switch (PASOS[paso]) {
            case 'datos':
                if (!valor('fecha').value) return 'Indica la fecha de la compra.';
                if (valor('ruc').value && !/^\d{11}$/.test(valor('ruc').value)) return 'El RUC debe tener 11 dígitos (en «Más datos»).';
                if (documentos.files.length > 5) return 'Adjunta como máximo 5 documentos.';
                return null;
            case 'articulos':
                if (lineas.length === 0) return 'Agrega al menos un artículo.';
                if (!lineas.every(completa)) return 'Completa cada artículo: insumo y presentación (o descripción), cantidad y precio unitario.';
                if (!calculo) return 'Espera el cálculo de la compra o corrige lo indicado.';
                return null;
            case 'revision': {
                if (lineas.some(esInventario) && !marcado('recepcion')) return 'Indica si los artículos de inventario ya llegaron.';
                const condicion = marcado('condicion_pago');
                if (!condicion) return 'Elige cómo se paga: contado, crédito o pago parcial.';
                if (condicion !== 'CREDITO' && !valor('metodo_pago').value) return 'Elige el método de pago.';
                if (condicion === 'PARCIAL') {
                    const monto = Number(valor('monto_pago').value);
                    if (!(monto > 0) || monto >= calculo.total) return `El pago de ahora debe ser mayor que cero y menor que el total (${soles(calculo.total)}).`;
                }
                return null;
            }
            default:
                return null;
        }
    }

    botonSiguiente.addEventListener('click', () => {
        const error = errorDelPaso();
        if (error) {
            pintarMensaje(error);
            return;
        }
        irAPaso(Math.min(paso + 1, PASOS.length - 1));
    });
    botonAtras.addEventListener('click', () => irAPaso(Math.max(paso - 1, 0)));

    selectCategoria.addEventListener('change', () => {
        // Una línea vacía toma el destino sugerido del tipo de compra elegido.
        lineas.forEach((linea) => {
            if (!linea.insumo_id && !linea.descripcion && !linea.cantidad) linea.tratamiento = tratamientoSugerido();
        });
    });

    // ------------------------------------------------ etapa 1: boleta opcional (OCR)

    function aplicarLectura(cuerpo) {
        lectura = { cabecera: cuerpo.cabecera ?? {}, lineas: cuerpo.lineas ?? [], advertencia: cuerpo.advertencia ?? null };
        formulario.querySelectorAll('[data-cabecera]').forEach((control) => {
            const propuesto = lectura.cabecera[control.dataset.cabecera];
            if (propuesto !== null && propuesto !== undefined && propuesto !== '') control.value = propuesto;
        });

        if (lectura.lineas.length > 0) {
            lineas = lectura.lineas.map((linea) => nuevaLinea({ ...linea, tratamiento: 'INVENTARIO' }));
            programarCalculo();
        }
    }

    function pintarLectura() {
        const datos = raiz.querySelector('[data-ocr-datos]');
        const filas = [['Proveedor', lectura.cabecera.proveedor], ['Comprobante', lectura.cabecera.numero_comprobante], ['Fecha', lectura.cabecera.fecha], ['Total leído', lectura.cabecera.total != null ? soles(lectura.cabecera.total) : null]];
        datos.replaceChildren(...filas.map(([rotulo, texto]) => {
            const fila = crear('div');
            fila.append(crear('dt', '', rotulo), crear('dd', texto ? '' : 'is-vacio', texto ?? 'No detectado'));
            return fila;
        }));

        const avisos = [];
        const sinReconocer = lectura.lineas.filter((l) => !l.insumo_id).length;
        if (lectura.advertencia) avisos.push(lectura.advertencia);
        if (lectura.lineas.length > 0) avisos.push(`${lectura.lineas.length} ${lectura.lineas.length === 1 ? 'artículo propuesto' : 'artículos propuestos'}: los revisarás en «Artículos».`);
        if (sinReconocer > 0) avisos.push(`${sinReconocer} ${sinReconocer === 1 ? 'artículo no se reconoció' : 'artículos no se reconocieron'} como insumo.`);
        if (lectura.lineas.some((l) => l.estado === 'PRESENTACION_AMBIGUA')) avisos.push('Hay presentaciones con varias opciones posibles: confirma cuál fue.');
        raiz.querySelector('[data-ocr-avisos]').replaceChildren(...avisos.map((texto) => {
            const item = crear('li', 'flex items-start gap-2 text-text-muted');
            item.append(icono('alerta'), crear('span', '', texto));
            return item;
        }));
        ocrLectura.hidden = false;
    }

    async function procesar() {
        if (!archivo) return;

        const datos = new FormData();
        datos.append('imagen', archivo);
        leyendo.hidden = false;
        errorOcr.hidden = true;
        ocrLectura.hidden = true;
        botonSiguiente.disabled = true;

        try {
            const respuesta = await fetch(raiz.dataset.urlBoleta, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: datos,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));

            // La foto queda guardada aunque no se haya podido leer: es el comprobante del registro manual.
            documentoEscaneado.value = cuerpo.documento_escaneado ?? '';

            if (!respuesta.ok || !cuerpo.success) {
                const primerError = cuerpo.errors ? Object.values(cuerpo.errors)[0]?.[0] : null;
                errorOcrTexto.textContent = primerError ?? cuerpo.mensaje ?? 'No se pudo leer la boleta automáticamente.';
                errorOcr.hidden = false;
                return;
            }

            aplicarLectura(cuerpo);
            pintarLectura();
            pintarMensaje('EcoLact completó lo que pudo leer. Revisa cada dato: nada se registra hasta que confirmes.', 'pendiente');
        } catch {
            errorOcrTexto.textContent = 'No se pudo conectar con el servidor para leer la boleta.';
            errorOcr.hidden = false;
        } finally {
            leyendo.hidden = true;
            botonSiguiente.disabled = false;
        }
    }

    entradasFoto.forEach((entrada) => entrada.addEventListener('change', () => {
        const elegido = entrada.files[0] ?? null;
        if (!elegido) return;
        limpiarFoto();
        archivo = elegido;
        urlEscaneo = URL.createObjectURL(archivo);
        escaneoImg.src = urlEscaneo;
        escaneoResultado.hidden = false;
        procesar();
    }));
    raiz.querySelector('[data-reintentar]').addEventListener('click', procesar);
    raiz.querySelector('[data-quitar-foto]').addEventListener('click', () => {
        limpiarFoto();
        pintarMensaje(null);
    });

    documentos.addEventListener('change', () => {
        raiz.querySelector('[data-documentos-lista]').replaceChildren(...[...documentos.files].map((f) => crear('li', '', `${f.name} · ${(f.size / 1024 / 1024).toFixed(1)} MB`)));
        if (documentos.files.length > 5) pintarMensaje('Adjunta como máximo 5 documentos.');
    });

    // ------------------------------------------------ etapa 2: artículos

    function ayudaLinea(linea, insumo) {
        if (!esInventario(linea)) return null;
        if (linea.texto && !linea.insumo_id) {
            return `No se reconoció «${linea.descripcion || linea.texto}» como insumo. Elige el insumo o cambia el destino si es un gasto.`;
        }
        if (insumo && insumo.presentaciones.length === 0) {
            return `${insumo.nombre} no tiene presentaciones comerciales activas. Agrégalas desde Insumos para poder comprarlo.`;
        }
        if (linea.estado === 'PRESENTACION_AMBIGUA' && !linea.presentacion_id) {
            return `La boleta dice «${linea.presentacionDetectada}» y ${insumo?.nombre ?? 'el insumo'} tiene varias así. Elige cuál fue.`;
        }
        if (linea.estado === 'PRESENTACION_NO_CONFIGURADA' && !linea.presentacion_id) {
            return `La boleta dice «${linea.presentacionDetectada}», pero ${insumo?.nombre ?? 'el insumo'} no tiene esa presentación. Elige la correcta.`;
        }
        return null;
    }

    function control(tag, propiedades, alCambiar) {
        const el = crear(tag, claseCampo);
        Object.assign(el, propiedades);
        el.addEventListener(tag === 'select' ? 'change' : 'input', alCambiar);
        return el;
    }

    function pintarLineas() {
        contenedorLineas.replaceChildren();

        lineas.forEach((linea, indice) => {
            const insumo = insumoPorId(linea.insumo_id);
            const tarjeta = crear('div', 'compra-linea');

            const cabecera = crear('div', 'flex items-end justify-between gap-3');
            const izquierda = crear('div', 'min-w-0 flex-1');
            if (linea.texto) izquierda.append(crear('p', 'mb-1 truncate font-mono text-xs text-text-muted', `Leído: ${linea.texto}`));

            const selectTratamiento = control('select', {}, () => {
                linea.tratamiento = selectTratamiento.value;
                if (!esInventario(linea) && !linea.descripcion) linea.descripcion = insumo?.nombre ?? linea.texto ?? '';
                pintarLineas();
                programarCalculo();
            });
            Object.entries(tratamientos).forEach(([codigo, etiqueta]) => selectTratamiento.append(new Option(etiqueta, codigo)));
            selectTratamiento.value = linea.tratamiento;
            izquierda.append(campo('Destino', selectTratamiento, 'sm:max-w-xs'));

            const quitar = crear('button', 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-danger ring-1 ring-inset ring-danger/30 transition hover:bg-danger/10');
            Object.assign(quitar, { type: 'button', title: 'Quitar artículo' });
            quitar.setAttribute('aria-label', `Quitar artículo ${indice + 1}`);
            quitar.append(icono('trash'));
            quitar.addEventListener('click', () => {
                lineas.splice(indice, 1);
                pintarLineas();
                programarCalculo();
            });
            cabecera.append(izquierda, quitar);

            const campos = crear('div', 'grid gap-3 sm:grid-cols-12 sm:items-end');
            const cantidad = control('input', { type: 'number', step: 'any', min: '0', inputMode: 'decimal', placeholder: 'Ej. 2', value: linea.cantidad }, (e) => { linea.cantidad = e.target.value; programarCalculo(); });
            const precio = control('input', { type: 'number', step: '0.01', min: '0', inputMode: 'decimal', placeholder: '0.00', value: linea.precio_unitario }, (e) => { linea.precio_unitario = e.target.value; programarCalculo(); });

            if (esInventario(linea)) {
                const selectInsumo = control('select', {}, () => {
                    linea.insumo_id = selectInsumo.value;
                    const elegido = insumoPorId(linea.insumo_id);
                    // Con una sola presentación no hay nada que elegir.
                    linea.presentacion_id = elegido && elegido.presentaciones.length === 1 ? String(elegido.presentaciones[0].id) : '';
                    linea.opciones = [];
                    pintarLineas();
                    programarCalculo();
                });
                selectInsumo.append(new Option('Seleccionar insumo', ''));
                catalogo.forEach((item) => selectInsumo.append(new Option(item.nombre, String(item.id))));
                selectInsumo.value = linea.insumo_id;

                const selectPresentacion = control('select', {}, () => {
                    linea.presentacion_id = selectPresentacion.value;
                    pintarLineas();
                    programarCalculo();
                });
                selectPresentacion.append(new Option(insumo ? 'Seleccionar presentación' : 'Primero elige el insumo', ''));
                // Si la boleta permitió acotar (p. ej. solo los "Saco"), esas van primero.
                [...(insumo?.presentaciones ?? [])]
                    .sort((a, b) => Number(!linea.opciones.includes(String(a.id))) - Number(!linea.opciones.includes(String(b.id))))
                    .forEach((p) => selectPresentacion.append(new Option(p.etiqueta, String(p.id))));
                selectPresentacion.disabled = !insumo || insumo.presentaciones.length === 0;
                selectPresentacion.value = linea.presentacion_id;

                campos.append(
                    campo('Artículo', selectInsumo, 'sm:col-span-4'),
                    campo('Presentación', selectPresentacion, 'sm:col-span-4'),
                    campo('Cantidad', cantidad, 'sm:col-span-2'),
                    campo('Precio unit. S/', precio, 'sm:col-span-2'),
                );
            } else {
                const descripcion = control('input', { type: 'text', maxLength: 150, placeholder: 'Ej. Gaseosa 3 L', value: linea.descripcion }, (e) => { linea.descripcion = e.target.value; programarCalculo(); });
                const unidad = control('input', { type: 'text', maxLength: 20, placeholder: 'und', value: linea.unidad }, (e) => { linea.unidad = e.target.value; });
                campos.append(
                    campo('Artículo', descripcion, 'sm:col-span-5'),
                    campo('Cantidad', cantidad, 'sm:col-span-2'),
                    campo('Unidad', unidad, 'sm:col-span-2'),
                    campo('Precio unit. S/', precio, 'sm:col-span-3'),
                );
            }

            tarjeta.append(cabecera, campos);

            const textoAyuda = ayudaLinea(linea, insumo);
            if (textoAyuda) tarjeta.append(crear('p', 'text-xs text-warning', textoAyuda));

            // Conversión y subtotal: los escribe pintarCalculo() con lo que responde el servidor.
            const resultado = crear('dl', 'grid grid-cols-2 gap-3 rounded-lg bg-primary-soft/30 px-3 py-2 text-sm sm:grid-cols-3');
            resultado.dataset.resultadoLinea = String(indice);
            resultado.hidden = true;
            tarjeta.append(resultado);

            contenedorLineas.append(tarjeta);
        });

        if (lineas.length === 0) {
            contenedorLineas.append(crear('p', 'rounded-lg border border-dashed border-text-muted/25 px-4 py-6 text-center text-sm text-text-muted', 'No hay artículos. Pulsa «Agregar artículo».'));
        }

        pintarCalculo();
    }

    raiz.querySelector('[data-agregar]').addEventListener('click', () => {
        lineas.push(nuevaLinea());
        pintarLineas();
        programarCalculo();
    });
    campoImpuesto.addEventListener('input', programarCalculo);

    // ------------------------------------------------ cálculo del servidor

    function pintarCalculo() {
        const completas = lineas.map((linea, indice) => ({ linea, indice })).filter(({ linea }) => completa(linea));
        contenedorLineas.querySelectorAll('[data-resultado-linea]').forEach((caja) => { caja.hidden = true; });

        if (calculo) {
            completas.forEach(({ indice }, posicion) => {
                const dato = calculo.lineas[posicion];
                const caja = contenedorLineas.querySelector(`[data-resultado-linea="${indice}"]`);
                if (!dato || !caja) return;

                const par = (rotulo, texto, clases) => {
                    const bloque = crear('div');
                    bloque.append(crear('dt', 'text-xs text-text-muted', rotulo), crear('dd', clases, texto));
                    return bloque;
                };
                const inv = dato.inventario;
                caja.replaceChildren(
                    inv
                        // "2 × Saco 25 kg = 50 kg de Sal" / "1 × Balde 20 L = 20 L = 20,000 ml de Cuajo"
                        ? par('Conversión', `${numero(inv.cantidad)} × ${inv.presentacion} = ${numero(inv.cantidad * inv.presentacion_cantidad)} ${simbolo(inv.presentacion_unidad)}${inv.presentacion_unidad === inv.unidad_base ? '' : ` = ${numero(inv.total)} ${simbolo(inv.unidad_base)}`}`, 'font-medium text-text')
                        : par('Destino', dato.tratamiento_etiqueta, 'font-medium text-text'),
                    inv ? par('Entra al stock', `+${numero(inv.total)} ${simbolo(inv.unidad_base)}`, 'font-bold text-success') : par('Inventario', 'No mueve stock', 'text-text-muted'),
                    par('Subtotal', dato.subtotal !== null ? soles(dato.subtotal) : 'Sin precio', 'font-medium text-text'),
                );
                caja.hidden = false;
            });
        }

        raiz.querySelector('[data-subtotal]').textContent = calculo?.subtotal != null ? soles(calculo.subtotal) : '—';
        raiz.querySelector('[data-total]').textContent = calculo?.total != null ? soles(calculo.total) : '—';
    }

    function programarCalculo() {
        clearTimeout(temporizador);
        calculo = null;
        temporizador = setTimeout(calcular, 300);
    }

    const lineaParaServidor = (l) => ({
        tratamiento: l.tratamiento,
        insumo_id: esInventario(l) ? Number(l.insumo_id) : null,
        presentacion_id: esInventario(l) ? Number(l.presentacion_id) : null,
        descripcion: esInventario(l) ? null : l.descripcion.trim(),
        cantidad: Number(l.cantidad),
        unidad: esInventario(l) ? null : (l.unidad.trim() || null),
        precio_unitario: l.precio_unitario === '' ? null : Number(l.precio_unitario),
    });

    async function calcular() {
        const solicitud = ++secuencia;
        const completas = lineas.filter(completa);
        calculo = null;

        if (completas.length === 0 || completas.length !== lineas.length) {
            calculando.hidden = true;
            pintarCalculo();
            if (PASOS[paso] === 'articulos' && lineas.length > 0 && completas.length < lineas.length) {
                pintarMensaje('Completa cada artículo (o quítalo) para calcular el total.', 'pendiente');
            }
            return;
        }

        calculando.hidden = false;
        if (PASOS[paso] === 'articulos') pintarMensaje(null);

        try {
            const respuesta = await fetch(raiz.dataset.urlPrevisualizar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ lineas: completas.map(lineaParaServidor), impuesto: campoImpuesto.value === '' ? 0 : Number(campoImpuesto.value) }),
            });
            if (solicitud !== secuencia) return;

            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) {
                const primerError = cuerpo.errors ? Object.values(cuerpo.errors)[0]?.[0] : null;
                pintarCalculo();
                pintarMensaje(primerError ?? cuerpo.message ?? 'No se pudo calcular la compra.');
                return;
            }

            calculo = cuerpo.data;
            pintarCalculo();
        } catch {
            if (solicitud === secuencia) {
                pintarCalculo();
                pintarMensaje('No se pudo conectar con el servidor para calcular la compra.');
            }
        } finally {
            if (solicitud === secuencia) calculando.hidden = true;
        }
    }

    // ------------------------------------------------ etapa 3: revisar y confirmar

    function pintarRevision() {
        const resumen = raiz.querySelector('[data-resumen]');
        const { categoria, automatica } = categoriaEfectiva();
        const conInventario = calculo.genera_stock;

        const lista = crear('ul', 'compra-resumen-lineas');
        calculo.lineas.forEach((linea) => {
            const item = crear('li');
            item.append(
                crear('span', '', `${linea.descripcion} · ${linea.inventario ? `${numero(linea.cantidad)} × ${linea.inventario.presentacion}` : `${numero(linea.cantidad)} ${linea.unidad ?? 'und'}`}`),
                crear('strong', '', soles(linea.subtotal ?? 0)),
            );
            lista.append(item);
        });
        const totalItem = crear('li', 'is-total');
        totalItem.append(crear('span', '', 'Total'), crear('strong', '', soles(calculo.total)));
        lista.append(totalItem);

        const adjuntos = (documentoEscaneado.value ? 1 : 0) + documentos.files.length;
        const comprobante = `${valor('tipo_comprobante').selectedOptions[0]?.text ?? ''} ${valor('numero_comprobante').value}`.trim();
        const filas = [
            ['Proveedor comercial', valor('proveedor_comercial').value || 'Sin proveedor'],
            ['Comprobante', `${comprobante || '—'}${adjuntos > 0 ? ` · ${adjuntos} ${adjuntos === 1 ? 'archivo' : 'archivos'}` : ''}`],
            ['Tipo de compra', `${categoria?.nombre ?? '—'}${automatica ? ' (automático)' : ''}`],
        ];
        const datos = crear('dl', 'compra-resumen-datos');
        filas.forEach(([rotulo, texto]) => {
            const fila = crear('div');
            fila.append(crear('dt', '', rotulo), crear('dd', '', texto));
            datos.append(fila);
        });

        const avisos = crear('ul', 'compra-resumen-efectos');
        const aviso = (texto, tipo) => {
            const item = crear('li', `is-${tipo}`);
            item.append(icono(tipo === 'aviso' ? 'alerta' : 'check'), crear('span', '', texto));
            avisos.append(item);
        };
        if (!conInventario) aviso('Es un gasto: no mueve el inventario.', 'ok');
        if (lectura?.cabecera.total != null && Math.abs(lectura.cabecera.total - calculo.total) >= 0.01) {
            aviso(`El total leído en la boleta (${soles(lectura.cabecera.total)}) no coincide con la suma de los artículos (${soles(calculo.total)}). Revisa cantidades, precios e IGV.`, 'aviso');
        }

        resumen.replaceChildren(lista, datos, ...(avisos.childElementCount ? [avisos] : []));

        raiz.querySelector('[data-impacto]').hidden = !conInventario;
        raiz.querySelector('[data-impacto-cuerpo]').replaceChildren(...calculo.impacto.map((fila) => {
            const item = crear('li', 'grid grid-cols-3 gap-x-3 gap-y-1 px-4 py-3 text-sm sm:grid-cols-12 sm:items-center');
            item.append(crear('p', 'col-span-3 font-semibold text-text', fila.insumo));
            const celda = (rotulo, texto, clases) => {
                const p = crear('p', `sm:col-span-3 sm:text-right ${clases}`);
                p.append(crear('span', 'block text-xs font-normal text-text-muted sm:hidden', rotulo), document.createTextNode(texto));
                return p;
            };
            item.append(
                celda('Stock actual', `${numero(fila.stock_actual)} ${simbolo(fila.unidad_base)}`, 'text-text'),
                celda('Ingresa', `+${numero(fila.compra)} ${simbolo(fila.unidad_base)}`, 'font-semibold text-success'),
                celda('Stock resultante', `${numero(fila.stock_resultante)} ${simbolo(fila.unidad_base)}`, 'font-bold text-text'),
            );
            return item;
        }));
        formulario.querySelector('[data-recepcion]').hidden = !conInventario;

        pintarPago();
    }

    /** Campos y saldo según la condición elegida; nada se da por pagado ni por recibido. */
    function pintarPago() {
        if (!calculo) return;
        const condicion = marcado('condicion_pago');
        const recepcion = marcado('recepcion');
        const conPago = condicion === 'CONTADO' || condicion === 'PARCIAL';

        formulario.querySelector('[data-campos-pago]').hidden = !conPago;
        formulario.querySelector('[data-campo-monto]').hidden = condicion !== 'PARCIAL';
        valor('metodo_pago').disabled = !conPago;
        valor('numero_operacion').disabled = !conPago;
        valor('monto_pago').disabled = condicion !== 'PARCIAL';

        const saldo = formulario.querySelector('[data-saldo]');
        saldo.hidden = !condicion;
        if (!condicion) return;

        const total = calculo.total;
        const pagado = condicion === 'CONTADO' ? total : (condicion === 'PARCIAL' ? Math.min(Number(valor('monto_pago').value) || 0, total) : 0);
        const pendiente = Math.max(0, total - pagado);
        const fila = (rotulo, texto, clase = '') => {
            const bloque = crear('div', clase);
            bloque.append(crear('dt', '', rotulo), crear('dd', '', texto));
            return bloque;
        };
        const nota = crear('p', 'compra-saldo-nota');
        const inventario = !calculo.genera_stock ? '' : (recepcion === 'PENDIENTE' ? ' El stock subirá al confirmar la recepción.' : (recepcion === 'RECIBIDA' ? ' El stock sube ahora.' : ''));
        nota.textContent = (pagado > 0 ? `Se registrará una salida de ${soles(pagado)} en Caja.` : 'No sale dinero de Caja ahora.') + inventario;

        saldo.replaceChildren(
            fila('Total', soles(total)),
            fila('Pagas ahora', soles(pagado)),
            fila('Saldo pendiente', soles(pendiente), pendiente > 0 ? 'is-pendiente' : ''),
            nota,
        );
    }
    formulario.querySelectorAll('[data-condicion], [data-recepcion-opcion]').forEach((radio) => radio.addEventListener('change', () => {
        pintarPago();
        pintarMensaje(null);
    }));
    valor('monto_pago').addEventListener('input', pintarPago);

    formulario.addEventListener('submit', (evento) => {
        if (enviando || PASOS[paso] !== 'revision' || !calculo || !lineas.every(completa)) {
            evento.preventDefault();
            return;
        }
        const error = errorDelPaso();
        if (error) {
            evento.preventDefault();
            pintarMensaje(error);
            return;
        }

        ocultos.replaceChildren();
        lineas.map(lineaParaServidor).forEach((linea, indice) => {
            Object.entries(linea).forEach(([nombre, dato]) => {
                if (dato === null) return;
                const oculto = document.createElement('input');
                oculto.type = 'hidden';
                oculto.name = `lineas[${indice}][${nombre}]`;
                oculto.value = dato;
                ocultos.append(oculto);
            });
        });

        // Evita dobles clics; la clave_envio de un solo uso protege también los reintentos.
        enviando = true;
        botonConfirmar.disabled = true;
        botonConfirmar.textContent = 'Registrando...';
    });

    const abrir = (insumoId) => {
        reiniciar(insumoId);
        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    };

    document.addEventListener('click', (evento) => {
        const disparador = evento.target.closest('[data-compra-abrir]');
        if (disparador) abrir(disparador.dataset.compraInsumo ?? null);
    });

    // "Comprar este insumo" (Insumos) llega con ?nueva=1&insumo=ID: el formulario se abre solo.
    if (raiz.dataset.abrir) {
        abrir(raiz.dataset.insumo ?? null);
        if (raiz.dataset.insumo) programarCalculo();
    }
}
