/*
 * Modal "Nueva producción": la persona elige producto + cantidad y, en cuanto ambos tienen valor,
 * el servidor calcula los insumos (receta x cantidad, con la leche primero), si alcanza el stock
 * y la frase que resume el resultado. Este archivo SOLO pinta lo que el servidor responde: no
 * contiene reglas de negocio ni cálculo de recetas. Previsualizar NUNCA descuenta inventario; el
 * descuento ocurre únicamente al enviar el formulario, y el servidor vuelve a validar todo.
 */
const raiz = document.querySelector('[data-produccion-nueva]');
const formulario = raiz?.querySelector('[data-produccion-form]');

if (raiz && formulario) {
    const modal = raiz.closest('[data-modal]');
    const selectorProducto = formulario.querySelector('[data-producto]');
    const campoCantidad = formulario.querySelector('[data-cantidad]');
    const sinCalculo = raiz.querySelector('[data-sin-calculo]');
    const calculando = raiz.querySelector('[data-calculando]');
    const panelRecursos = raiz.querySelector('[data-recursos]');
    const resumenProducto = raiz.querySelector('[data-resumen-producto]');
    const resumenCantidad = raiz.querySelector('[data-resumen-cantidad]');
    const resumenImagen = raiz.querySelector('[data-resumen-imagen]');
    const cuerpoRecursos = raiz.querySelector('[data-recursos-cuerpo]');
    const veredicto = raiz.querySelector('[data-veredicto]');
    const botonIniciar = raiz.querySelector('[data-iniciar]');

    const urlRequerimientos = raiz.dataset.urlRequerimientos;
    const token = formulario.querySelector('input[name="_token"]')?.value ?? '';

    const numero = (valor) => Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 4 });
    const conUnidad = (valor, unidad) => {
        if (unidad === 'unidad') {
            return `${numero(valor)} ${Number(valor) === 1 ? 'unidad' : 'unidades'}`;
        }

        return `${numero(valor)} ${unidad}`;
    };
    const icono = (nombre) => raiz.querySelector(`template[data-icono="${nombre}"]`)?.content.cloneNode(true) ?? document.createTextNode('');

    let secuencia = 0;
    let temporizador = null;
    let enviando = false;

    const crear = (tag, clases, texto) => {
        const el = document.createElement(tag);
        if (clases) el.className = clases;
        if (texto !== undefined) el.textContent = texto;
        return el;
    };

    const completo = () => Number(selectorProducto.value) > 0 && Number(campoCantidad.value) > 0;

    function estado(insumo) {
        const caja = crear('div', 'flex flex-col items-start gap-0.5 sm:items-end');
        const badge = crear(
            'span',
            `inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${insumo.suficiente ? 'bg-success-soft text-success ring-success/20' : 'bg-danger-soft text-danger ring-danger/20'}`,
        );
        badge.append(icono(insumo.suficiente ? 'check' : 'alerta'), document.createTextNode(insumo.suficiente ? 'Suficiente' : 'Insuficiente'));
        caja.append(badge);

        if (!insumo.suficiente) {
            caja.append(crear('span', 'text-xs font-medium text-danger', `Faltan ${numero(insumo.faltante)} ${insumo.unidad}`));
        }

        return caja;
    }

    function filaInsumo(insumo) {
        const fila = crear('li', 'grid grid-cols-2 gap-x-3 gap-y-1.5 px-4 py-3 text-sm sm:grid-cols-12 sm:items-center');

        const nombre = crear('div', 'col-span-2 flex min-w-0 items-center gap-2 sm:col-span-4');
        if (insumo.es_leche) {
            const gota = crear('span', 'text-info');
            gota.append(icono('leche'));
            nombre.append(gota);
        }
        nombre.append(crear('span', 'font-medium text-text', insumo.nombre));
        if (insumo.es_leche) {
            nombre.append(crear('span', 'text-xs text-text-muted', 'desde Acopio'));
        }

        // En móvil cada cifra lleva su rótulo; en escritorio lo da la cabecera de la tabla.
        const cifra = (rotulo, texto, clases, columnas) => {
            const celda = crear('p', `${columnas} sm:text-right`);
            celda.append(crear('span', 'text-xs text-text-muted sm:hidden', `${rotulo}: `), crear('span', clases, texto));
            return celda;
        };

        const estadoCelda = crear('div', 'col-span-2 sm:col-span-3');
        estadoCelda.append(estado(insumo));

        fila.append(
            nombre,
            cifra('Necesario', `${numero(insumo.necesario)} ${insumo.unidad}`, 'font-semibold text-text', 'sm:col-span-2'),
            cifra('Disponible', `${numero(insumo.disponible)} ${insumo.unidad}`, insumo.suficiente ? 'text-text' : 'font-semibold text-danger', 'sm:col-span-3'),
            estadoCelda,
        );

        return fila;
    }

    function pintarVeredicto(puede, texto, detalles = []) {
        veredicto.replaceChildren();
        const caja = crear(
            'div',
            `flex items-start gap-2 rounded-lg border px-4 py-3 text-sm ${puede ? 'border-success/25 bg-success-soft text-success' : 'border-danger/25 bg-danger-soft text-danger'}`,
        );
        const textos = crear('div', 'space-y-0.5');
        textos.append(crear('p', 'font-medium', texto));
        detalles.forEach((detalle) => textos.append(crear('p', 'text-text', detalle)));
        caja.append(icono(puede ? 'check' : 'alerta'), textos);
        veredicto.append(caja);
        veredicto.hidden = false;
    }

    function pintarResultado(datos) {
        const linea = datos.lineas[0];
        resumenProducto.textContent = linea ? linea.etiqueta ?? linea.producto : '';
        resumenCantidad.textContent = linea ? `${conUnidad(linea.cantidad, linea.unidad)} planificadas` : '';

        // La imagen es la misma que se subió en la receta (viene en la opción elegida).
        const imagen = selectorProducto.selectedOptions[0]?.dataset.imagen ?? '';
        resumenImagen.hidden = imagen === '';
        if (imagen !== '') {
            resumenImagen.src = imagen;
        }

        cuerpoRecursos.replaceChildren(...datos.insumos.map(filaInsumo));

        if (datos.insumos.length === 0) {
            cuerpoRecursos.append(crear('li', 'px-4 py-3 text-sm text-text-muted', 'La receta no tiene insumos registrados.'));
        }

        panelRecursos.hidden = datos.lineas.length === 0;
        sinCalculo.hidden = true;

        // Sin receta no hay tabla útil: el detalle del motivo lo redacta el servidor.
        const detalles = datos.productos_sin_receta.length > 0 ? datos.mensajes : [];
        pintarVeredicto(datos.puede_iniciar, datos.resumen, detalles);

        botonIniciar.disabled = !datos.puede_iniciar || enviando;
    }

    function limpiarResultado(mensaje) {
        panelRecursos.hidden = true;
        sinCalculo.hidden = Boolean(mensaje);
        veredicto.hidden = true;
        botonIniciar.disabled = true;

        if (mensaje) {
            pintarVeredicto(false, mensaje);
        }
    }

    function programarCalculo() {
        clearTimeout(temporizador);
        botonIniciar.disabled = true;
        temporizador = setTimeout(actualizar, 300);
    }

    async function actualizar() {
        const solicitud = ++secuencia;
        botonIniciar.disabled = true;

        if (!completo()) {
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
                body: JSON.stringify({ productos: [{ producto_id: Number(selectorProducto.value), cantidad: Number(campoCantidad.value) }] }),
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

    selectorProducto.addEventListener('change', programarCalculo);
    campoCantidad.addEventListener('input', programarCalculo);
    campoCantidad.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
        }
    });

    formulario.addEventListener('submit', (evento) => {
        if (enviando || !completo() || botonIniciar.disabled) {
            evento.preventDefault();
            return;
        }

        // Evita dobles clics: el servidor igualmente valida todo de nuevo.
        enviando = true;
        botonIniciar.disabled = true;
        botonIniciar.textContent = 'Iniciando...';
    });

    actualizar();

    // Se vuelve a abrir si el servidor rechazó el envío o se pidió abrirlo desde el enlace interno.
    if (raiz.dataset.abrir === '1' && modal) {
        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
