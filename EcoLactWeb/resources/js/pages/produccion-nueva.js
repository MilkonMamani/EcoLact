/*
 * Modal "Nueva producción": la persona elige producto + cantidad y el servidor calcula
 * los insumos (receta x cantidad, sumados entre productos, con la leche primero). Este
 * archivo SOLO pinta lo que el servidor responde: no contiene reglas de negocio ni cálculo
 * de recetas. Abrir el modal, agregar productos, cambiar cantidades y previsualizar NUNCA
 * descuentan inventario; el descuento ocurre únicamente al enviar el formulario.
 */
const raiz = document.querySelector('[data-produccion-nueva]');
const formulario = raiz?.querySelector('[data-produccion-form]');

if (raiz && formulario) {
    const modal = raiz.closest('[data-modal]');
    const selector = raiz.querySelector('[data-producto-select]');
    const cantidadInput = raiz.querySelector('[data-cantidad-input]');
    const botonAgregar = raiz.querySelector('[data-agregar]');
    const tablaLineas = raiz.querySelector('[data-tabla-lineas]');
    const cuerpoLineas = raiz.querySelector('[data-lineas]');
    const vacio = raiz.querySelector('[data-lineas-vacio]');
    const inputsOcultos = raiz.querySelector('[data-inputs-ocultos]');
    const avisos = raiz.querySelector('[data-avisos]');
    const calculando = raiz.querySelector('[data-calculando]');
    const panelRecursos = raiz.querySelector('[data-recursos]');
    const cuerpoRecursos = raiz.querySelector('[data-recursos-cuerpo]');
    const botonIniciar = raiz.querySelector('[data-iniciar]');
    const errorCantidad = raiz.querySelector('[data-error-cantidad]');

    const urlRequerimientos = raiz.dataset.urlRequerimientos;
    const productos = JSON.parse(raiz.dataset.productos || '[]');
    const token = formulario.querySelector('input[name="_token"]')?.value ?? '';

    const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });
    const productoPorId = (id) => productos.find((p) => String(p.id) === String(id));
    const icono = (nombre) => raiz.querySelector(`template[data-icono="${nombre}"]`)?.content.cloneNode(true);

    let lineas = JSON.parse(raiz.dataset.lineasIniciales || '[]').map((l) => ({
        producto_id: Number(l.producto_id),
        cantidad: Number(l.cantidad),
    }));
    let secuencia = 0;
    let enviando = false;

    const crear = (tag, clases, texto) => {
        const el = document.createElement(tag);
        if (clases) el.className = clases;
        if (texto !== undefined) el.textContent = texto;
        return el;
    };

    function miniatura(producto) {
        if (producto?.imagen) {
            const imagen = crear('img', 'h-10 w-10 shrink-0 rounded-lg border border-text-muted/10 object-cover');
            imagen.src = producto.imagen;
            imagen.alt = producto.nombre;
            imagen.loading = 'lazy';

            return imagen;
        }

        const vacia = crear('span', 'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-dashed border-text-muted/25 bg-primary-soft/40 text-xs text-text-muted', '—');
        vacia.setAttribute('role', 'img');
        vacia.setAttribute('aria-label', 'Sin imagen');

        return vacia;
    }

    function pintarLineas() {
        cuerpoLineas.replaceChildren();
        inputsOcultos.replaceChildren();
        vacio.hidden = lineas.length > 0;
        tablaLineas.hidden = lineas.length === 0;

        lineas.forEach((linea, indice) => {
            const producto = productoPorId(linea.producto_id);
            const fila = crear('tr', 'border-b border-text-muted/5 last:border-0');

            const celdaImagen = crear('td', 'py-2 pr-3');
            celdaImagen.append(miniatura(producto));

            const celdaProducto = crear('td', 'py-2 pr-3 font-medium text-text', producto?.nombre ?? 'Producto');

            const celdaCantidad = crear('td', 'py-2 pr-3');
            const cantidad = crear('input', 'w-24 rounded-lg border border-text-muted/25 bg-surface px-2 py-1.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary');
            cantidad.type = 'number';
            cantidad.step = 'any';
            cantidad.min = '0';
            cantidad.value = linea.cantidad;
            cantidad.setAttribute('aria-label', `Cantidad de ${producto?.nombre ?? 'producto'}`);
            cantidad.addEventListener('change', () => {
                const nueva = Number(cantidad.value);

                if (!(nueva > 0)) {
                    cantidad.value = linea.cantidad;
                    return;
                }

                linea.cantidad = nueva;
                pintarLineas();
                actualizar();
            });
            celdaCantidad.append(cantidad);

            const celdaAccion = crear('td', 'py-2 pl-3 text-right');
            const quitar = crear('button', 'inline-flex h-8 w-8 items-center justify-center rounded-lg bg-danger text-white transition hover:brightness-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary');
            quitar.type = 'button';
            quitar.title = `Quitar ${producto?.nombre ?? 'producto'}`;
            quitar.setAttribute('aria-label', `Quitar ${producto?.nombre ?? 'producto'}`);
            quitar.append(icono('trash') ?? document.createTextNode('✕'));
            quitar.addEventListener('click', () => {
                lineas.splice(indice, 1);
                pintarLineas();
                actualizar();
            });
            celdaAccion.append(quitar);

            fila.append(celdaImagen, celdaProducto, celdaCantidad, celdaAccion);
            cuerpoLineas.append(fila);

            [['producto_id', linea.producto_id], ['cantidad', linea.cantidad]].forEach(([campo, valor]) => {
                const oculto = document.createElement('input');
                oculto.type = 'hidden';
                oculto.name = `productos[${indice}][${campo}]`;
                oculto.value = valor;
                inputsOcultos.append(oculto);
            });
        });
    }

    function pintarAviso(clase, texto) {
        avisos.append(crear('div', `rounded-lg border px-3 py-2 text-sm text-text ${clase}`, texto));
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
        cuerpoRecursos.replaceChildren();

        datos.insumos.forEach((insumo) => {
            const fila = crear('tr', 'border-b border-text-muted/5 last:border-0');
            fila.append(crear('td', 'py-2 pr-3 font-medium text-text', insumo.nombre));
            fila.append(crear('td', 'py-2 pr-3 whitespace-nowrap text-text', `${numero(insumo.necesario)} ${insumo.unidad}`));
            fila.append(crear('td', 'py-2 pr-3 whitespace-nowrap text-text-muted', `${numero(insumo.disponible)} ${insumo.unidad}`));
            const celdaEstado = crear('td', 'py-2 pr-3');
            celdaEstado.append(badgeEstado(insumo.suficiente));
            fila.append(celdaEstado);
            cuerpoRecursos.append(fila);
        });

        panelRecursos.hidden = datos.insumos.length === 0;

        // Los mensajes vienen ya redactados por el servidor (misma redacción que el bloqueo real).
        datos.mensajes.forEach((mensaje) => pintarAviso('border-danger/30 bg-danger/10', mensaje));

        botonIniciar.disabled = !datos.puede_iniciar || enviando;
    }

    function limpiarResultado(mensaje) {
        avisos.replaceChildren();
        panelRecursos.hidden = true;
        botonIniciar.disabled = true;

        if (mensaje) {
            pintarAviso('border-danger/30 bg-danger/10', mensaje);
        }
    }

    async function actualizar() {
        const solicitud = ++secuencia;
        botonIniciar.disabled = true;

        if (lineas.length === 0) {
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
                body: JSON.stringify({ productos: lineas }),
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

    botonAgregar.addEventListener('click', () => {
        errorCantidad.textContent = '';
        const productoId = Number(selector.value);
        const cantidad = Number(cantidadInput.value);

        if (!productoId) {
            errorCantidad.textContent = 'Elige un producto.';
            return;
        }

        if (!(cantidad > 0)) {
            errorCantidad.textContent = 'Escribe una cantidad mayor que cero.';
            return;
        }

        if (lineas.some((l) => l.producto_id === productoId)) {
            errorCantidad.textContent = 'Este producto ya fue agregado. Cambia su cantidad en la tabla.';
            return;
        }

        lineas.push({ producto_id: productoId, cantidad });
        selector.value = '';
        cantidadInput.value = '';
        pintarLineas();
        actualizar();
    });

    cantidadInput.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            botonAgregar.click();
        }
    });

    formulario.addEventListener('submit', (evento) => {
        if (enviando || lineas.length === 0 || botonIniciar.disabled) {
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
