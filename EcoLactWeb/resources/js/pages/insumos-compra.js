/*
 * Pantalla Insumos (administrador): compra de insumos y modales del panel lateral.
 *
 * - "Escanear boleta": foto -> el servidor la guarda, la lee con Tesseract y PROPONE líneas.
 * - "Registrar compra" (encabezado, fila o panel): la misma revisión, sin OCR; admite foto adjunta.
 * - Revisión: la persona corrige insumo, presentación, cantidad y precio unitario. La equivalencia,
 *   el total en unidad base, los subtotales y el stock resultante los calcula el SERVIDOR
 *   (previsualizar); aquí no hay reglas de negocio. El stock cambia solo al confirmar.
 * - Modales del panel: presentación de compra (nueva/editar, con vista previa de la equivalencia),
 *   "Editar presentaciones" y ajuste de stock (merma, pérdida, corrección, inventario físico).
 */
const pagina = document.querySelector('[data-insumos-page]');
const raiz = document.querySelector('[data-compra-insumos]');

const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });
const soles = (valor) => Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function abrirModal(modal) {
    modal.classList.add('is-open');
    document.body.classList.add('overflow-hidden');
}

const crear = (tag, clases, texto) => {
    const el = document.createElement(tag);
    if (clases) el.className = clases;
    if (texto !== undefined) el.textContent = texto;
    return el;
};

const claseCampo = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

// ------------------------------------------------------------ modales del panel

