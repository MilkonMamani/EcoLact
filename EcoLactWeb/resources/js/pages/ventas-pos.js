/**
 * Ventas / POS (admin/ventas y ventas/ventas). Todo se registra por un POST normal; este script
 * solo PREVISUALIZA y guía:
 *
 * - Pestañas Nueva venta / Historial sin recargar (la URL guarda ?tab=historial).
 * - Nueva venta: misma regla que TarifasProducto (proveedor → PROVEEDOR; cantidad ≥ mínimo
 *   mayorista → MAYORISTA; si no → PUBLICO), total, stock después, saldo semanal del proveedor y
 *   el resumen de la derecha. "Confirmar venta" se deshabilita si algo no cuadra; el backend
 *   (RegistrarVentaProducto) vuelve a calcularlo y validarlo todo.
 * - El catálogo muestra solo la lista de precios de quien compra; un producto sin precio para
 *   ese comprador queda "Precio no configurado" y no se puede elegir.
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
        limpiar() {
            if (valor.value !== '') {
                elegir(null);
            }
        },
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
    const campoCantidad = $('[data-vpos-cantidad]');
    const confirmar = form.querySelectorAll('[data-vpos-confirmar]');
    const pagoDescuento = $('[data-vpos-pago][value="DESCUENTO_PLANILLA"]');
    const pagoContado = $('[data-vpos-pago][value="CONTADO"]');
    const r = (clave) => form.querySelector(`[data-vpos-r="${clave}"]`);
    const saldos = new Map();
    let enviando = false;
    let intentado = false;
    let avisoQuitado = '';
    let clienteAnterior = null;

    const buscadores = {};
    form.querySelectorAll('[data-vpos-buscador]').forEach((raiz) => {
        buscadores[raiz.dataset.vposBuscador] = crearBuscador(raiz, () => actualizar());
    });

    const cliente = () => form.querySelector('[data-vpos-cliente]:checked')?.value ?? 'PUBLICO';
    const producto = () => catalogo.find((p) => String(p.id) === form.querySelector('[data-vpos-producto]:checked')?.value) ?? null;
    const cantidadTexto = () => campoCantidad.value.trim();
    const cantidad = () => {
        const valor = Number.parseFloat(cantidadTexto());
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

    function mostrarError(campo, mensaje) {
        const el = form.querySelector(`[data-vpos-error="${campo}"]`);
        el.textContent = mensaje ?? '';
        el.hidden = ! mensaje;
    }

    /**
     * Lista de precios de quien compra, sin los de las otras: el proveedor solo ve PROVEEDOR; público
     * general y comprador registrado ven PUBLICO y el escalón MAYORISTA (lo decide la cantidad).
     */
    function tarifaDe(prod, esProveedor) {
        return esProveedor
            ? { base: prod.proveedor, mayorista: null, minimo: null }
            : { base: prod.publico, mayorista: prod.mayorista, minimo: prod.minimoMayorista };
    }

    const etiquetasTarifa = JSON.parse($('[data-vpos-tarifa]').dataset.textos);
    const destinatarios = { PUBLICO: 'el público general', COMPRADOR: 'compradores registrados', PROVEEDOR: 'proveedores' };

    /**
     * Tarjetas del catálogo con la tarifa de quien compra. Un producto sin precio para ese comprador
     * (o sin stock) no se puede elegir; si estaba elegido se quita y se devuelve para avisarlo.
     */
    function pintarCatalogo(quien, cant) {
        const esProveedor = quien === 'PROVEEDOR';
        let sinTarifa = 0;
        let quitado = null;

        $('[data-vpos-tarifa-texto]').textContent = etiquetasTarifa[quien];

        catalogo.forEach((prod) => {
            const input = form.querySelector(`[data-vpos-producto][value="${prod.id}"]`);
            const precio = form.querySelector(`[data-vpos-producto-precio="${prod.id}"]`);
            const nota = form.querySelector(`[data-vpos-producto-nota="${prod.id}"]`);
            const tarifa = tarifaDe(prod, esProveedor);
            const configurado = tarifa.base !== null || tarifa.mayorista !== null;
            const mayoristaAplica = tarifa.mayorista !== null && cant >= tarifa.minimo;

            nota.classList.toggle('is-aplicado', mayoristaAplica);
            if (! configurado) {
                sinTarifa += 1;
                precio.textContent = 'Precio no configurado';
                nota.textContent = '';
            } else if (tarifa.base !== null) {
                precio.textContent = `${soles(tarifa.base)} c/u`;
                nota.textContent = tarifa.mayorista !== null ? `Mayorista ${soles(tarifa.mayorista)} desde ${numero(tarifa.minimo)}` : '';
            } else {
                precio.textContent = `${soles(tarifa.mayorista)} c/u`;
                nota.textContent = `Solo mayorista, desde ${unidades(tarifa.minimo)}`;
            }
            nota.hidden = nota.textContent === '';
            precio.classList.toggle('is-sin-precio', ! configurado);

            input.disabled = ! configurado || prod.stock <= 0;
            if (input.disabled && input.checked) {
                input.checked = false;
                quitado = prod;
            }
        });

        const aviso = $('[data-vpos-sin-tarifa]');
        aviso.hidden = sinTarifa === 0;
        $('[data-vpos-sin-tarifa-texto]').textContent = sinTarifa === 1
            ? `1 producto no tiene precio para ${destinatarios[quien]}.`
            : `${sinTarifa} productos no tienen precio para ${destinatarios[quien]}.`;

        return quitado;
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
            nota = `ⓘ Precio mayorista (${soles(prod.mayorista)}) desde ${unidades(prod.minimoMayorista)}. Faltan ${unidades(faltan)}.`;
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
        const proveedorId = buscadores.proveedor?.valor() ?? '';
        const compradorId = buscadores.comprador?.valor() ?? '';
        const cant = cantidad();

        // Primero el catálogo: puede quitar un producto que ya no tiene precio para este comprador.
        const quitado = pintarCatalogo(quien, cant);
        if (quitado) {
            avisoQuitado = `${quitado.nombre} no tiene precio para ${destinatarios[quien]}: se quitó de la venta.`;
        }
        const prod = producto();
        if (prod) {
            avisoQuitado = '';
        }

        // Bloques según quién compra.
        form.querySelectorAll('[data-vpos-si-cliente]').forEach((bloque) => { bloque.hidden = bloque.dataset.vposSiCliente !== quien; });

        // Descuento semanal: solo con un proveedor válido seleccionado.
        const descuentoHabilitado = esProveedor && proveedorId !== '';
        pagoDescuento.disabled = ! descuentoHabilitado;
        if (! descuentoHabilitado && pagoDescuento.checked) {
            pagoContado.checked = true;
        }
        const requisito = $('[data-vpos-pago-requisito]');
        requisito.hidden = descuentoHabilitado;
        requisito.textContent = esProveedor ? 'Selecciona primero al proveedor.' : 'Disponible solo para ventas a un proveedor.';
        const pago = form.querySelector('[data-vpos-pago]:checked')?.value ?? 'CONTADO';

        const aplicado = prod ? precioAplicable(prod, esProveedor, cant) : null;
        const total = aplicado ? Math.round(cant * aplicado.precio * 100) / 100 : 0;
        // Solo de control: el backend rechaza la venta si la tarifa vigente ya no es esta.
        $('[data-vpos-precio-mostrado]').value = aplicado ? String(aplicado.precio) : '';

        // Cantidad: paso según la unidad de venta y tope de stock en el stepper.
        campoCantidad.step = prod && ! prod.entero ? 'any' : '1';
        campoCantidad.inputMode = campoCantidad.step === '1' ? 'numeric' : 'decimal';
        if (prod) {
            campoCantidad.max = String(prod.stock);
        } else {
            campoCantidad.removeAttribute('max');
        }
        form.querySelector('[data-vpos-paso="-1"]').disabled = cant <= 1;
        form.querySelector('[data-vpos-paso="1"]').disabled = !! prod && cant + 1 > prod.stock;

        const stock = $('[data-vpos-stock]');
        if (prod) {
            const restante = prod.stock - cant;
            stock.innerHTML = 'Disponible: <strong></strong>';
            stock.querySelector('strong').textContent = unidades(prod.stock);
            if (cant > 0 && restante >= 0) {
                stock.append(` · quedarán ${numero(restante)}`);
            }
        } else {
            stock.textContent = 'Elige un producto para ver el stock disponible.';
        }

        const hint = $('[data-vpos-hint]');
        hint.textContent = prod && ! aplicado ? sinPrecio(prod, esProveedor) : avisoQuitado;
        hint.hidden = hint.textContent === '';

        pintarPrecio(prod, esProveedor, cant, aplicado);

        // Errores junto a cada control (los de "falta elegir" solo tras intentar confirmar).
        let errorCliente = '';
        if (quien === 'COMPRADOR' && compradorId === '') {
            errorCliente = 'Selecciona el comprador registrado de la lista.';
        } else if (esProveedor && proveedorId === '') {
            errorCliente = 'Selecciona un proveedor válido de la lista.';
        }

        let errorCantidad = '';
        if (cantidadTexto() === '') {
            errorCantidad = intentado ? 'Ingresa la cantidad.' : '';
        } else if (cant <= 0) {
            errorCantidad = 'La cantidad debe ser mayor que cero.';
        } else if (prod && prod.entero && ! Number.isInteger(cant)) {
            errorCantidad = 'Este producto se vende por unidades enteras.';
        } else if (prod && cant > prod.stock) {
            errorCantidad = prod.stock > 0
                ? `Stock insuficiente: solo hay ${unidades(prod.stock)} disponibles.`
                : 'No hay unidades disponibles de este producto.';
        }

        mostrarError('cliente', intentado ? errorCliente : '');
        mostrarError('producto', intentado && ! prod ? 'Selecciona un producto.' : '');
        mostrarError('cantidad', errorCantidad);
        campoCantidad.closest('[data-vpos-stepper]').classList.toggle('is-error', errorCantidad !== '');

        // Saldo semanal del proveedor (descuento).
        const bloqueSaldo = $('[data-vpos-saldo]');
        const errorSaldo = $('[data-vpos-saldo-error]');
        const conDescuento = descuentoHabilitado && pago === 'DESCUENTO_PLANILLA';
        const saldo = conDescuento ? saldos.get(proveedorId) : null;
        bloqueSaldo.hidden = ! (saldo && typeof saldo.saldo === 'number');
        errorSaldo.hidden = true;

        if (conDescuento && ! saldo) {
            cargarSaldo(proveedorId);
        }

        let saldoInsuficiente = false;
        if (saldo && typeof saldo.saldo === 'number') {
            const despues = Math.round((saldo.saldo - total) * 100) / 100;
            saldoInsuficiente = despues < 0;
            $('[data-vpos-saldo-disponible]').textContent = soles(saldo.saldo);
            $('[data-vpos-saldo-compra]').textContent = soles(total);
            $('[data-vpos-saldo-despues]').textContent = soles(despues);
            $('[data-vpos-saldo-despues]').classList.toggle('is-negativo', saldoInsuficiente);
            if (saldoInsuficiente) {
                errorSaldo.textContent = 'La compra supera el saldo estimado del pago semanal. Cobra al contado o reduce la cantidad.';
                errorSaldo.hidden = false;
            }
        } else if (saldo && saldo.mensaje) {
            errorSaldo.textContent = saldo.mensaje;
            errorSaldo.hidden = false;
        }

        // Por qué no se puede confirmar todavía (el primero que falte).
        let motivo = '';
        if (errorCliente) {
            motivo = errorCliente;
        } else if (! prod) {
            motivo = 'Selecciona un producto.';
        } else if (cant <= 0) {
            motivo = 'Ingresa una cantidad mayor que cero.';
        } else if (errorCantidad) {
            motivo = errorCantidad;
        } else if (! aplicado) {
            motivo = sinPrecio(prod, esProveedor);
        } else if (conDescuento && (! saldo || saldo.cargando)) {
            motivo = 'Consultando el saldo del proveedor...';
        } else if (conDescuento && (typeof saldo.saldo !== 'number' || saldoInsuficiente)) {
            motivo = 'El saldo semanal del proveedor no alcanza.';
        }

        // Resumen.
        const nombreCliente = { PUBLICO: 'Público general', COMPRADOR: buscadores.comprador?.nombre(), PROVEEDOR: buscadores.proveedor?.nombre() }[quien];
        const tipos = { PROVEEDOR: 'proveedor', MAYORISTA: 'mayorista', PUBLICO: 'público' };
        const despues = prod ? prod.stock - cant : null;

        r('cliente').textContent = nombreCliente || 'Sin seleccionar';
        r('cliente').classList.toggle('is-pendiente', ! nombreCliente);
        r('producto').textContent = prod ? prod.nombre : '—';
        r('cantidad').textContent = cant > 0 ? unidades(cant) : '—';
        r('unitario').textContent = aplicado ? soles(aplicado.precio) : (prod ? 'Sin precio' : '—');
        r('unitario').classList.toggle('is-error', !! prod && ! aplicado);
        r('tipo').textContent = aplicado ? tipos[aplicado.tipo] : '';
        r('tipo').classList.toggle('is-mayorista', aplicado?.tipo === 'MAYORISTA');
        r('total').textContent = soles(total);
        r('totalBarra').textContent = soles(total);
        r('stock').textContent = prod ? numero(prod.stock) : '—';
        r('stockDespues').textContent = prod && cant > 0 ? (despues >= 0 ? numero(despues) : 'Insuficiente') : '—';
        r('stockDespues').classList.toggle('is-error', despues !== null && despues < 0);
        r('pago').textContent = pago === 'DESCUENTO_PLANILLA' ? 'Descuento semanal' : 'Contado · ingreso a Caja';

        const conError = errorCantidad !== '' || (!! prod && ! aplicado) || saldoInsuficiente || !! saldo?.mensaje;
        const estado = $('[data-vpos-estado]');
        estado.textContent = motivo === '' ? 'Lista para confirmar' : (conError ? 'Revisar' : 'Incompleta');
        estado.classList.toggle('is-lista', motivo === '');
        estado.classList.toggle('is-error', motivo !== '' && conError);

        const motivoEl = $('[data-vpos-motivo]');
        motivoEl.textContent = motivo;
        motivoEl.hidden = motivo === '';
        confirmar.forEach((boton) => { boton.disabled = motivo !== '' || enviando; });
        clienteAnterior = quien;
    }

    // Stepper − / + (enteros; el campo admite decimales si la unidad lo permite).
    form.querySelectorAll('[data-vpos-paso]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const prod = producto();
            const siguiente = Math.floor(cantidad()) + Number(boton.dataset.vposPaso);
            const tope = prod ? Math.floor(prod.stock) : Infinity;
            campoCantidad.value = String(Math.max(1, Math.min(siguiente, tope)));
            actualizar();
        });
    });

    // Filtro del catálogo (solo se pinta con muchos productos).
    const filtroProductos = $('[data-vpos-producto-filtro]');
    filtroProductos?.addEventListener('input', () => {
        const buscado = normalizar(filtroProductos.value.trim());
        let visibles = 0;
        form.querySelectorAll('[data-vpos-producto-item]').forEach((item) => {
            const coincide = buscado === '' || item.dataset.texto.includes(buscado) || item.querySelector('input').checked;
            item.hidden = ! coincide;
            visibles += coincide ? 1 : 0;
        });
        $('[data-vpos-productos-vacio]').hidden = visibles > 0;
    });

    // Al cambiar de tipo de comprador se suelta al comprador/proveedor anterior: la venta no
    // arrastra su identidad (el listener del radio corre antes que el 'change' del form).
    form.querySelectorAll('[data-vpos-cliente]').forEach((radio) => {
        radio.addEventListener('change', () => {
            ({ COMPRADOR: buscadores.comprador, PROVEEDOR: buscadores.proveedor })[clienteAnterior]?.limpiar();
            clienteAnterior = cliente();
        });
    });

    form.addEventListener('change', actualizar);
    campoCantidad.addEventListener('input', actualizar);
    form.addEventListener('submit', (evento) => {
        intentado = true;
        actualizar();
        if (confirmar[0].disabled) {
            evento.preventDefault();
            form.querySelector('.vpos-error-campo:not([hidden])')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        enviando = true;
        confirmar.forEach((boton) => { boton.disabled = true; });
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

            buscadores.proveedor?.limpiar();
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
