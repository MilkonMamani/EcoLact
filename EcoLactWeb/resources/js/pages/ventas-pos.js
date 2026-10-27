/**
 * Ventas / POS (admin/ventas y ventas/ventas). Todo se registra por un POST normal; este script
 * solo PREVISUALIZA y guía:
 *
 * - Pestañas Nueva venta / Historial sin recargar (la URL guarda ?tab=historial).
 * - Nueva venta: misma regla que TarifasProducto (proveedor → PROVEEDOR; cantidad ≥ mínimo
 *   mayorista → MAYORISTA; si no → PUBLICO), total, stock después, saldo semanal del proveedor y
 *   el resumen de la derecha. "Confirmar venta" se deshabilita si algo no cuadra; el backend
 *   (RegistrarVentaProducto) vuelve a calcularlo y validarlo todo.
 * - Buscadores de comprador / proveedor y "+ Nuevo comprador" (fetch: queda seleccionado sin
 *   perder producto ni cantidad).
 * - Historial: filtros sin botón y detalle en panel lateral (fetch a la ruta show).
 */
const pagina = document.querySelector('[data-ventas-pos]');
const soles = (valor) => `S/ ${Number(valor).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const numero = (valor) => String(Math.round(valor * 100) / 100);
const unidades = (valor) => `${numero(valor)} ${Number(valor) === 1 ? 'unidad' : 'unidades'}`;
const normalizar = (texto) => (texto ?? '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

// ------------------------------------------------ pestañas

const tabs = pagina.querySelectorAll('[data-vpos-tab]');
const paneles = pagina.querySelectorAll('[data-vpos-panel]');

function mostrarTab(nombre) {
    tabs.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.vposTab === nombre)));
    paneles.forEach((panel) => { panel.hidden = panel.dataset.vposPanel !== nombre; });
}

tabs.forEach((tab) => {
    tab.addEventListener('click', (evento) => {
        if (evento.ctrlKey || evento.metaKey || evento.shiftKey) {
            return;
        }

        evento.preventDefault();
        mostrarTab(tab.dataset.vposTab);

        const url = new URL(window.location.href);
        if (tab.dataset.vposTab === 'historial') {
            url.searchParams.set('tab', 'historial');
        } else {
            url.search = '';
        }
        window.history.replaceState(window.history.state, '', url);
    });
});

// ------------------------------------------------ buscadores (comprador / proveedor)

function crearBuscador(raiz, alCambiar) {
    const valor = raiz.querySelector('[data-vpos-buscador-valor]');
    const ficha = raiz.querySelector('[data-vpos-buscador-ficha]');
    const caja = raiz.querySelector('[data-vpos-buscador-caja]');
    const texto = raiz.querySelector('[data-vpos-buscador-texto]');
    const lista = raiz.querySelector('[data-vpos-buscador-lista]');
    const opciones = JSON.parse(raiz.querySelector('[data-vpos-buscador-opciones]').textContent);
    let resultados = [];
    let activo = -1;

    function cerrarLista() {
        lista.hidden = true;
        texto.setAttribute('aria-expanded', 'false');
        activo = -1;
    }

    function pintarLista() {
        const buscado = normalizar(texto.value.trim());
        resultados = opciones
            .filter((op) => buscado === '' || normalizar(op.nombre).includes(buscado) || normalizar(op.documento).includes(buscado))
            .slice(0, 8);
        lista.replaceChildren();

        if (resultados.length === 0) {
            const vacio = document.createElement('li');
            vacio.className = 'is-vacio';
            vacio.textContent = raiz.dataset.vacio;
            lista.append(vacio);
        }

        resultados.forEach((op, indice) => {
            const item = document.createElement('li');
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(indice === activo));
            item.dataset.indice = indice;
            item.innerHTML = '<span class="vpos-lista-nombre"></span><span class="vpos-lista-doc"></span>';
            item.querySelector('.vpos-lista-nombre').textContent = op.nombre;
            item.querySelector('.vpos-lista-doc').textContent = op.documento ?? '';
            lista.append(item);
        });

        lista.hidden = false;
        texto.setAttribute('aria-expanded', 'true');
    }

    function elegir(op) {
        valor.value = op ? op.id : '';
        ficha.hidden = ! op;
        caja.hidden = !! op;
        raiz.querySelector('[data-vpos-buscador-nombre]').textContent = op?.nombre ?? '';
        raiz.querySelector('[data-vpos-buscador-doc]').textContent = op?.documento ?? '';
        texto.value = '';
        cerrarLista();
        alCambiar();
    }

    texto.addEventListener('focus', pintarLista);
    texto.addEventListener('input', () => { activo = -1; pintarLista(); });
    texto.addEventListener('keydown', (evento) => {
        if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
            evento.preventDefault();
            activo = Math.max(0, Math.min(resultados.length - 1, activo + (evento.key === 'ArrowDown' ? 1 : -1)));
            pintarLista();
        } else if (evento.key === 'Enter') {
            evento.preventDefault();
            if (resultados[activo] ?? (resultados.length === 1 ? resultados[0] : null)) {
                elegir(resultados[activo] ?? resultados[0]);
            }
        } else if (evento.key === 'Escape') {
            cerrarLista();
        }
    });

    // mousedown (no click): elige antes de que el blur cierre la lista.
    lista.addEventListener('mousedown', (evento) => {
        const item = evento.target.closest('[data-indice]');
        if (item) {
            evento.preventDefault();
            elegir(resultados[Number(item.dataset.indice)]);
        }
    });
    texto.addEventListener('blur', () => setTimeout(cerrarLista, 120));

    raiz.querySelector('[data-vpos-buscador-cambiar]').addEventListener('click', () => {
        elegir(null);
        texto.focus();
    });

    return {
        valor: () => valor.value,
        nombre: () => raiz.querySelector('[data-vpos-buscador-nombre]').textContent,
        agregar(op) {
            opciones.push(op);
            opciones.sort((a, b) => a.nombre.localeCompare(b.nombre));
            elegir(op);
        },
    };
}

// ------------------------------------------------ nueva venta

const form = pagina.querySelector('[data-vpos-form]');

if (form) {
    const catalogo = JSON.parse(pagina.querySelector('[data-vpos-catalogo]').textContent);
    const $ = (selector) => form.querySelector(selector);
    const campoProducto = $('[data-vpos-producto]');
    const campoCantidad = $('[data-vpos-cantidad]');
    const confirmar = $('[data-vpos-confirmar]');
    const resumen = $('[data-vpos-resumen]');
    const r = (clave) => $(`[data-vpos-r="${clave}"]`);
    const saldos = new Map();
    let enviando = false;

    const buscadores = {};
    form.querySelectorAll('[data-vpos-buscador]').forEach((raiz) => {
        buscadores[raiz.dataset.vposBuscador] = crearBuscador(raiz, () => actualizar());
    });

    const cliente = () => form.querySelector('[data-vpos-cliente]:checked')?.value ?? 'PUBLICO';
    const formaPago = () => (cliente() === 'PROVEEDOR' ? form.querySelector('[data-vpos-pago]:checked')?.value : 'CONTADO');
    const producto = () => catalogo.find((p) => String(p.id) === campoProducto.value) ?? null;
    const cantidad = () => {
        const valor = Number.parseFloat(campoCantidad.value);
        return Number.isFinite(valor) && valor > 0 ? valor : 0;
    };

    /** Misma regla que App\Domain\Ventas\TarifasProducto::aplicable(). */
    function precioAplicable(prod, esProveedor, cant) {
        if (esProveedor) {
            return prod.proveedor !== null ? { tipo: 'PROVEEDOR', precio: prod.proveedor } : null;
        }

        if (prod.mayorista !== null && prod.minimoMayorista !== null && cant >= prod.minimoMayorista) {
            return { tipo: 'MAYORISTA', precio: prod.mayorista };
        }

        return prod.publico !== null ? { tipo: 'PUBLICO', precio: prod.publico } : null;
    }

    function sinPrecio(prod, esProveedor) {
        if (esProveedor) {
            return 'Este producto no tiene precio de proveedor.';
        }

        return prod.minimoMayorista !== null
            ? `Este producto no tiene precio público: se vende desde ${unidades(prod.minimoMayorista)} (precio mayorista).`
            : 'Este producto no tiene precio para este comprador.';
    }

    async function cargarSaldo(proveedorId) {
        if (saldos.has(proveedorId)) {
            return;
        }

        saldos.set(proveedorId, { cargando: true });
        try {
            const respuesta = await fetch(pagina.dataset.urlSaldo.replace('__ID__', proveedorId), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const datos = await respuesta.json();
            saldos.set(proveedorId, respuesta.ok ? datos : { saldo: null, mensaje: 'No se pudo consultar el saldo del proveedor.' });
        } catch {
            saldos.set(proveedorId, { saldo: null, mensaje: 'No se pudo consultar el saldo del proveedor.' });
        }
        actualizar();
    }

    function pintarPrecio(prod, esProveedor, cant, aplicado) {
        const caja = $('[data-vpos-precio]');
        caja.classList.toggle('is-mayorista', aplicado?.tipo === 'MAYORISTA');
        caja.hidden = ! prod || cant <= 0 || ! aplicado;

        if (caja.hidden) {
            return;
        }

        const etiquetas = { PROVEEDOR: 'Precio proveedor', PUBLICO: 'Precio público', MAYORISTA: '✓ Precio mayorista aplicado' };
        let nota = '';

        if (aplicado.tipo === 'MAYORISTA') {
            nota = `Desde ${unidades(prod.minimoMayorista)}.`;
        } else if (! esProveedor && prod.minimoMayorista !== null && prod.mayorista !== null) {
            const faltan = prod.minimoMayorista - cant;
            nota = `ⓘ Precio mayorista desde ${unidades(prod.minimoMayorista)}. Faltan ${unidades(faltan)} para aplicarlo.`;
        }

        caja.innerHTML = '<div class="vpos-precio-linea"><span class="vpos-precio-tipo"></span><span class="vpos-precio-monto"></span></div><p class="vpos-precio-nota"></p>';
        caja.querySelector('.vpos-precio-tipo').textContent = etiquetas[aplicado.tipo];
        caja.querySelector('.vpos-precio-monto').textContent = `${soles(aplicado.precio)} c/u`;
        caja.querySelector('.vpos-precio-nota').textContent = nota;
        caja.querySelector('.vpos-precio-nota').hidden = nota === '';
    }

    function actualizar() {
        const quien = cliente();
        const esProveedor = quien === 'PROVEEDOR';
        const prod = producto();
        const cant = cantidad();
        const pago = formaPago();
        const aplicado = prod ? precioAplicable(prod, esProveedor, cant) : null;
        const total = aplicado ? Math.round(cant * aplicado.precio * 100) / 100 : 0;
        let motivo = '';

        // Bloques según quién compra.
        form.querySelectorAll('[data-vpos-si-cliente]').forEach((bloque) => { bloque.hidden = bloque.dataset.vposSiCliente !== quien; });
        $('[data-vpos-pago-fijo]').hidden = esProveedor;
        if (! esProveedor) {
            form.querySelector('[data-vpos-pago][value="CONTADO"]').checked = true;
        }

        // Producto: stock y aviso de mayorista.
        const stock = $('[data-vpos-stock]');
        const hint = $('[data-vpos-hint]');
        stock.hidden = ! prod;
        hint.hidden = true;
        if (prod) {
            stock.innerHTML = 'Stock disponible: <strong></strong>';
            stock.querySelector('strong').textContent = unidades(prod.stock);

            if (esProveedor && prod.proveedor !== null) {
                hint.textContent = `ⓘ Precio proveedor ${soles(prod.proveedor)} c/u.`;
                hint.hidden = cant > 0;
            } else if (! esProveedor && prod.minimoMayorista !== null && prod.mayorista !== null) {
                hint.textContent = `ⓘ Precio mayorista desde ${unidades(prod.minimoMayorista)}.`;
                hint.hidden = cant > 0;
            }
        }

        pintarPrecio(prod, esProveedor, cant, aplicado);

        // Stock insuficiente.
        const sinStock = $('[data-vpos-sin-stock]');
        sinStock.hidden = ! (prod && cant > prod.stock);
        if (! sinStock.hidden) {
            sinStock.textContent = prod.stock > 0
                ? `⚠ Stock insuficiente. Solo hay ${unidades(prod.stock)} disponibles.`
                : '⚠ Stock insuficiente. No hay unidades disponibles de este producto.';
        }

        // Saldo semanal del proveedor (descuento).
        const proveedorId = buscadores.proveedor?.valor() ?? '';
        const bloqueSaldo = $('[data-vpos-saldo]');
        const errorSaldo = $('[data-vpos-saldo-error]');
        const conDescuento = esProveedor && pago === 'DESCUENTO_PLANILLA' && proveedorId !== '';
        const saldo = conDescuento ? saldos.get(proveedorId) : null;
        bloqueSaldo.hidden = ! (saldo && typeof saldo.saldo === 'number');
        errorSaldo.hidden = true;

        if (conDescuento && ! saldo) {
            cargarSaldo(proveedorId);
        }

        if (saldo && typeof saldo.saldo === 'number') {
            const despues = Math.round((saldo.saldo - total) * 100) / 100;
            $('[data-vpos-saldo-disponible]').textContent = soles(saldo.saldo);
            $('[data-vpos-saldo-compra]').textContent = soles(total);
            $('[data-vpos-saldo-despues]').textContent = soles(despues);
            $('[data-vpos-saldo-despues]').classList.toggle('is-negativo', despues < 0);
            if (despues < 0) {
                errorSaldo.textContent = 'La compra supera el saldo estimado del pago semanal. Cobra al contado o reduce la cantidad.';
                errorSaldo.hidden = false;
            }
        } else if (saldo && saldo.mensaje) {
            errorSaldo.textContent = saldo.mensaje;
            errorSaldo.hidden = false;
        }

        // Por qué no se puede confirmar todavía (el primero que falte).
        if (quien === 'COMPRADOR' && buscadores.comprador.valor() === '') {
            motivo = 'Selecciona el comprador registrado.';
        } else if (esProveedor && proveedorId === '') {
            motivo = 'Selecciona el proveedor.';
        } else if (! prod) {
            motivo = 'Selecciona un producto.';
        } else if (cant <= 0) {
            motivo = 'Ingresa la cantidad.';
        } else if (! aplicado) {
            motivo = sinPrecio(prod, esProveedor);
        } else if (cant > prod.stock) {
            motivo = 'Stock insuficiente.';
        } else if (conDescuento && (! saldo || saldo.cargando)) {
            motivo = 'Consultando el saldo del proveedor...';
        } else if (conDescuento && (typeof saldo.saldo !== 'number' || total > saldo.saldo)) {
            motivo = 'El saldo semanal del proveedor no alcanza.';
        }

        // Resumen.
        const conDatos = !! prod && cant > 0;
        $('[data-vpos-resumen-vacio]').hidden = conDatos;
        resumen.hidden = ! conDatos;
        if (conDatos) {
            const nombreCliente = { PUBLICO: 'Público general', COMPRADOR: buscadores.comprador?.nombre(), PROVEEDOR: buscadores.proveedor?.nombre() }[quien];
            const tipos = { PROVEEDOR: 'Proveedor', MAYORISTA: 'Mayorista', PUBLICO: 'Público' };
            const despues = prod.stock - cant;

            r('producto').textContent = prod.nombre;
            r('cliente').textContent = nombreCliente || '';
            r('cantidad').textContent = unidades(cant);
            r('tipo').textContent = aplicado ? tipos[aplicado.tipo] : 'Sin precio';
            r('tipo').classList.toggle('is-mayorista', aplicado?.tipo === 'MAYORISTA');
            r('tipo').classList.toggle('is-error', ! aplicado);
            r('unitario').textContent = aplicado ? soles(aplicado.precio) : '—';
            r('total').textContent = soles(total);
            r('stock').textContent = numero(prod.stock);
            r('stockDespues').textContent = despues >= 0 ? numero(despues) : 'Insuficiente';
            r('stockDespues').classList.toggle('is-error', despues < 0);
            r('pago').textContent = pago === 'DESCUENTO_PLANILLA' ? 'Descuento semanal' : 'Contado';
        }

        const motivoEl = $('[data-vpos-motivo]');
        motivoEl.textContent = motivo;
        motivoEl.hidden = motivo === '' || ! conDatos;
        confirmar.disabled = motivo !== '' || enviando;
    }

    form.addEventListener('change', actualizar);
    campoCantidad.addEventListener('input', actualizar);
    form.addEventListener('submit', (evento) => {
        actualizar();
        if (confirmar.disabled) {
            evento.preventDefault();
            return;
        }
        enviando = true;
        confirmar.disabled = true;
    });

    // ------------------------------------------------ + Nuevo comprador

    const formComprador = pagina.querySelector('[data-vpos-comprador-form]');
    const errorComprador = formComprador.querySelector('[data-vpos-comprador-error]');

    formComprador.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const guardar = formComprador.querySelector('[data-vpos-comprador-guardar]');
        errorComprador.hidden = true;

        if (formComprador.elements.nombre.value.trim() === '') {
            errorComprador.textContent = 'Ingresa el nombre o la razón social.';
            errorComprador.hidden = false;
            formComprador.elements.nombre.focus();
            return;
        }

        guardar.disabled = true;
        try {
            const respuesta = await fetch(pagina.dataset.urlComprador, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(formComprador),
            });
            const datos = await respuesta.json();

            if (! respuesta.ok) {
                errorComprador.textContent = Object.values(datos.errors ?? {})[0]?.[0] ?? datos.message ?? 'No se pudo guardar el comprador.';
                errorComprador.hidden = false;
                return;
            }

            form.querySelector('[data-vpos-cliente][value="COMPRADOR"]').checked = true;
            buscadores.comprador.agregar(datos.data);
            formComprador.reset();
            formComprador.querySelector('[data-modal-close]').click();
        } catch {
            errorComprador.textContent = 'No se pudo guardar el comprador. Inténtalo de nuevo.';
            errorComprador.hidden = false;
        } finally {
            guardar.disabled = false;
        }
    });

    actualizar();
}

pagina.querySelector('[data-vpos-ok-cerrar]')?.addEventListener('click', (evento) => {
    evento.target.closest('[data-vpos-ok]').remove();
});

// ------------------------------------------------ historial: filtros

const filtros = pagina.querySelector('[data-vpos-filtros]');

if (filtros) {
    let esperaBusqueda = null;

    filtros.querySelectorAll('select, input[type="date"]').forEach((campo) => {
        campo.addEventListener('change', () => {
            if (campo.matches('[data-vpos-periodo]') && campo.value === 'personalizado') {
                filtros.querySelector('[data-vpos-rango]').hidden = false;
                return;
            }
            filtros.requestSubmit();
        });
    });

    filtros.querySelector('input[name="q"]').addEventListener('input', () => {
        clearTimeout(esperaBusqueda);
        esperaBusqueda = setTimeout(() => filtros.requestSubmit(), 500);
    });

    filtros.addEventListener('submit', () => {
        const personalizado = filtros.elements.periodo.value === 'personalizado';
        filtros.querySelectorAll('input, select').forEach((campo) => {
            campo.disabled = campo.value === '' || (campo.type === 'date' && ! personalizado);
        });
    });
}

// ------------------------------------------------ detalle (panel lateral)

const panel = pagina.querySelector('[data-vpos-detalle]');
const velo = pagina.querySelector('[data-vpos-velo]');
const skeleton = pagina.querySelector('[data-vpos-detalle-skeleton]');
let solicitud = null;
let urlAntes = null;

function marcarFila(id) {
    pagina.querySelectorAll('[data-vpos-fila]').forEach((fila) => fila.classList.toggle('is-selected', fila.dataset.vposFila === String(id)));
}

function abrirPanel() {
    panel.classList.add('is-abierto');
    panel.removeAttribute('aria-hidden');
    velo.hidden = false;
    document.body.classList.add('caja-drawer-abierto');
}

function cerrarPanel() {
    solicitud?.abort();
    panel.classList.remove('is-abierto');
    panel.setAttribute('aria-hidden', 'true');
    velo.hidden = true;
    document.body.classList.remove('caja-drawer-abierto');
    marcarFila(null);

    // Cerrar desde la ruta show (enlace directo) vuelve al historial.
    const url = urlAntes ?? `${pagina.querySelector('[data-vpos-tab="historial"]').href}`;
    window.history.replaceState(window.history.state, '', url);
    urlAntes = null;
}

async function verVenta(id, href, abrirBoleta = false) {
    solicitud?.abort();
    solicitud = new AbortController();
    urlAntes ??= window.location.href;

    marcarFila(id);
    panel.replaceChildren(skeleton.content.cloneNode(true));
    abrirPanel();
    window.history.replaceState(window.history.state, '', href);

    try {
        const respuesta = await fetch(pagina.dataset.urlShow.replace('__ID__', id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            signal: solicitud.signal,
        });

        if (! respuesta.ok) {
            throw new Error(`HTTP ${respuesta.status}`);
        }

        panel.innerHTML = await respuesta.text();
        panel.querySelector('[data-vpos-cerrar]')?.focus();

        if (abrirBoleta) {
            panel.querySelector('[data-vpos-boleta]')?.setAttribute('open', '');
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            panel.innerHTML = '<div class="caja-detalle"><div class="caja-detalle-cabecera"><p class="caja-detalle-kicker">Detalle de la venta</p><button type="button" class="caja-cerrar" data-vpos-cerrar aria-label="Cerrar detalle">✕</button></div><p class="vpos-vacio">No se pudo cargar el detalle. Inténtalo de nuevo.</p></div>';
        }
    }
}

pagina.addEventListener('click', (evento) => {
    const ver = evento.target.closest('[data-vpos-ver]');
    const fila = evento.target.closest('[data-vpos-fila]');

    if ((ver || fila) && ! evento.ctrlKey && ! evento.metaKey && ! evento.shiftKey) {
        const id = ver?.dataset.vposVer ?? fila.dataset.vposFila;
        const href = ver?.href ?? fila.querySelector('[data-vpos-ver]').href;
        evento.preventDefault();
        verVenta(id, href, ver?.hasAttribute('data-vpos-ver-boleta') ?? false);

        return;
    }

    if (evento.target.closest('[data-vpos-cerrar]') || evento.target === velo) {
        cerrarPanel();
    }
});

document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && panel.classList.contains('is-abierto') && ! document.querySelector('[data-modal].is-open')) {
        cerrarPanel();
    }
});

if (panel.classList.contains('is-abierto')) {
    document.body.classList.add('caja-drawer-abierto');
}
