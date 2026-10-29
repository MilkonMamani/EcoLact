/*
 * Modal "Nueva producción" (planificación): la persona agrega uno o varios productos con su cantidad
 * y, cada vez que cambia algo, el servidor calcula los insumos TOTALES (receta x cantidad de cada
 * producto, convertidos a la unidad base y sumados), si alcanza el stock y la frase que resume el
 * resultado. Este archivo SOLO pinta lo que el servidor responde: no contiene reglas de negocio ni
 * cálculo de recetas. Previsualizar NUNCA descuenta inventario; el descuento ocurre únicamente al
 * enviar el formulario (un lote por producto), y el servidor vuelve a validar todo.
 */
const raiz = document.querySelector('[data-produccion-nueva]');
const formulario = raiz?.querySelector('[data-produccion-form]');

if (raiz && formulario) {
    const modal = raiz.closest('[data-modal]');
    const listaFilas = formulario.querySelector('[data-filas]');
    const plantillaFila = formulario.querySelector('template[data-fila-plantilla]');
    const botonAgregar = formulario.querySelector('[data-agregar-fila]');
    const resumenPlanificacion = raiz.querySelector('[data-resumen-planificacion]');
    const sinCalculo = raiz.querySelector('[data-sin-calculo]');
    const calculando = raiz.querySelector('[data-calculando]');
    const panelRecursos = raiz.querySelector('[data-recursos]');
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
    let siguienteIndice = listaFilas.querySelectorAll('[data-fila]').length;

    const crear = (tag, clases, texto) => {
        const el = document.createElement(tag);
        if (clases) el.className = clases;
        if (texto !== undefined) el.textContent = texto;
        return el;
    };

    const filas = () => [...listaFilas.querySelectorAll('[data-fila]')];
    const lineaDe = (fila) => ({
        producto_id: Number(fila.querySelector('[data-producto]').value),
        cantidad: Number(fila.querySelector('[data-cantidad]').value),
    });

    /* Líneas que se mandan a calcular: solo las filas con producto elegido. */
    const lineasElegidas = () => filas().map(lineaDe).filter((linea) => linea.producto_id > 0);

    /* Completo = al menos una línea y TODAS las filas con producto tienen cantidad válida (> 0). */
    const completo = () => {
        const lineas = lineasElegidas();
        return lineas.length > 0 && lineas.every((linea) => Number.isFinite(linea.cantidad) && linea.cantidad > 0);
    };

    /* ---------- Filas de productos ---------- */

    function pintarFila(fila) {
        const opcion = fila.querySelector('[data-producto]').selectedOptions[0];
        const unidad = opcion?.dataset.unidad ?? '';
        const imagen = opcion?.dataset.imagen ?? '';
        const celdaUnidad = fila.querySelector('[data-fila-unidad]');
        const miniatura = fila.querySelector('[data-fila-imagen]');

        celdaUnidad.textContent = unidad === '' ? '—' : unidad === 'unidad' ? 'unidades' : unidad;
        miniatura.hidden = imagen === '';
        if (imagen !== '') {
            miniatura.src = imagen;
        }

        // Cantidad cero, negativa o vacía con producto elegido: se marca la fila.
        const cantidad = fila.querySelector('[data-cantidad]');
        const invalida = Number(opcion?.value) > 0 && cantidad.value !== '' && !(Number(cantidad.value) > 0);
        cantidad.classList.toggle('border-danger', invalida);
        cantidad.setAttribute('aria-invalid', invalida ? 'true' : 'false');
    }

    /*
     * Un producto ya elegido en otra fila no se puede volver a elegir: cada producto es un lote, y
     * para fabricar más basta con subir su cantidad. (El servidor igual sumaría las repetidas.)
     */
    function sincronizarFilas() {
        const todas = filas();
        const elegidos = todas.map((fila) => fila.querySelector('[data-producto]').value).filter(Boolean);

        todas.forEach((fila) => {
            const selector = fila.querySelector('[data-producto]');
            [...selector.options].forEach((opcion) => {
                opcion.disabled = opcion.value !== '' && opcion.value !== selector.value && elegidos.includes(opcion.value);
            });
            fila.querySelector('[data-quitar-fila]').disabled = todas.length === 1;
            pintarFila(fila);
        });

        const totalProductos = plantillaFila.content.querySelectorAll('[data-producto] option[value]:not([value=""])').length;
        botonAgregar.disabled = todas.length >= totalProductos;
    }

    function agregarFila() {
        const indice = siguienteIndice++;
        const html = plantillaFila.innerHTML.replaceAll('__i__', String(indice));
        const contenedor = document.createElement('template');
        contenedor.innerHTML = html.trim();
        const fila = contenedor.content.firstElementChild;
        listaFilas.append(fila);
        sincronizarFilas();
        fila.querySelector('[data-producto]').focus();
    }

    function quitarFila(fila) {
        if (filas().length === 1) {
            return;
        }
        fila.remove();
        sincronizarFilas();
        programarCalculo();
    }

    /* ---------- Resultado del servidor ---------- */

    /*
     * "3 productos · 23 unidades planificadas". Solo se suman cantidades de la MISMA unidad; si hay
     * unidades distintas se listan por separado ("18 unidades y 2,5 kg") en vez de mezclarlas.
     */
    function pintarResumen(lineas) {
        if (lineas.length === 0) {
            resumenPlanificacion.textContent = '';
            return;
        }

        const porUnidad = new Map();
        lineas.forEach((linea) => porUnidad.set(linea.unidad, (porUnidad.get(linea.unidad) ?? 0) + Number(linea.cantidad)));
        const partes = [...porUnidad].map(([unidad, total]) => conUnidad(total, unidad));
        const ultima = partes.pop();
        const cantidades = partes.length > 0 ? `${partes.join(', ')} y ${ultima}` : ultima;
        const productos = `${lineas.length} ${lineas.length === 1 ? 'producto' : 'productos'}`;
        const sufijo = porUnidad.size === 1 && porUnidad.has('unidad') ? 'planificadas' : 'planificados';

        resumenPlanificacion.textContent = `${productos} · ${cantidades} ${sufijo}`;
    }

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

    /* Con varios productos, bajo cada insumo se ve cuánto aporta cada uno al total. */
    function desglose(insumo, lineas) {
        if (lineas.length < 2) {
            return null;
        }

        const partes = lineas
            .map((linea) => ({ linea, porcion: (linea.insumos ?? []).find((i) => i.insumoId === insumo.insumo_id) }))
            .filter(({ porcion }) => porcion)
            .map(({ linea, porcion }) => `${linea.producto} ${numero(porcion.necesario)} ${insumo.unidad}`);

        return partes.length > 0 ? crear('p', 'col-span-2 text-xs text-text-muted sm:col-span-12', partes.join(' · ')) : null;
    }

    function filaInsumo(insumo, lineas) {
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

        const detalle = desglose(insumo, lineas);
        if (detalle) {
            fila.append(detalle);
        }

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
        pintarResumen(datos.lineas);
        cuerpoRecursos.replaceChildren(...datos.insumos.map((insumo) => filaInsumo(insumo, datos.lineas)));

        if (datos.insumos.length === 0) {
            cuerpoRecursos.append(crear('li', 'px-4 py-3 text-sm text-text-muted', 'Las recetas no tienen insumos registrados.'));
        }

        panelRecursos.hidden = datos.lineas.length === 0;
        sinCalculo.hidden = true;

        // El servidor redacta cada motivo: qué producto no tiene receta y qué insumo falta y cuánto.
        pintarVeredicto(datos.puede_iniciar, datos.resumen, datos.puede_iniciar ? [] : datos.mensajes);

        botonIniciar.disabled = !datos.puede_iniciar || enviando;
    }

    function limpiarResultado(mensaje) {
        panelRecursos.hidden = true;
        sinCalculo.hidden = Boolean(mensaje);
        veredicto.hidden = true;
        botonIniciar.disabled = true;
        resumenPlanificacion.textContent = '';

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
            const conProducto = lineasElegidas().length > 0;
            limpiarResultado(conProducto ? 'Indica una cantidad mayor que cero para cada producto.' : undefined);
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
                body: JSON.stringify({ productos: lineasElegidas() }),
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

    /* ---------- Eventos ---------- */

    botonAgregar.addEventListener('click', agregarFila);

    listaFilas.addEventListener('change', (evento) => {
        if (evento.target.matches('[data-producto]')) {
            sincronizarFilas();
            programarCalculo();
        }
    });

    listaFilas.addEventListener('input', (evento) => {
        if (evento.target.matches('[data-cantidad]')) {
            pintarFila(evento.target.closest('[data-fila]'));
            programarCalculo();
        }
    });

    listaFilas.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' && evento.target.matches('[data-cantidad]')) {
            evento.preventDefault();
        }
    });

    listaFilas.addEventListener('click', (evento) => {
        const quitar = evento.target.closest('[data-quitar-fila]');
        if (quitar) {
            quitarFila(quitar.closest('[data-fila]'));
        }
    });

    formulario.addEventListener('submit', (evento) => {
        if (enviando || !completo() || botonIniciar.disabled) {
            evento.preventDefault();
            return;
        }

        // Las filas vacías (sin producto) no se envían.
        filas().forEach((fila) => {
            if (!(lineaDe(fila).producto_id > 0)) {
                fila.querySelectorAll('select, input').forEach((campo) => {
                    campo.disabled = true;
                });
            }
        });

        // Evita dobles clics: el servidor igualmente valida todo de nuevo.
        enviando = true;
        botonIniciar.disabled = true;
        botonIniciar.textContent = 'Iniciando...';
    });

    sincronizarFilas();
    actualizar();

    // Se vuelve a abrir si el servidor rechazó el envío o se pidió abrirlo desde el enlace interno.
    if (raiz.dataset.abrir === '1' && modal) {
        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    }
}
