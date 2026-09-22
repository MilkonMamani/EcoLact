import { cargarGoogleMaps, getMapId, getCsrfToken } from '../maps.js';

/**
 * Pantalla operativa del acopiador para un recorrido: mapa + lista
 * sincronizados, identificación por DNI/QR, registro de acopio y "no
 * entregó". El estado siempre se inicializa desde los datos que el
 * servidor incrustó al renderizar la página (nunca localStorage) y solo se
 * actualiza tras la confirmación del servidor a cada acción.
 *
 * Formatos de QR soportados: únicamente el QR interno del proveedor
 * (identificador opaco generado por EcoLact, ver proveedor.qr_token). Si el
 * navegador soporta la Barcode Detection API (`BarcodeDetector`), se activa
 * el escaneo con cámara para leer ese QR; si no, o si la cámara es
 * denegada, se informa con un mensaje claro y queda disponible la búsqueda
 * manual por DNI, que siempre funciona.
 */
const root = document.querySelector('[data-recorrido]');

if (root) {
    iniciarPantallaRecorrido(root);
}

function iniciarPantallaRecorrido(root) {
    const recorridoId = root.dataset.recorridoId;
    const datosIniciales = JSON.parse(document.getElementById('recorrido-paradas-data').textContent);

    const estado = {
        paradas: new Map(datosIniciales.paradas.map((p) => [String(p.id), { ...p }])),
        paradaSeleccionadaId: null,
        tokenIdempotencia: crypto.randomUUID(),
    };

    const els = {
        lista: root.querySelector('[data-recorrido-lista]'),
        progresoRecolectados: root.querySelector('[data-progreso-recolectados]'),
        progresoPendientes: root.querySelector('[data-progreso-pendientes]'),
        progresoNoEntrego: root.querySelector('[data-progreso-no-entrego]'),
        progresoLitros: root.querySelector('[data-progreso-litros]'),
        buscarDniInput: root.querySelector('[data-buscar-dni-input]'),
        buscarDniBoton: root.querySelector('[data-buscar-dni-boton]'),
        buscarDniError: root.querySelector('[data-buscar-dni-error]'),
        escanearBoton: root.querySelector('[data-escanear-boton]'),
        escanearVideo: root.querySelector('[data-escanear-video]'),
        escanearAviso: root.querySelector('[data-escanear-aviso]'),
        panel: root.querySelector('[data-parada-panel]'),
        mapaContenedor: root.querySelector('[data-recorrido-mapa]'),
        mapaAviso: root.querySelector('[data-recorrido-mapa-aviso]'),
    };

    const markers = new Map();
    let mapa = null;
    let mapsLib = null;

    renderizarLista();
    renderizarProgreso();
    inicializarMapa();
    conectarBusquedaDni();
    conectarEscaneo();

    async function inicializarMapa() {
        mapsLib = await cargarGoogleMaps();

        if (!mapsLib) {
            els.mapaAviso.hidden = false;
            return;
        }

        const conUbicacion = [...estado.paradas.values()].filter((p) => p.latitud !== null && p.longitud !== null);

        if (conUbicacion.length === 0) {
            els.mapaAviso.hidden = false;
            els.mapaAviso.textContent = 'Ningún proveedor de este recorrido tiene ubicación registrada. Usa la lista para operar.';
            return;
        }

        mapa = new mapsLib.Map(els.mapaContenedor, {
            center: { lat: conUbicacion[0].latitud, lng: conUbicacion[0].longitud },
            zoom: 13,
            mapId: getMapId() || undefined,
        });

        const bounds = new google.maps.LatLngBounds();

        conUbicacion.forEach((parada) => {
            const marker = crearMarcador(parada);
            markers.set(String(parada.id), marker);
            bounds.extend({ lat: parada.latitud, lng: parada.longitud });
        });

        if (conUbicacion.length > 1) {
            mapa.fitBounds(bounds);
        }
    }

    function crearMarcador(parada) {
        const pin = new mapsLib.PinElement(colorPin(parada.estado, parada.ordenVisita));
        const marker = new mapsLib.AdvancedMarkerElement({
            map: mapa,
            position: { lat: parada.latitud, lng: parada.longitud },
            title: `${parada.ordenVisita}. ${parada.proveedorNombre} (${etiquetaEstado(parada.estado)})`,
            content: pin.element,
        });

        marker.addListener('click', () => seleccionarParada(parada.id));

        return marker;
    }

    function colorPin(estadoParada, orden) {
        const colores = {
            PENDIENTE: '#2563eb',
            RECOLECTADO: '#16a34a',
            NO_ENTREGO: '#ea580c',
        };

        return { background: colores[estadoParada] ?? colores.PENDIENTE, borderColor: '#ffffff', glyphColor: '#ffffff', glyph: String(orden) };
    }

    function actualizarMarcador(parada) {
        const marker = markers.get(String(parada.id));
        if (!marker || !mapsLib) {
            return;
        }

        const pin = new mapsLib.PinElement(colorPin(parada.estado, parada.ordenVisita));
        marker.content = pin.element;
        marker.title = `${parada.ordenVisita}. ${parada.proveedorNombre} (${etiquetaEstado(parada.estado)})`;
    }

    function etiquetaEstado(estadoParada) {
        return { PENDIENTE: 'Pendiente', RECOLECTADO: 'Recolectado', NO_ENTREGO: 'No entregó' }[estadoParada] ?? estadoParada;
    }

    function renderizarLista() {
        els.lista.innerHTML = '';

        [...estado.paradas.values()]
            .sort((a, b) => a.ordenVisita - b.ordenVisita)
            .forEach((parada) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'flex w-full items-center justify-between gap-2 rounded-lg border border-text-muted/10 px-3 py-2 text-left text-sm hover:bg-text-muted/5';
                item.dataset.paradaId = parada.id;

                const izquierda = document.createElement('div');
                izquierda.innerHTML = `
                    <span class="font-medium text-text">${parada.ordenVisita}. ${escapeHtml(parada.proveedorNombre)}</span>
                    <span class="block text-xs text-text-muted">DNI ${escapeHtml(parada.proveedorDni)} · ${parada.latitud === null ? 'Sin ubicación' : escapeHtml(parada.referenciaUbicacion || 'Sin referencia')}</span>
                `;

                const badge = document.createElement('span');
                badge.className = `shrink-0 rounded-full px-2 py-1 text-xs font-medium ${claseBadge(parada.estado)}`;
                badge.textContent = etiquetaEstado(parada.estado);

                item.append(izquierda, badge);
                item.addEventListener('click', () => seleccionarParada(parada.id));

                els.lista.append(item);
            });
    }

    function claseBadge(estadoParada) {
        return {
            PENDIENTE: 'bg-primary/10 text-primary',
            RECOLECTADO: 'bg-success/10 text-success',
            NO_ENTREGO: 'bg-warning/10 text-warning',
        }[estadoParada] ?? 'bg-text-muted/10 text-text-muted';
    }

    function renderizarProgreso() {
        const valores = [...estado.paradas.values()];
        els.progresoRecolectados.textContent = valores.filter((p) => p.estado === 'RECOLECTADO').length;
        els.progresoPendientes.textContent = valores.filter((p) => p.estado === 'PENDIENTE').length;
        els.progresoNoEntrego.textContent = valores.filter((p) => p.estado === 'NO_ENTREGO').length;
        els.progresoLitros.textContent = valores.reduce((acc, p) => acc + (p.litrosRegistrados ?? 0), 0).toFixed(2);
    }

    function seleccionarParada(paradaId) {
        estado.paradaSeleccionadaId = String(paradaId);
        estado.tokenIdempotencia = crypto.randomUUID();
        renderizarPanel();
    }

    function renderizarPanel() {
        const parada = estado.paradas.get(estado.paradaSeleccionadaId);

        if (!parada) {
            els.panel.innerHTML = '<p class="text-sm text-text-muted">Selecciona una parada en el mapa o en la lista, o identifica al proveedor por DNI/QR.</p>';
            return;
        }

        els.panel.innerHTML = `
            <div class="space-y-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-text-muted">Parada ${parada.ordenVisita}</p>
                    <p class="text-base font-semibold text-text">${escapeHtml(parada.proveedorNombre)}</p>
                    <p class="text-sm text-text-muted">DNI ${escapeHtml(parada.proveedorDni)} · ${parada.latitud === null ? 'Sin ubicación registrada' : escapeHtml(parada.referenciaUbicacion || 'Sin referencia adicional')}</p>
                    <p class="mt-1 text-sm">Estado: <span class="font-medium">${etiquetaEstado(parada.estado)}</span> · Litros registrados: <span class="font-medium">${(parada.litrosRegistrados ?? 0).toFixed(2)}</span></p>
                    ${parada.observacion ? `<p class="mt-1 text-xs text-warning">Observación: ${escapeHtml(parada.observacion)}</p>` : ''}
                </div>

                <form data-form-acopio class="space-y-2 rounded-lg border border-text-muted/10 p-3">
                    <p class="text-sm font-medium text-text">Registrar acopio</p>
                    <label class="block text-xs text-text-muted">Litros
                        <input type="number" step="0.01" min="0.01" name="litros" required class="mt-1 w-full rounded-lg border border-text-muted/20 px-3 py-2 text-sm">
                    </label>
                    <label class="block text-xs text-text-muted">Fecha/hora de ordeño (opcional)
                        <input type="datetime-local" name="fecha_hora_ordeño" class="mt-1 w-full rounded-lg border border-text-muted/20 px-3 py-2 text-sm">
                    </label>
                    <label class="block text-xs text-text-muted">Observación (opcional)
                        <textarea name="observacion" rows="2" class="mt-1 w-full rounded-lg border border-text-muted/20 px-3 py-2 text-sm"></textarea>
                    </label>
                    <p data-form-acopio-error class="hidden text-xs text-danger"></p>
                    <button type="submit" class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-medium text-white">Guardar acopio</button>
                </form>

                <form data-form-no-entrego class="space-y-2 rounded-lg border border-text-muted/10 p-3">
                    <p class="text-sm font-medium text-text">Registrar "no entregó"</p>
                    <label class="block text-xs text-text-muted">Motivo
                        <textarea name="motivo" rows="2" required class="mt-1 w-full rounded-lg border border-text-muted/20 px-3 py-2 text-sm"></textarea>
                    </label>
                    <p data-form-no-entrego-error class="hidden text-xs text-danger"></p>
                    <button type="submit" class="w-full rounded-lg border border-warning px-3 py-2 text-sm font-medium text-warning">Marcar no entregó</button>
                </form>
            </div>
        `;

        els.panel.querySelector('[data-form-acopio]').addEventListener('submit', (event) => enviarAcopio(event, parada));
        els.panel.querySelector('[data-form-no-entrego]').addEventListener('submit', (event) => enviarNoEntrego(event, parada));
    }

    async function enviarAcopio(event, parada) {
        event.preventDefault();
        const form = event.target;
        const errorEl = form.querySelector('[data-form-acopio-error]');
        errorEl.classList.add('hidden');

        const datos = Object.fromEntries(new FormData(form).entries());
        const boton = form.querySelector('button[type="submit"]');
        boton.disabled = true;

        try {
            const response = await fetch(`/acopiador/recorridos/${recorridoId}/paradas/${parada.id}/acopio`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ ...datos, token_idempotencia: estado.tokenIdempotencia }),
            });

            const cuerpo = await response.json();

            if (!response.ok) {
                throw new Error(cuerpo.message || 'No se pudo registrar el acopio.');
            }

            const actualizada = { ...parada, estado: cuerpo.parada.estado, litrosRegistrados: (parada.litrosRegistrados ?? 0) + Number(cuerpo.litros) };
            estado.paradas.set(String(parada.id), actualizada);
            estado.tokenIdempotencia = crypto.randomUUID();

            actualizarMarcador(actualizada);
            renderizarLista();
            renderizarProgreso();
            renderizarPanel();
        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.classList.remove('hidden');
        } finally {
            boton.disabled = false;
        }
    }

    async function enviarNoEntrego(event, parada) {
        event.preventDefault();
        const form = event.target;
        const errorEl = form.querySelector('[data-form-no-entrego-error]');
        errorEl.classList.add('hidden');

        const datos = Object.fromEntries(new FormData(form).entries());
        const boton = form.querySelector('button[type="submit"]');
        boton.disabled = true;

        try {
            const response = await fetch(`/acopiador/recorridos/${recorridoId}/paradas/${parada.id}/no-entrego`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(datos),
            });

            const cuerpo = await response.json();

            if (!response.ok) {
                throw new Error(cuerpo.message || 'No se pudo registrar "no entregó".');
            }

            const actualizada = { ...parada, estado: cuerpo.parada.estado, observacion: cuerpo.parada.observacion };
            estado.paradas.set(String(parada.id), actualizada);

            actualizarMarcador(actualizada);
            renderizarLista();
            renderizarProgreso();
            renderizarPanel();
        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.classList.remove('hidden');
        } finally {
            boton.disabled = false;
        }
    }

    function conectarBusquedaDni() {
        els.buscarDniBoton?.addEventListener('click', () => identificar('DNI', els.buscarDniInput.value.trim()));
    }

    async function identificar(metodo, identificador) {
        els.buscarDniError.classList.add('hidden');

        if (!identificador) {
            return;
        }

        try {
            const response = await fetch(`/acopiador/recorridos/${recorridoId}/identificar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ metodo, identificador }),
            });

            const cuerpo = await response.json();

            if (!response.ok) {
                throw new Error(cuerpo.message || 'No se pudo identificar al proveedor.');
            }

            seleccionarParada(cuerpo.parada_id);
            els.buscarDniInput.value = '';
        } catch (error) {
            els.buscarDniError.textContent = error.message;
            els.buscarDniError.classList.remove('hidden');
        }
    }

    function conectarEscaneo() {
        if (!els.escanearBoton) {
            return;
        }

        if (!('BarcodeDetector' in window)) {
            els.escanearBoton.disabled = true;
            els.escanearAviso.hidden = false;
            els.escanearAviso.textContent = 'Tu navegador no admite escaneo de código QR. Usa la búsqueda por DNI.';
            return;
        }

        els.escanearBoton.addEventListener('click', () => iniciarEscaneo());
    }

    async function iniciarEscaneo() {
        els.escanearAviso.hidden = true;
        let stream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        } catch {
            els.escanearAviso.hidden = false;
            els.escanearAviso.textContent = 'No se pudo acceder a la cámara: revisa los permisos del navegador.';
            return;
        }

        els.escanearVideo.hidden = false;
        els.escanearVideo.srcObject = stream;
        await els.escanearVideo.play();

        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        let activo = true;

        const detener = () => {
            activo = false;
            stream.getTracks().forEach((track) => track.stop());
            els.escanearVideo.hidden = true;
        };

        const loop = async () => {
            if (!activo) {
                return;
            }

            try {
                const codigos = await detector.detect(els.escanearVideo);
                if (codigos.length > 0) {
                    detener();
                    await identificar('QR', codigos[0].rawValue);
                    return;
                }
            } catch {
                // Reintenta en el próximo frame; un fallo puntual de detección no es un error fatal.
            }

            requestAnimationFrame(loop);
        };

        requestAnimationFrame(loop);
    }
}

function escapeHtml(valor) {
    const div = document.createElement('div');
    div.textContent = valor ?? '';
    return div.innerHTML;
}
