/*
 * Modal "Nueva producción": la persona elige producto + cantidad y, en cuanto ambos están
 * completos, el servidor calcula los insumos (receta x cantidad, sumados entre productos, con
 * la leche primero) y si alcanza el stock. Este archivo SOLO pinta lo que el servidor responde:
 * no contiene reglas de negocio ni cálculo de recetas. Elegir, cambiar cantidades y previsualizar
 * NUNCA descuentan inventario; el descuento ocurre únicamente al enviar el formulario.
 */
const raiz = document.querySelector('[data-produccion-nueva]');
const formulario = raiz?.querySelector('[data-produccion-form]');

if (raiz && formulario) {
    const modal = raiz.closest('[data-modal]');
    const contenedorLineas = raiz.querySelector('[data-lineas]');
    const botonAgregarLinea = raiz.querySelector('[data-agregar-linea]');
    const inputsOcultos = raiz.querySelector('[data-inputs-ocultos]');
    const sinCalculo = raiz.querySelector('[data-sin-calculo]');
    const avisos = raiz.querySelector('[data-avisos]');
    const calculando = raiz.querySelector('[data-calculando]');
    const panelRecursos = raiz.querySelector('[data-recursos]');
    const resumenLineas = raiz.querySelector('[data-resumen-lineas]');
    const cuerpoRecursos = raiz.querySelector('[data-recursos-cuerpo]');
    const botonIniciar = raiz.querySelector('[data-iniciar]');

    const urlRequerimientos = raiz.dataset.urlRequerimientos;
    const productos = JSON.parse(raiz.dataset.productos || '[]');
    const token = formulario.querySelector('input[name="_token"]')?.value ?? '';

    const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });
    const conUnidad = (valor, unidad) => {
        if (unidad === 'unidad') {
            return `${numero(valor)} ${Number(valor) === 1 ? 'unidad' : 'unidades'}`;
        }

        return `${numero(valor)} ${unidad}`;
    };
    const icono = (nombre) => raiz.querySelector(`template[data-icono="${nombre}"]`)?.content.cloneNode(true);

    const iniciales = JSON.parse(raiz.dataset.lineasIniciales || '[]').map((l) => ({
        producto_id: String(l.producto_id ?? ''),
        cantidad: String(l.cantidad ?? ''),
    }));
    let lineas = iniciales.length > 0 ? iniciales : [{ producto_id: '', cantidad: '' }];
    let secuencia = 0;
    let temporizador = null;
    let enviando = false;

    const crear = (tag, clases, texto) => {
        const el = document.createElement(tag);
        if (clases) el.className = clases;
        if (texto !== undefined) el.textContent = texto;
        return el;
    };

    const claseCampo = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

    const lineasCompletas = () => lineas.filter((l) => Number(l.producto_id) > 0 && Number(l.cantidad) > 0);

    function pintarLineas() {
        contenedorLineas.replaceChildren();

        lineas.forEach((linea, indice) => {
            const fila = crear('div', 'grid gap-3 sm:grid-cols-12 sm:items-end');

            const campoProducto = crear('div', 'sm:col-span-7');
            const idProducto = `produccion-producto-${indice}`;
            const etiquetaProducto = crear('label', 'mb-1 block text-sm font-medium text-text', 'Producto');
            etiquetaProducto.htmlFor = idProducto;
            const selector = crear('select', claseCampo);
            selector.id = idProducto;
            selector.append(new Option('Elegir un producto', ''));

            // Un producto ya elegido en otra fila no se repite: se cambia su cantidad.
            const elegidosEnOtras = lineas.filter((_, i) => i !== indice).map((l) => l.producto_id);
            productos.forEach((producto) => {
                const opcion = new Option(producto.etiqueta ?? producto.nombre, String(producto.id));
                opcion.disabled = elegidosEnOtras.includes(String(producto.id));
                selector.append(opcion);
            });
            selector.value = linea.producto_id;
            selector.addEventListener('change', () => {
                linea.producto_id = selector.value;
                pintarLineas();
                programarCalculo();
            });
            campoProducto.append(etiquetaProducto, selector);

            const campoCantidad = crear('div', lineas.length > 1 ? 'sm:col-span-4' : 'sm:col-span-5');
            const idCantidad = `produccion-cantidad-${indice}`;
            const etiquetaCantidad = crear('label', 'mb-1 block text-sm font-medium text-text', 'Cantidad a producir');
            etiquetaCantidad.htmlFor = idCantidad;
            const cantidad = crear('input', claseCampo);
            cantidad.id = idCantidad;
            cantidad.type = 'number';
            cantidad.inputMode = 'decimal';
            cantidad.step = 'any';
            cantidad.min = '0';
            cantidad.placeholder = 'Ej. 10';
            cantidad.value = linea.cantidad;
            cantidad.addEventListener('input', () => {
                linea.cantidad = cantidad.value;
                pintarOcultos();
                programarCalculo();
            });
            cantidad.addEventListener('keydown', (evento) => {
                if (evento.key === 'Enter') {
                    evento.preventDefault();
                }
            });
            campoCantidad.append(etiquetaCantidad, cantidad);

            fila.append(campoProducto, campoCantidad);

            if (lineas.length > 1) {
                const celdaQuitar = crear('div', 'flex justify-end sm:col-span-1');
                const quitar = crear('button', 'inline-flex h-9 w-9 items-center justify-center rounded-lg text-danger ring-1 ring-inset ring-danger/30 transition hover:bg-danger/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary');
                quitar.type = 'button';
                quitar.title = 'Quitar producto';
                quitar.setAttribute('aria-label', 'Quitar producto');
                quitar.append(icono('trash') ?? document.createTextNode('✕'));
                quitar.addEventListener('click', () => {
                    lineas.splice(indice, 1);
                    pintarLineas();
                    programarCalculo();
                });
                celdaQuitar.append(quitar);
                fila.append(celdaQuitar);
            }

            contenedorLineas.append(fila);
        });

        botonAgregarLinea.hidden = lineas.length >= productos.length;
        pintarOcultos();
    }

    /** Solo se envían las filas completas: lo que se previsualizó es lo que se inicia. */
    function pintarOcultos() {
        inputsOcultos.replaceChildren();

        lineasCompletas().forEach((linea, indice) => {
            [['producto_id', linea.producto_id], ['cantidad', linea.cantidad]].forEach(([campo, valor]) => {
                const oculto = document.createElement('input');
                oculto.type = 'hidden';
                oculto.name = `productos[${indice}][${campo}]`;
                oculto.value = valor;
                inputsOcultos.append(oculto);
            });
        });
    }

    function pintarAviso(texto) {
        const aviso = crear('div', 'flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger');
        const marca = icono('alerta');

        if (marca) {
            aviso.append(marca);
        }

        aviso.append(crear('span', 'text-text', texto));
        avisos.append(aviso);
    }

    function badgeEstado(suficiente) {
        const badge = crear(
            'span',
            `inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${suficiente ? 'bg-success/10 text-success ring-success/20' : 'bg-danger/10 text-danger ring-danger/20'}`,
        );
        const marca = icono(suficiente ? 'check' : 'alerta');

        if (marca) {
            badge.append(marca);
        }

        badge.append(document.createTextNode(suficiente ? 'Suficiente' : 'Insuficiente'));

        return badge;
    }

    function pintarResultado(datos) {
        avisos.replaceChildren();
        resumenLineas.replaceChildren();
        cuerpoRecursos.replaceChildren();

        datos.lineas.forEach((linea) => {
            const fila = crear('div', 'flex flex-wrap items-baseline justify-between gap-x-3');
            fila.append(crear('p', 'text-sm font-semibold text-text', linea.etiqueta ?? linea.producto));
            fila.append(crear('p', 'text-sm text-text-muted', `Cantidad planificada: ${conUnidad(linea.cantidad, linea.unidad)}`));
            resumenLineas.append(fila);
        });

        datos.insumos.forEach((insumo, indice) => {
            const esLeche = indice === 0 && insumo.nombre.toLowerCase() === 'leche';
            const fila = crear('li', 'flex flex-col gap-2 py-2.5 sm:flex-row sm:items-center sm:gap-4');

            const nombre = crear('div', 'flex min-w-0 items-center gap-2 sm:w-1/3');
            if (esLeche) {
                const gota = crear('span', 'text-info');
                gota.append(icono('leche') ?? document.createTextNode(''));
                nombre.append(gota);
            }
            nombre.append(crear('span', 'font-medium text-text', insumo.nombre));
            if (esLeche) {
                nombre.append(crear('span', 'text-xs text-text-muted', 'desde Acopio'));
            }

            const cifras = crear('div', 'grid flex-1 grid-cols-2 gap-3 text-sm');
            const necesario = crear('p', 'text-text-muted');
            necesario.append('Necesario: ', crear('span', 'font-semibold text-text', `${numero(insumo.necesario)} ${insumo.unidad}`));
            const disponible = crear('p', 'text-text-muted');
            disponible.append('Disponible: ', crear('span', insumo.suficiente ? 'text-text' : 'font-semibold text-danger', `${numero(insumo.disponible)} ${insumo.unidad}`));
            cifras.append(necesario, disponible);

            const estado = crear('div', 'sm:shrink-0');
            estado.append(badgeEstado(insumo.suficiente));

            fila.append(nombre, cifras, estado);
            cuerpoRecursos.append(fila);
        });

        if (datos.insumos.length === 0) {
            cuerpoRecursos.append(crear('li', 'py-2 text-sm text-text-muted', 'La receta no tiene insumos registrados.'));
        }

        panelRecursos.hidden = false;
        sinCalculo.hidden = true;

        // Los mensajes vienen ya redactados por el servidor (misma redacción que el bloqueo real).
        datos.mensajes.forEach((mensaje) => pintarAviso(mensaje));

        botonIniciar.disabled = !datos.puede_iniciar || enviando;
    }

    function limpiarResultado(mensaje) {
        avisos.replaceChildren();
        panelRecursos.hidden = true;
        sinCalculo.hidden = Boolean(mensaje);
        botonIniciar.disabled = true;

        if (mensaje) {
            pintarAviso(mensaje);
        }
    }

    function programarCalculo() {
        clearTimeout(temporizador);
        botonIniciar.disabled = true;
        temporizador = setTimeout(actualizar, 300);
    }

    async function actualizar() {
        const solicitud = ++secuencia;
        const completas = lineasCompletas();
        botonIniciar.disabled = true;

        if (completas.length === 0) {
            calculando.hidden = true;
            limpiarResultado();
            return;
        }

        calculando.hidden = false;

        try {
            const respuesta = await fetch(urlRequerimientos, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ productos: completas.map((l) => ({ producto_id: Number(l.producto_id), cantidad: Number(l.cantidad) })) }),
            });

            if (solicitud !== secuencia) {
                return;
            }

            const cuerpo = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                limpiarResultado(cuerpo.message ?? 'No se pudieron calcular los insumos. Intenta de nuevo.');
                return;
            }

            pintarResultado(cuerpo.data);
        } catch {
            if (solicitud === secuencia) {
                limpiarResultado('No se pudo conectar con el servidor para calcular los insumos.');
            }
        } finally {
            if (solicitud === secuencia) {
                calculando.hidden = true;
            }
        }
    }

    botonAgregarLinea.addEventListener('click', () => {
        lineas.push({ producto_id: '', cantidad: '' });
        pintarLineas();
        contenedorLineas.querySelector(`#produccion-producto-${lineas.length - 1}`)?.focus();
    });

    formulario.addEventListener('submit', (evento) => {
        if (enviando || lineasCompletas().length === 0 || botonIniciar.disabled) {
            evento.preventDefault();
            return;
        }

        // Evita dobles clics: el servidor igualmente valida todo de nuevo.
        enviando = true;
        botonIniciar.disabled = true;
        botonIniciar.textContent = 'Iniciando...';
    });

    pintarLineas();
    actualizar();

    // Se vuelve a abrir si el servidor rechazó el envío o se pidió abrirlo desde el enlace interno.
    if (raiz.dataset.abrir === '1' && modal) {
        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