if (pagina) {
    // ---------------- presentación de compra: la equivalencia se ve mientras se escribe.
    const modalPresentacion = document.getElementById('modal-presentacion');
    const formPresentacion = modalPresentacion?.querySelector('[data-presentacion-form]');
    let presentacionActual = null;

    function pintarEquivalencia() {
        if (!formPresentacion || !presentacionActual) return;
        const nombre = formPresentacion.querySelector('[name="nombre"]').value.trim() || 'presentación';
        const cantidad = Number(formPresentacion.querySelector('[name="cantidad"]').value);
        const unidad = formPresentacion.querySelector('[data-presentacion-unidades]').value;
        const factor = presentacionActual.factores?.[unidad];
        const base = presentacionActual.unidad_base;

        formPresentacion.querySelector('[data-presentacion-equivalencia]').textContent = cantidad > 0 && factor
            ? `1 ${nombre.toLowerCase()} = ${numero(cantidad)} ${unidad}${unidad === base ? '' : ` = ${numero(cantidad * factor)} ${base}`}`
            : `Indica cuánto contiene para ver su equivalencia en ${base}.`;
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
            botonEditarLista.querySelector('[data-presentaciones-editar-texto]').textContent = editando ? 'Listo' : 'Editar presentaciones';
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

// ------------------------------------------------------------ compra de insumos

if (raiz) {
    const modal = raiz.closest('[data-modal]');
    const titulo = modal.querySelector('h2');
    const pasoCaptura = raiz.querySelector('[data-paso="captura"]');
    const formulario = raiz.querySelector('[data-compra-form]');
    const entradasFoto = [...raiz.querySelectorAll('[data-foto]')];
    const preview = raiz.querySelector('[data-preview]');
    const previewImg = raiz.querySelector('[data-preview-img]');
    const botonProcesar = raiz.querySelector('[data-procesar]');
    const leyendo = raiz.querySelector('[data-leyendo]');
    const errorOcr = raiz.querySelector('[data-error-ocr]');
    const errorOcrTexto = raiz.querySelector('[data-error-ocr-texto]');
    const imagenBoleta = raiz.querySelector('[data-imagen-boleta]');
    const avisoOcr = raiz.querySelector('[data-aviso-ocr]');
    const miniatura = raiz.querySelector('[data-miniatura]');
    const tituloLineas = raiz.querySelector('[data-titulo-lineas]');
    const contenedorLineas = raiz.querySelector('[data-lineas]');
    const botonAgregar = raiz.querySelector('[data-agregar]');
    const impacto = raiz.querySelector('[data-impacto]');
    const cuerpoImpacto = raiz.querySelector('[data-impacto-cuerpo]');
    const calculando = raiz.querySelector('[data-calculando]');
    const mensaje = raiz.querySelector('[data-mensaje]');
    const ocultos = raiz.querySelector('[data-ocultos]');
    const fotoManual = raiz.querySelector('[data-foto-manual]');
    const imagenManual = raiz.querySelector('[data-imagen-manual]');
    const campoTotal = raiz.querySelector('[name="total"]');
    const ayudaTotal = raiz.querySelector('[data-total-ayuda]');
    const botonConfirmar = raiz.querySelector('[data-confirmar]');

    const catalogo = JSON.parse(raiz.dataset.catalogo || '[]');
    const token = formulario.querySelector('input[name="_token"]').value;
    const insumoPorId = (id) => catalogo.find((insumo) => String(insumo.id) === String(id));
    const icono = (nombre) => raiz.querySelector(`template[data-icono="${nombre}"]`)?.content.cloneNode(true) ?? document.createTextNode('');

    let archivo = null;
    let lineas = [];
    let calculo = null;
    let secuencia = 0;
    let temporizador = null;
    let enviando = false;

    const nuevaLinea = (datos = {}) => ({
        texto: datos.texto ?? null,
        descripcion: datos.descripcion ?? null,
        presentacionDetectada: datos.presentacion_detectada ?? null,
        estado: datos.estado ?? null,
        insumo_id: datos.insumo_id ? String(datos.insumo_id) : '',
        presentacion_id: datos.presentacion_id ? String(datos.presentacion_id) : '',
        opciones: (datos.opciones_presentacion ?? []).map(String),
        cantidad: datos.cantidad !== undefined && datos.cantidad !== null ? String(datos.cantidad) : '',
        precio_unitario: datos.precio_unitario !== undefined && datos.precio_unitario !== null ? String(datos.precio_unitario) : '',
    });

    const completa = (linea) => Number(linea.insumo_id) > 0 && Number(linea.presentacion_id) > 0 && Number(linea.cantidad) > 0;

    function mostrarPaso(paso) {
        pasoCaptura.hidden = paso !== 'captura';
        formulario.hidden = paso !== 'revision';
    }

    function reiniciar(modo, insumoId = null) {
        archivo = null;
        calculo = null;
        enviando = false;
        entradasFoto.forEach((entrada) => { entrada.value = ''; });
        preview.hidden = true;
        errorOcr.hidden = true;
        leyendo.hidden = true;
        botonProcesar.disabled = true;
        imagenBoleta.value = '';
        avisoOcr.hidden = true;
        imagenManual.value = '';
        fotoManual.hidden = false;
        campoTotal.placeholder = '';
        formulario.querySelectorAll('[data-cabecera]').forEach((campo) => {
            if (campo.dataset.cabecera !== 'fecha') campo.value = '';
        });
        botonConfirmar.textContent = 'Confirmar compra';

        if (modo === 'boleta') {
            titulo.textContent = 'Registrar compra desde boleta';
            mostrarPaso('captura');
        } else {
            irAManual(insumoId);
        }
    }

    function irAManual(insumoId = null) {
        titulo.textContent = 'Registrar compra';
        tituloLineas.textContent = 'Insumos comprados';
        avisoOcr.hidden = true;

        const insumo = insumoId ? insumoPorId(insumoId) : null;
        const unica = insumo && insumo.presentaciones.length === 1 ? insumo.presentaciones[0].id : '';
        lineas = [nuevaLinea({ insumo_id: insumo?.id ?? '', presentacion_id: unica })];

        mostrarPaso('revision');
        pintarLineas();
        programarCalculo();
    }

    // ------------------------------------------------ paso 1: foto y OCR

    entradasFoto.forEach((entrada) => entrada.addEventListener('change', () => {
        archivo = entrada.files[0] ?? null;
        errorOcr.hidden = true;
        botonProcesar.disabled = !archivo;
        preview.hidden = !archivo;

        if (archivo) {
            previewImg.src = URL.createObjectURL(archivo);
        }
    }));

    async function procesar() {
        if (!archivo) {
            return;
        }

        const datos = new FormData();
        datos.append('imagen', archivo);
        botonProcesar.disabled = true;
        leyendo.hidden = false;
        errorOcr.hidden = true;

        try {
            const respuesta = await fetch(raiz.dataset.urlBoleta, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: datos,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));

            // La foto queda guardada aunque no se haya podido leer: sirve de respaldo en la compra manual.
            imagenBoleta.value = cuerpo.imagen_boleta ?? '';
            miniatura.src = cuerpo.imagen_url ?? '';

            if (!respuesta.ok || !cuerpo.success) {
                const primerError = cuerpo.errors ? Object.values(cuerpo.errors)[0]?.[0] : null;
                errorOcrTexto.textContent = primerError ?? cuerpo.mensaje ?? 'No se pudo leer la boleta automáticamente.';
                errorOcr.hidden = false;
                return;
            }

            titulo.textContent = 'Registrar compra desde boleta';
            tituloLineas.textContent = 'Compra detectada';
            avisoOcr.hidden = false;
            lineas = cuerpo.lineas.map(nuevaLinea);

            // Datos del comprobante: solo se proponen, nunca bloquean la compra.
            formulario.querySelectorAll('[data-cabecera]').forEach((campo) => {
                const valor = cuerpo.cabecera?.[campo.dataset.cabecera];
                if (valor !== null && valor !== undefined && valor !== '') {
                    campo.value = valor;
                }
            });
            // La foto escaneada ya es el respaldo: no se pide otra.
            fotoManual.hidden = true;

            mostrarPaso('revision');
            pintarLineas();
            programarCalculo();
        } catch {
            errorOcrTexto.textContent = 'No se pudo conectar con el servidor para leer la boleta.';
            errorOcr.hidden = false;
        } finally {
            leyendo.hidden = true;
            botonProcesar.disabled = !archivo;
        }
    }

    botonProcesar.addEventListener('click', procesar);
    raiz.querySelector('[data-reintentar]').addEventListener('click', () => {
        errorOcr.hidden = true;
        procesar();
    });
    raiz.querySelectorAll('[data-ir-manual]').forEach((boton) => boton.addEventListener('click', () => {
        const conFoto = imagenBoleta.value !== '';
        irAManual();
        // Si la foto ya se subió, se conserva como respaldo de la compra manual.
        avisoOcr.hidden = !conFoto;
        fotoManual.hidden = conFoto;
    }));

    // ------------------------------------------------ paso 2: líneas

    function etiquetaEstado(linea) {
        if (linea.estado === null) {
            return null;
        }

        if (!linea.insumo_id) {
            return { texto: 'No reconocido', ok: false };
        }

        if (!linea.presentacion_id) {
            return { texto: linea.estado === 'PRESENTACION_AMBIGUA' ? 'Confirma la presentación' : 'Elige la presentación', ok: false };
        }

        return { texto: 'Reconocido', ok: true };
    }

    function ayuda(linea, insumo) {
        if (linea.texto && !linea.insumo_id) {
            return `No se reconoció «${linea.descripcion}». Selecciona el insumo; EcoLact no crea insumos nuevos.`;
        }

        if (insumo && insumo.presentaciones.length === 0) {
            return `${insumo.nombre} no tiene presentaciones activas. Agrégalas desde su panel para poder comprarlo.`;
        }

        if (linea.estado === 'PRESENTACION_AMBIGUA' && !linea.presentacion_id) {
            return `La boleta dice «${linea.presentacionDetectada}» y ${insumo?.nombre ?? 'el insumo'} tiene varias así. Selecciona cuál fue.`;
        }

        if (linea.estado === 'PRESENTACION_NO_CONFIGURADA' && !linea.presentacion_id) {
            return `La boleta dice «${linea.presentacionDetectada}», pero ${insumo?.nombre ?? 'el insumo'} no tiene esa presentación. Elige la correcta.`;
        }

        return null;
    }

    function pintarLineas() {
        contenedorLineas.replaceChildren();

        lineas.forEach((linea, indice) => {
            const insumo = insumoPorId(linea.insumo_id);
            const tarjeta = crear('div', 'space-y-3 rounded-xl border border-text-muted/15 p-4');

            const cabecera = crear('div', 'flex items-start justify-between gap-3');
            const izquierda = crear('div', 'min-w-0 space-y-1');
            if (linea.texto) {
                izquierda.append(crear('p', 'truncate font-mono text-xs text-text-muted', `Leído: ${linea.texto}`));
            }
            const estado = etiquetaEstado(linea);
            if (estado) {
                const badge = crear('span', `inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${estado.ok ? 'bg-success-soft text-success ring-success/20' : 'bg-warning/10 text-warning ring-warning/30'}`);
                badge.append(icono(estado.ok ? 'check' : 'alerta'), document.createTextNode(estado.texto));
                izquierda.append(badge);
            }
            const quitar = crear('button', 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-danger ring-1 ring-inset ring-danger/30 transition hover:bg-danger/10');
            quitar.type = 'button';
            quitar.title = 'Quitar línea';
            quitar.setAttribute('aria-label', 'Quitar línea');
            quitar.append(icono('trash'));
            quitar.addEventListener('click', () => {
                lineas.splice(indice, 1);
                pintarLineas();
                programarCalculo();
            });
            cabecera.append(izquierda, quitar);

            const campos = crear('div', 'grid gap-3 sm:grid-cols-12 sm:items-end');

            const campoInsumo = crear('label', 'block sm:col-span-4');
            campoInsumo.append(crear('span', 'mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted', 'Insumo'));
            const selectInsumo = crear('select', claseCampo);
            selectInsumo.append(new Option('Seleccionar insumo', ''));
            catalogo.forEach((item) => selectInsumo.append(new Option(item.nombre, String(item.id))));
            selectInsumo.value = linea.insumo_id;
            selectInsumo.addEventListener('change', () => {
                linea.insumo_id = selectInsumo.value;
                const elegido = insumoPorId(linea.insumo_id);
                // Con una sola presentación no hay nada que elegir.
                linea.presentacion_id = elegido && elegido.presentaciones.length === 1 ? String(elegido.presentaciones[0].id) : '';
                linea.opciones = [];
                pintarLineas();
                programarCalculo();
            });
            campoInsumo.append(selectInsumo);

            const campoCantidad = crear('label', 'block sm:col-span-2');
            campoCantidad.append(crear('span', 'mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted', 'Cantidad'));
            const inputCantidad = crear('input', claseCampo);
            Object.assign(inputCantidad, { type: 'number', step: 'any', min: '0', inputMode: 'decimal', placeholder: 'Ej. 2', value: linea.cantidad });
            inputCantidad.addEventListener('input', () => {
                linea.cantidad = inputCantidad.value;
                programarCalculo();
            });
            campoCantidad.append(inputCantidad);

            const campoPresentacion = crear('label', 'block sm:col-span-4');
            campoPresentacion.append(crear('span', 'mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted', 'Presentación'));
            const selectPresentacion = crear('select', claseCampo);
            selectPresentacion.append(new Option(insumo ? 'Seleccionar presentación' : 'Primero elige el insumo', ''));
            // Si la boleta permitió acotar (p. ej. solo los "Saco"), esas van primero.
            const presentaciones = [...(insumo?.presentaciones ?? [])].sort((a, b) => Number(!linea.opciones.includes(String(a.id))) - Number(!linea.opciones.includes(String(b.id))));
            presentaciones.forEach((presentacion) => selectPresentacion.append(new Option(presentacion.etiqueta, String(presentacion.id))));
            selectPresentacion.disabled = !insumo || presentaciones.length === 0;
            selectPresentacion.value = linea.presentacion_id;
            selectPresentacion.addEventListener('change', () => {
                linea.presentacion_id = selectPresentacion.value;
                pintarLineas();
                programarCalculo();
            });
            campoPresentacion.append(selectPresentacion);

            const campoPrecio = crear('label', 'block sm:col-span-2');
            campoPrecio.append(crear('span', 'mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted', 'Precio unit. S/'));
            const inputPrecio = crear('input', claseCampo);
            Object.assign(inputPrecio, { type: 'number', step: '0.01', min: '0', inputMode: 'decimal', placeholder: 'Opcional', value: linea.precio_unitario });
            inputPrecio.addEventListener('input', () => {
                linea.precio_unitario = inputPrecio.value;
                programarCalculo();
            });
            campoPrecio.append(inputPrecio);

            campos.append(campoInsumo, campoPresentacion, campoCantidad, campoPrecio);
            tarjeta.append(cabecera, campos);

            const textoAyuda = ayuda(linea, insumo);
            if (textoAyuda) {
                tarjeta.append(crear('p', 'text-xs text-warning', textoAyuda));
            }

            // Equivalencia y total: los escribe pintarCalculo() con lo que responde el servidor.
            const resultado = crear('dl', 'grid grid-cols-2 gap-3 rounded-lg bg-primary-soft/30 px-3 py-2 text-sm sm:grid-cols-3');
            resultado.dataset.resultadoLinea = String(indice);
            resultado.hidden = true;
            tarjeta.append(resultado);

            contenedorLineas.append(tarjeta);
        });

        if (lineas.length === 0) {
            contenedorLineas.append(crear('p', 'rounded-lg border border-dashed border-text-muted/25 px-4 py-6 text-center text-sm text-text-muted', 'No hay líneas. Pulsa «Agregar otro insumo».'));
        }

        pintarCalculo();
    }

    botonAgregar.addEventListener('click', () => {
        lineas.push(nuevaLinea());
        pintarLineas();
        programarCalculo();
    });

    // ------------------------------------------------ cálculo del servidor

    /** tipo: 'error' (lo rechazó el servidor) o 'pendiente' (falta completar algo; no es un error). */
    function pintarMensaje(texto, tipo = 'error') {
        mensaje.replaceChildren();
        if (!texto) {
            return;
        }
        const clases = tipo === 'error' ? 'border-danger/25 bg-danger-soft text-danger' : 'border-text-muted/15 bg-background text-text-muted';
        const caja = crear('div', `flex items-start gap-2 rounded-lg border px-4 py-3 text-sm ${clases}`);
        caja.append(icono('alerta'), crear('span', tipo === 'error' ? 'font-medium' : '', texto));
        mensaje.append(caja);
    }

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
                caja.replaceChildren(
                    par('Equivalencia por unidad', `${dato.presentacion} = ${numero(dato.equivalencia)} ${dato.unidad_base}`, 'font-medium text-text'),
                    par('Entra al stock', `${numero(dato.cantidad)} × ${numero(dato.equivalencia)} = +${numero(dato.total)} ${dato.unidad_base}`, 'font-bold text-success'),
                    par('Subtotal', dato.subtotal !== null ? `S/ ${soles(dato.subtotal)}` : 'Sin precio', dato.subtotal !== null ? 'font-medium text-text' : 'text-text-muted'),
                );
                caja.hidden = false;
            });

            cuerpoImpacto.replaceChildren(...calculo.impacto.map((fila) => {
                const item = crear('li', 'grid grid-cols-3 gap-x-3 gap-y-1 px-4 py-3 text-sm sm:grid-cols-12 sm:items-center');
                item.append(crear('p', 'col-span-3 font-semibold text-text sm:col-span-3', fila.insumo));
                const celda = (rotulo, texto, clases) => {
                    const p = crear('p', `sm:col-span-3 sm:text-right ${clases}`);
                    p.append(crear('span', 'block text-xs font-normal text-text-muted sm:hidden', rotulo), document.createTextNode(texto));
                    return p;
                };
                item.append(
                    celda('Stock actual', `${numero(fila.stock_actual)} ${fila.unidad_base}`, 'text-text'),
                    celda('Compra', `+${numero(fila.compra)} ${fila.unidad_base}`, 'font-semibold text-success'),
                    celda('Stock resultante', `${numero(fila.stock_resultante)} ${fila.unidad_base}`, 'font-bold text-text'),
                );
                return item;
            }));
        }

        impacto.hidden = !calculo;

        // Si cada línea tiene precio, el total sale solo (salvo que se escriba otro).
        const totalPrecios = calculo?.total_precios ?? null;
        campoTotal.placeholder = totalPrecios !== null ? soles(totalPrecios) : '';
        ayudaTotal.textContent = totalPrecios !== null
            ? `Si lo dejas vacío se usará S/ ${soles(totalPrecios)} (suma de las líneas).`
            : 'Opcional si indicas el precio unitario de cada línea.';
        const todoListo = lineas.length > 0 && completas.length === lineas.length && calculo !== null;
        botonConfirmar.disabled = !todoListo || enviando;

        if (lineas.length > 0 && completas.length < lineas.length) {
            pintarMensaje('Completa insumo, cantidad y presentación de cada línea, o quita las que no correspondan.', 'pendiente');
        }
    }

    function programarCalculo() {
        clearTimeout(temporizador);
        botonConfirmar.disabled = true;
        temporizador = setTimeout(calcular, 300);
    }

    async function calcular() {
        const solicitud = ++secuencia;
        const completas = lineas.filter(completa);
        calculo = null;
        pintarMensaje(null);

        if (completas.length === 0) {
            calculando.hidden = true;
            pintarCalculo();
            return;
        }

        calculando.hidden = false;

        try {
            const respuesta = await fetch(raiz.dataset.urlPrevisualizar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ lineas: completas.map((l) => ({ insumo_id: Number(l.insumo_id), presentacion_id: Number(l.presentacion_id), cantidad: Number(l.cantidad), precio_unitario: l.precio_unitario === '' ? null : Number(l.precio_unitario) })) }),
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

    formulario.addEventListener('submit', (evento) => {
        if (enviando || botonConfirmar.disabled || !lineas.every(completa)) {
            evento.preventDefault();
            return;
        }

        ocultos.replaceChildren();
        lineas.forEach((linea, indice) => {
            [['insumo_id', linea.insumo_id], ['presentacion_id', linea.presentacion_id], ['cantidad', linea.cantidad], ['precio_unitario', linea.precio_unitario]].forEach(([campo, valor]) => {
                const oculto = document.createElement('input');
                oculto.type = 'hidden';
                oculto.name = `lineas[${indice}][${campo}]`;
                oculto.value = valor;
                ocultos.append(oculto);
            });
        });

        // Evita dobles clics: el servidor vuelve a calcular y validar todo.
        enviando = true;
        botonConfirmar.disabled = true;
        botonConfirmar.textContent = 'Registrando...';
    });

    document.addEventListener('click', (evento) => {
        const disparador = evento.target.closest('[data-compra-abrir]');
        if (!disparador) return;

        reiniciar(disparador.dataset.compraAbrir, disparador.dataset.compraInsumo ?? null);
        abrirModal(modal);
    });
}
