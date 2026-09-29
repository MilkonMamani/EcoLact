/**
 * Proveedores → pestaña Compras → "Ver pagos". Abre un modal sobre la MISMA pantalla: el
 * historial (filtrado por semana y estado, paginado) y el comprobante de cada pago llegan por
 * fetch como JSON con HTML ya renderizado, así que la URL del navegador nunca cambia.
 *
 * - Los modales son únicos en la página; las URLs y el título salen del panel del proveedor
 *   seleccionado ([data-proveedor-pagos]), que admin-fichas.js reemplaza al cambiar de fila.
 *   Por eso el botón se escucha por delegación y el estado se reinicia al cambiar de proveedor.
 * - Historial: #modal-pagos-proveedor. Los filtros aplicados y la página se conservan
 *   mientras se siga en el mismo proveedor (también al volver del detalle).
 * - Detalle: #modal-pago-detalle, apilado encima del historial. Cerrarlo devuelve al
 *   historial exactamente como estaba.
 */

const ANCHO_HOJA = 794;

if (document.getElementById('modal-pagos-proveedor')) {
    iniciar();
}

function iniciar() {
    let urlHistorial = null;
    let urlDetalle = null;
    const modal = document.getElementById('modal-pagos-proveedor');
    const modalDetalle = document.getElementById('modal-pago-detalle');
    const formulario = modal.querySelector('[data-pagos-filtros]');
    const selectSemana = formulario.querySelector('[name="semana"]');
    const selectEstado = formulario.querySelector('[name="estado"]');
    const resultado = modal.querySelector('[data-pagos-resultado]');
    const detalleSub = modalDetalle.querySelector('[data-pago-detalle-sub]');
    const detalleCuerpo = modalDetalle.querySelector('[data-pago-detalle-cuerpo]');

    /** filtros ya aplicados a la tabla (no los que se están editando en los combos) */
    const aplicado = { semana: 'todos', estado: 'todos', pagina: 1 };
    let solicitud = 0;
    let solicitudDetalle = 0;

    const cabeceras = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    const abrir = (elemento) => {
        elemento.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
    };

    const mensaje = (texto, { reintentar = null } = {}) => {
        const caja = document.createElement('div');
        caja.className = 'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-text-muted/25 bg-background/60 px-6 py-12 text-center';
        const parrafo = document.createElement('p');
        parrafo.className = 'text-sm text-text-muted';
        parrafo.textContent = texto;
        caja.append(parrafo);

        if (reintentar) {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'rounded-lg border border-text-muted/25 bg-surface px-4 py-2 text-sm font-medium text-text transition hover:bg-text-muted/10';
            boton.textContent = 'Reintentar';
            boton.addEventListener('click', reintentar);
            caja.append(boton);
        }

        return caja;
    };

    const cargando = (texto) => {
        const caja = document.createElement('div');
        caja.className = 'flex items-center justify-center gap-3 px-6 py-12 text-sm text-text-muted';
        caja.innerHTML = '<span class="h-5 w-5 animate-spin rounded-full border-2 border-primary/30 border-t-primary" aria-hidden="true"></span>';
        caja.append(document.createTextNode(texto));

        return caja;
    };

    /* ------------------------------------------------------------ historial */

    function pintarSemanas(semanas, seleccionada) {
        const opciones = [{ valor: 'todos', etiqueta: 'Todos' }, ...semanas];

        selectSemana.replaceChildren(...opciones.map((semana) => {
            const opcion = document.createElement('option');
            opcion.value = semana.valor;
            opcion.textContent = semana.etiqueta;

            return opcion;
        }));

        selectSemana.value = opciones.some((semana) => semana.valor === seleccionada) ? seleccionada : 'todos';
    }

    async function cargar() {
        const token = ++solicitud;
        const parametros = new URLSearchParams({ semana: aplicado.semana, estado: aplicado.estado, page: String(aplicado.pagina) });

        if (resultado.childElementCount === 0) {
            resultado.replaceChildren(cargando('Cargando pagos…'));
        } else {
            resultado.classList.add('opacity-50', 'pointer-events-none');
        }

        try {
            const respuesta = await fetch(`${urlHistorial}?${parametros}`, { headers: cabeceras });

            if (!respuesta.ok) {
                throw new Error('respuesta no válida');
            }

            const datos = await respuesta.json();

            if (token !== solicitud) {
                return;
            }

            resultado.innerHTML = datos.html;
            pintarSemanas(datos.semanas, datos.semana);
            selectEstado.value = datos.estado;
            aplicado.semana = selectSemana.value;
            aplicado.estado = datos.estado;
            aplicado.pagina = datos.pagina;
        } catch {
            if (token === solicitud) {
                resultado.replaceChildren(mensaje('No se pudieron cargar los pagos. Inténtalo de nuevo.', { reintentar: cargar }));
            }
        } finally {
            if (token === solicitud) {
                resultado.classList.remove('opacity-50', 'pointer-events-none');
            }
        }
    }

    /* -------------------------------------------------------------- detalle */

    /** Ajusta la hoja A4 al ancho disponible del modal (sin ampliarla más allá de su tamaño real). */
    function montarHoja(html) {
        detalleCuerpo.innerHTML = `<div class="pp-escala">${html}</div>`;

        const contenedor = detalleCuerpo.firstElementChild;
        const hoja = contenedor.querySelector('.pp-hoja');
        const disponible = detalleCuerpo.clientWidth - 24;
        const escala = Math.min(1, Math.max(0.4, disponible / ANCHO_HOJA));

        contenedor.style.setProperty('--pp-escala', String(escala));
        contenedor.style.width = `${ANCHO_HOJA * escala}px`;
        contenedor.style.height = `${(hoja?.offsetHeight ?? 1123) * escala}px`;
        contenedor.style.margin = '0 auto';
    }

    async function verDetalle(id) {
        const token = ++solicitudDetalle;

        detalleSub.textContent = 'Comprobante del pago del proveedor.';
        detalleCuerpo.replaceChildren(cargando('Cargando comprobante…'));
        abrir(modalDetalle);

        try {
            const respuesta = await fetch(urlDetalle.replace('__ID__', encodeURIComponent(id)), { headers: cabeceras });

            if (!respuesta.ok) {
                throw new Error('respuesta no válida');
            }

            const datos = await respuesta.json();

            if (token !== solicitudDetalle) {
                return;
            }

            detalleSub.textContent = `${datos.semana} · ${datos.rango} · ${datos.estado === 'PAGADO' ? 'Pagado' : 'Pendiente'}`;
            montarHoja(datos.html);
        } catch {
            if (token === solicitudDetalle) {
                detalleCuerpo.replaceChildren(mensaje('No se pudo cargar el comprobante. Inténtalo de nuevo.', { reintentar: () => verDetalle(id) }));
            }
        }
    }

    /* -------------------------------------------------------------- eventos */

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-pagos-abrir]');
        const raiz = boton?.closest('[data-proveedor-pagos]');

        if (!raiz) {
            return;
        }

        // Otro proveedor: filtros, página y resultados vuelven a empezar.
        if (raiz.dataset.url !== urlHistorial) {
            urlHistorial = raiz.dataset.url;
            urlDetalle = raiz.dataset.urlDetalle;
            Object.assign(aplicado, { semana: 'todos', estado: 'todos', pagina: 1 });
            selectEstado.value = 'todos';
            resultado.replaceChildren();

            const titulo = modal.querySelector('h2');
            if (titulo && raiz.dataset.titulo) {
                titulo.textContent = raiz.dataset.titulo;
                modal.setAttribute('aria-label', raiz.dataset.titulo);
            }
        }

        abrir(modal);
        cargar();
    });

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        aplicado.semana = selectSemana.value;
        aplicado.estado = selectEstado.value;
        aplicado.pagina = 1;
        cargar();
    });

    resultado.addEventListener('click', (evento) => {
        const pagina = evento.target.closest('[data-pagina]');
        const detalle = evento.target.closest('[data-pago-detalle]');

        if (pagina && !pagina.disabled) {
            aplicado.pagina = Number(pagina.dataset.pagina);
            cargar();

            return;
        }

        if (detalle) {
            verDetalle(detalle.dataset.pagoDetalle);
        }
    });
}
