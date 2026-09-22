/**
 * Pago semanal a proveedores: selección de proveedores, miniatura del comprobante,
 * modal de vista previa de impresión e impresión. Abrir la vista previa NO marca
 * nada como pagado: el pago solo se envía cuando el administrador confirma, después
 * de imprimir (formulario [data-form-pagar]).
 *
 * Un proveedor PAGADO es histórico: su fila se ve y se puede abrir su comprobante en
 * la hoja lateral, pero nunca entra en la selección para volver a pagarse.
 */

const ANCHO_HOJA = 794;
const POR_PAGINA = 8;
const LOTE_PREVIEW = 40;
const MAX_MINIATURAS = 60;

const raiz = document.querySelector('[data-pago-semanal]');

if (raiz) {
    iniciar();
}

function iniciar() {
    const filas = [...raiz.querySelectorAll('[data-fila]')];
    const filaPorId = new Map(filas.map((fila) => [fila.dataset.id, fila]));
    const urlPreview = raiz.dataset.previewUrl;
    const modal = document.getElementById('modal-vista-previa');

    const botonImprimirYMarcar = raiz.querySelector('[data-abrir-vista-previa]');
    const kpiSeleccionados = raiz.querySelector('[data-kpi-seleccionados]');
    const marcarTodos = raiz.querySelector('[data-marcar-todos]');
    const buscador = raiz.querySelector('[data-buscar]');
    const espejosBusqueda = document.querySelectorAll('[data-buscar-espejo]');
    const resumenTabla = raiz.querySelector('[data-resumen-tabla]');
    const paginacion = raiz.querySelector('[data-paginacion]');
    const sinResultados = raiz.querySelector('[data-sin-resultados]');
    const mini = {
        vacio: raiz.querySelector('[data-mini-vacio]'),
        cargando: raiz.querySelector('[data-mini-cargando]'),
        hoja: raiz.querySelector('[data-mini-hoja]'),
    };

    /** ids seleccionados (solo pendientes) en el orden en que se marcaron */
    let seleccion = [];
    /** id del pago que muestra la hoja lateral: el último marcado o la fila pagada abierta */
    let vistaId = raiz.dataset.verId ?? null;
    let vistaMostrada = null;
    let texto = '';
    let pagina = 1;
    let tokenMini = 0;
    const cache = new Map();

    const normalizar = (valor) => valor.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const soles = (valor) => `S/ ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const coincidentes = () => filas.filter((fila) => texto === '' || fila.dataset.buscarTexto.includes(texto));

    /* ---------------------------------------------------------- tabla */

    function pintarTabla() {
        const lista = coincidentes();
        const total = lista.length;
        const paginas = Math.max(1, Math.ceil(total / POR_PAGINA));
        pagina = Math.min(pagina, paginas);
        const visibles = new Set(lista.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA));

        filas.forEach((fila) => {
            fila.hidden = !visibles.has(fila);
        });

        sinResultados.hidden = total > 0 || filas.length === 0;
        resumenTabla.textContent = total === 0 ? '' : `Mostrando ${visibles.size} de ${total} proveedores`;
        paginacion.replaceChildren();

        if (paginas <= 1) {
            return;
        }

        const boton = (contenido, destino, { activo = false, deshabilitado = false, etiqueta } = {}) => {
            const el = document.createElement('button');
            el.type = 'button';
            el.textContent = contenido;
            el.disabled = deshabilitado;
            el.setAttribute('aria-label', etiqueta ?? `Página ${contenido}`);
            el.className = `flex h-9 min-w-9 items-center justify-center rounded-lg border px-2 text-sm transition ${activo ? 'border-info/30 bg-info-soft font-semibold text-primary-dark' : 'border-text-muted/15 bg-surface text-text hover:bg-text-muted/10'} disabled:cursor-not-allowed disabled:opacity-40`;
            el.addEventListener('click', () => {
                pagina = destino;
                pintarTabla();
            });

            return el;
        };

        paginacion.append(boton('‹', pagina - 1, { deshabilitado: pagina === 1, etiqueta: 'Página anterior' }));
        for (let numero = 1; numero <= paginas; numero += 1) {
            paginacion.append(boton(String(numero), numero, { activo: numero === pagina }));
        }
        paginacion.append(boton('›', pagina + 1, { deshabilitado: pagina === paginas, etiqueta: 'Página siguiente' }));
    }

    /* ------------------------------------------------------ selección */

    function pintarSeleccion() {
        filas.forEach((fila) => {
            const marcada = seleccion.includes(fila.dataset.id);
            fila.querySelector('[data-marcar]').checked = marcada;
            fila.classList.toggle('bg-info-soft', marcada || fila.dataset.id === vistaId);
        });

        const candidatas = coincidentes().filter((fila) => fila.dataset.pendiente === '1');
        const marcadas = candidatas.filter((fila) => seleccion.includes(fila.dataset.id)).length;
        marcarTodos.checked = candidatas.length > 0 && marcadas === candidatas.length;
        marcarTodos.indeterminate = marcadas > 0 && marcadas < candidatas.length;
        marcarTodos.disabled = candidatas.length === 0;

        kpiSeleccionados.textContent = String(seleccion.length);

        if (botonImprimirYMarcar) {
            botonImprimirYMarcar.disabled = seleccion.length === 0;
        }

        mostrarMiniatura();
    }

    /** Un pago PAGADO nunca entra en la selección, aunque se intente marcar. */
    function alternar(id, marcar) {
        const fila = filaPorId.get(id);

        if (!fila || fila.dataset.pendiente !== '1') {
            return;
        }

        seleccion = seleccion.filter((actual) => actual !== id);

        if (marcar) {
            seleccion.push(id);
            vistaId = id;
        } else if (vistaId === id) {
            vistaId = seleccion[seleccion.length - 1] ?? null;
        }
    }

    /** Selecciona solo los pendientes que cumplen los filtros activos (incluida la búsqueda). */
    function seleccionarPendientes() {
        coincidentes()
            .filter((fila) => fila.dataset.pendiente === '1')
            .forEach((fila) => alternar(fila.dataset.id, true));
        pintarSeleccion();
    }

    const limpiar = () => {
        seleccion = [];

        if (filaPorId.get(vistaId)?.dataset.pendiente === '1') {
            vistaId = null;
        }

        pintarSeleccion();
    };

    /** Abre la hoja lateral de cualquier fila (pendiente o pagada) sin tocar la selección. */
    function verFila(id) {
        vistaId = id;
        pintarSeleccion();
    }

    /* ------------------------------------------------ hojas (comprobantes) */

    async function cargarHojas(ids) {
        const faltan = ids.filter((id) => !cache.has(id));

        for (let i = 0; i < faltan.length; i += LOTE_PREVIEW) {
            const parametros = new URLSearchParams();
            faltan.slice(i, i + LOTE_PREVIEW).forEach((id) => parametros.append('ids[]', id));

            const respuesta = await fetch(`${urlPreview}?${parametros}`, { headers: { Accept: 'application/json' } });

            if (!respuesta.ok) {
                throw new Error('No se pudo cargar el comprobante.');
            }

            (await respuesta.json()).hojas.forEach((hoja) => cache.set(String(hoja.id), hoja));
        }

        return ids.map((id) => cache.get(id)).filter(Boolean);
    }

    /** Ajusta el contenedor .pp-escala al ancho disponible (o al ancho pedido). */
    function escalar(contenedor, ancho) {
        const hoja = contenedor.querySelector('.pp-hoja');
        const escala = ancho / ANCHO_HOJA;

        contenedor.style.setProperty('--pp-escala', String(escala));
        contenedor.style.height = `${(hoja?.offsetHeight ?? 1123) * escala}px`;
    }

    function montarHoja(contenedor, html, ancho) {
        contenedor.innerHTML = `<div class="pp-escala">${html}</div>`;
        escalar(contenedor.firstElementChild, ancho);
    }

    /** La hoja de la derecha se ajusta al ancho de la tarjeta y a la altura de la ventana. */
    function anchoMiniatura() {
        const disponible = mini.hoja.parentElement.clientWidth || 380;
        const porAltura = ((window.innerHeight - 270) * ANCHO_HOJA) / 1123;

        return Math.round(Math.max(240, Math.min(disponible, porAltura)));
    }

    async function mostrarMiniatura() {
        const id = vistaId ?? undefined;

        if (id === vistaMostrada || (id === undefined && vistaMostrada === null)) {
            return;
        }

        vistaMostrada = id ?? null;
        const token = ++tokenMini;

        if (id === undefined) {
            mini.vacio.hidden = false;
            mini.cargando.hidden = true;
            mini.hoja.hidden = true;

            return;
        }

        mini.vacio.hidden = true;
        mini.hoja.hidden = true;
        mini.cargando.hidden = false;

        try {
            const [hoja] = await cargarHojas([id]);

            if (token !== tokenMini || !hoja) {
                return;
            }

            mini.cargando.hidden = true;
            mini.hoja.hidden = false;
            mini.hoja.style.width = `${anchoMiniatura()}px`;
            montarHoja(mini.hoja, hoja.html, anchoMiniatura());
        } catch {
            if (token === tokenMini) {
                vistaMostrada = null;
                mini.cargando.textContent = 'No se pudo cargar el comprobante. Vuelve a hacer clic en el proveedor.';
            }
        }
    }

    /* ------------------------------------------------------------ modal */

    const vp = {
        lista: modal.querySelector('[data-lista-paginas]'),
        totalPaginas: modal.querySelector('[data-total-paginas]'),
        nombre: modal.querySelector('[data-nombre-pagina]'),
        contador: modal.querySelector('[data-contador]'),
        area: modal.querySelector('[data-area-hoja]'),
        grande: modal.querySelector('[data-hoja-grande]'),
        cargando: modal.querySelector('[data-cargando]'),
        anterior: modal.querySelector('[data-pagina-anterior]'),
        siguiente: modal.querySelector('[data-pagina-siguiente]'),
        zoomValor: modal.querySelector('[data-zoom-valor]'),
        imprimir: modal.querySelector('[data-imprimir]'),
        formulario: modal.querySelector('[data-form-pagar]'),
        campos: modal.querySelector('[data-campos-pago]'),
    };
    let hojas = [];
    let actual = 0;
    let zoom = 1;

    function anchoBase() {
        return Math.min(ANCHO_HOJA, Math.max(280, vp.area.clientWidth - 48));
    }

    function pintarPagina() {
        const hoja = hojas[actual];

        if (!hoja) {
            return;
        }

        vp.nombre.textContent = `${hoja.proveedor} · ${hoja.codigo}`;
        vp.contador.textContent = `${actual + 1} / ${hojas.length}`;
        vp.anterior.disabled = actual === 0;
        vp.siguiente.disabled = actual === hojas.length - 1;
        vp.zoomValor.textContent = `${Math.round(zoom * 100)}%`;

        vp.grande.hidden = false;
        vp.grande.style.width = `${anchoBase() * zoom}px`;
        montarHoja(vp.grande, hoja.html, anchoBase() * zoom);

        vp.lista.querySelectorAll('.pp-pagina-lista').forEach((item, indice) => {
            item.classList.toggle('is-activa', indice === actual);
            item.setAttribute('aria-current', indice === actual ? 'page' : 'false');
        });
    }

    function pintarLista() {
        vp.totalPaginas.textContent = String(hojas.length);
        vp.lista.replaceChildren();

        hojas.forEach((hoja, indice) => {
            const item = document.createElement('li');
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'pp-pagina-lista';

            const miniatura = document.createElement('div');
            miniatura.className = 'pp-miniatura';

            if (indice < MAX_MINIATURAS) {
                montarHoja(miniatura, hoja.html, 62);
            } else {
                miniatura.className = 'flex h-20 w-[62px] items-center justify-center rounded-md bg-primary-soft text-primary-dark';
                miniatura.textContent = String(indice + 1);
            }

            const datos = document.createElement('div');
            datos.className = 'min-w-0';
            datos.innerHTML = '<strong class="block truncate text-[13px] font-semibold text-[#12324a]"></strong><span class="block text-xs text-text-muted"></span><span class="mt-0.5 block text-xs text-text-muted"></span>';
            const [nombre, codigo, numero] = datos.children;
            nombre.textContent = hoja.proveedor;
            codigo.textContent = hoja.codigo;
            numero.textContent = `Página ${indice + 1}`;

            boton.append(miniatura, datos);
            boton.addEventListener('click', () => {
                actual = indice;
                pintarPagina();
            });
            item.append(boton);
            vp.lista.append(item);
        });
    }

    async function abrirVistaPrevia() {
        if (seleccion.length === 0) {
            return;
        }

        // Orden de impresión = orden de la tabla (alfabético por proveedor).
        const ids = filas.map((fila) => fila.dataset.id).filter((id) => seleccion.includes(id));

        vp.formulario.hidden = true;
        vp.imprimir.disabled = true;
        vp.grande.hidden = true;
        vp.cargando.hidden = false;
        vp.cargando.textContent = 'Preparando comprobantes…';
        vp.lista.replaceChildren();
        vp.contador.textContent = `0 / ${ids.length}`;
        vp.nombre.textContent = '—';
        zoom = 1;
        actual = 0;

        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');

        try {
            hojas = await cargarHojas(ids);
        } catch {
            vp.cargando.textContent = 'No se pudieron cargar los comprobantes. Cierra esta ventana e inténtalo de nuevo.';

            return;
        }

        if (hojas.length === 0) {
            vp.cargando.textContent = 'No hay comprobantes para imprimir.';

            return;
        }

        vp.cargando.hidden = true;
        vp.imprimir.disabled = false;
        pintarLista();
        pintarPagina();
    }

    function cambiarZoom(paso) {
        zoom = Math.min(1.6, Math.max(0.5, Math.round((zoom + paso) * 10) / 10));
        pintarPagina();
    }

    /* --------------------------------------------------------- impresión */

    async function imprimir() {
        vp.imprimir.disabled = true;

        const marco = document.createElement('iframe');
        marco.setAttribute('aria-hidden', 'true');
        marco.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        document.body.append(marco);

        const doc = marco.contentDocument;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Pago semanal a proveedores</title></head><body style="margin:0;background:#fff"></body></html>');
        doc.close();

        const estilos = [...document.querySelectorAll('link[rel="stylesheet"], style')].map((nodo) => {
            const copia = nodo.cloneNode(true);
            const cargada = nodo.tagName === 'LINK'
                ? new Promise((resolver) => { copia.onload = resolver; copia.onerror = resolver; })
                : Promise.resolve();
            doc.head.append(copia);

            return cargada;
        });
        await Promise.all(estilos);

        doc.body.innerHTML = hojas.map((hoja) => hoja.html).join('');
        await Promise.all([...doc.images].map((imagen) => (imagen.complete ? null : new Promise((resolver) => { imagen.onload = resolver; imagen.onerror = resolver; }))));
        await doc.fonts?.ready;

        let confirmado = false;
        const alTerminar = () => {
            if (confirmado) {
                return;
            }
            confirmado = true;
            marco.remove();
            pedirConfirmacion();
        };

        marco.contentWindow.addEventListener('afterprint', alTerminar);
        marco.contentWindow.focus();
        marco.contentWindow.print();
        // print() bloquea mientras el diálogo está abierto; en navegadores que no bloquean, afterprint avisa.
        window.setTimeout(alTerminar, 1500);
    }

    function pedirConfirmacion() {
        const total = hojas.reduce((suma, hoja) => suma + Number(hoja.total), 0);

        vp.formulario.querySelector('[data-conf-cantidad]').textContent = hojas.length === 1 ? '1 proveedor' : `${hojas.length} proveedores`;
        vp.formulario.querySelector('[data-conf-total]').textContent = soles(total);
        vp.formulario.querySelector('[data-conf-confirmar]').disabled = false;
        vp.formulario.hidden = false;
        vp.imprimir.disabled = false;
    }

    function prepararEnvio(evento) {
        vp.campos.replaceChildren();
        vp.formulario.querySelector('[data-ver-hoja]').value = hojas.some((hoja) => String(hoja.id) === vistaId) ? vistaId : '';

        hojas.forEach((hoja) => {
            [['ids[]', hoja.id], [`montos[${hoja.id}]`, Number(hoja.total).toFixed(2)]].forEach(([nombre, valor]) => {
                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = nombre;
                campo.value = valor;
                vp.campos.append(campo);
            });
        });

        const boton = vp.formulario.querySelector('[data-conf-confirmar]');

        if (boton.disabled) {
            evento.preventDefault();

            return;
        }

        boton.disabled = true;
    }

    /* ----------------------------------------------------------- eventos */

    filas.forEach((fila) => {
        fila.addEventListener('click', (evento) => {
            if (evento.target.closest('input, a, button')) {
                return;
            }

            if (fila.dataset.pendiente !== '1') {
                verFila(fila.dataset.id);

                return;
            }

            alternar(fila.dataset.id, !seleccion.includes(fila.dataset.id));
            pintarSeleccion();
        });

        fila.querySelector('[data-marcar]').addEventListener('change', (evento) => {
            alternar(fila.dataset.id, evento.target.checked);
            pintarSeleccion();
        });
    });

    marcarTodos.addEventListener('change', () => {
        const ids = coincidentes().filter((fila) => fila.dataset.pendiente === '1').map((fila) => fila.dataset.id);
        ids.forEach((id) => alternar(id, marcarTodos.checked));
        pintarSeleccion();
    });

    raiz.querySelectorAll('[data-seleccionar-pendientes]').forEach((boton) => {
        boton.addEventListener('click', seleccionarPendientes);
    });

    raiz.querySelectorAll('[data-limpiar]').forEach((boton) => boton.addEventListener('click', limpiar));

    raiz.querySelector('[data-filtros]').querySelectorAll('select').forEach((campo) => {
        campo.addEventListener('change', () => campo.form.submit());
    });

    function aplicarBusqueda() {
        texto = normalizar(buscador.value);
        pagina = 1;
        pintarTabla();
        pintarSeleccion();
    }

    // La búsqueda viaja en la URL y en los formularios para conservarse al recargar, filtrar o pagar.
    buscador.addEventListener('input', () => {
        const valor = buscador.value.trim();
        const url = new URL(window.location.href);

        espejosBusqueda.forEach((campo) => { campo.value = valor; });

        if (valor === '') {
            url.searchParams.delete('buscar');
        } else {
            url.searchParams.set('buscar', valor);
        }

        window.history.replaceState(null, '', url);
        aplicarBusqueda();
    });

    botonImprimirYMarcar?.addEventListener('click', abrirVistaPrevia);
    vp.imprimir.addEventListener('click', imprimir);
    vp.anterior.addEventListener('click', () => { actual = Math.max(0, actual - 1); pintarPagina(); });
    vp.siguiente.addEventListener('click', () => { actual = Math.min(hojas.length - 1, actual + 1); pintarPagina(); });
    modal.querySelector('[data-zoom-menos]').addEventListener('click', () => cambiarZoom(-0.1));
    modal.querySelector('[data-zoom-mas]').addEventListener('click', () => cambiarZoom(0.1));
    modal.querySelector('[data-conf-volver]').addEventListener('click', () => { vp.formulario.hidden = true; });
    vp.formulario.addEventListener('submit', prepararEnvio);

    window.addEventListener('resize', () => {
        if (!mini.hoja.hidden) {
            mini.hoja.style.width = `${anchoMiniatura()}px`;
            escalar(mini.hoja.firstElementChild, anchoMiniatura());
        }
        if (modal.classList.contains('is-open') && hojas.length > 0) {
            pintarPagina();
        }
    });

    aplicarBusqueda();
}
