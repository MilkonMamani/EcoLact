import { alFallarGoogleMaps, cargarGoogleMaps, esCoordenadaValida, getMapId, getCsrfToken } from '../maps.js';
import {
    crearPinProveedor,
    dibujarDelimitacion,
    encuadrar,
    iniciarEditorDelimitacion,
    iniciarMapaGeneral,
    limitesDeVertices,
    resaltarDelimitacion,
    verticesDeGeometria,
    zonaQueContiene,
} from './zonas-mapa.js';

/**
 * Pantalla /admin/zonas. El panel por fetch, sus pestañas y el skeleton son los de
 * admin-fichas.js (igual que Proveedores y Acopiadores). Aquí va lo propio de Zonas:
 *  - mapa general de zonas (zonas-mapa.js), sincronizado con el panel;
 *  - buscador principal de proveedores: elegir uno enfoca y resalta su pin en el mapa;
 *  - "Mover ubicación": solo el pin seleccionado se vuelve arrastrable; al guardar se detecta
 *    la zona (point-in-polygon), se confirma y el servidor vuelve a verificarla antes de guardar;
 *  - editor de delimitación de "Editar zona" (zonas-mapa.js);
 *  - filtro local de la pestaña Proveedores;
 *  - "+ Asignar proveedor": buscador de proveedores (nombre o DNI) que abre el modal de asignación;
 *  - modal "Asignar proveedor a zona y ruta": mapa con las mismas delimitaciones del mapa general
 *    donde un click ubica al proveedor y detecta su zona, y después Zona → Ruta.
 *
 * Google Maps se carga una sola vez por página (cargarGoogleMaps memoiza la promesa) y solo
 * cuando hace falta.
 */
const pagina = document.querySelector('[data-zonas-page]');

if (pagina) {
    iniciarZonasPage(pagina);
}

function iniciarZonasPage(pagina) {
    const mapaGeneral = iniciarMapaGeneral(pagina, zonaDelPanel(pagina));
    const asignacion = iniciarModalAsignacion(pagina);
    const mover = iniciarMoverUbicacion(pagina, mapaGeneral);

    iniciarEditorDelimitacion(pagina);
    iniciarBuscador(pagina, asignacion.abrir);
    iniciarBuscadorPrincipal(pagina, mapaGeneral, mover, asignacion.abrir);
    iniciarFiltroDeProveedores(pagina);

    // El panel muestra otra zona (desde el mapa, o tras mover un proveedor): el mapa general la
    // resalta y se acerca a ella, salvo que ya fuera la resaltada.
    document.addEventListener('fichas:panel-actualizado', () => {
        const zonaId = zonaDelPanel(pagina);

        if (mapaGeneral && zonaId !== mapaGeneral.seleccionada()) {
            mapaGeneral.resaltar(zonaId, { zoom: true });
        }
    });

    // Lápiz de cada fila de proveedor (delegado, porque el panel se reemplaza).
    pagina.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-asignar-abrir]');

        if (boton) {
            asignacion.abrir(JSON.parse(boton.dataset.asignarAbrir));
        }
    });

    const parametros = new URLSearchParams(window.location.search);

    // Enlace profundo desde el perfil del proveedor: ?asignar=<DNI> abre el mismo
    // modal de asignación (zona, ruta, mapa) ya cargado con los datos del proveedor.
    if (parametros.get('asignar')) {
        abrirAsignacionPorDni(pagina, parametros.get('asignar'), asignacion.abrir);
    }

    // ?proveedor=<ID> vuelve a enfocar ese pin (p. ej. tras recargar la pantalla). Solo ids numéricos.
    if (/^\d+$/.test(parametros.get('proveedor') ?? '') && mapaGeneral) {
        mapaGeneral.seleccionarProveedor(parseInt(parametros.get('proveedor'), 10));
    }

    // Tras un error de validación de un formulario de zona/ruta, el servidor
    // indica qué modal debe volver a abrirse.
    if (pagina.dataset.abrirModal) {
        abrirModalPorId(pagina.dataset.abrirModal);
    }
}

async function abrirAsignacionPorDni(pagina, dni, abrirAsignacion) {
    const url = new URL(window.location.href);
    url.searchParams.delete('asignar');
    window.history.replaceState(window.history.state, '', url);

    try {
        const respuesta = await fetch(`${pagina.dataset.urlBuscar}?q=${encodeURIComponent(dni)}`, { headers: { Accept: 'application/json' } });
        const proveedor = (await respuesta.json()).find((p) => p.dni === dni);

        if (proveedor) {
            abrirAsignacion(proveedor);
        }
    } catch {
        // Sin el modal el administrador aún puede usar "+ Asignar proveedor".
    }
}

/** Reutiliza la apertura de modales de app.js disparando un clic sobre un botón. */
function abrirModalPorId(id) {
    const disparador = document.createElement('button');
    disparador.type = 'button';
    disparador.hidden = true;
    disparador.dataset.modalOpen = id;
    document.body.append(disparador);
    disparador.click();
    disparador.remove();
}

function cerrarModal(modal) {
    if (modal?.classList.contains('is-open')) {
        modal.querySelector('[data-modal-close]')?.click();
    }
}

/** Aviso breve abajo a la derecha (mismo estilo que el del switch de estado). */
function aviso(texto) {
    document.querySelector('.fichas-toast')?.remove();

    const caja = Object.assign(document.createElement('div'), { className: 'fichas-toast', textContent: texto });
    caja.setAttribute('role', 'status');
    document.body.append(caja);
    window.setTimeout(() => caja.remove(), 4000);
}

/** Zona que se está viendo en el panel (null en "Proveedores sin zona"). */
function zonaDelPanel(pagina) {
    const id = pagina.querySelector('[data-fichas-panel] [data-zona-id]')?.dataset.zonaId;

    return id ? parseInt(id, 10) : null;
}

/** Muestra una zona en el panel sin que el mapa general cambie de encuadre. */
function mostrarZonaEnPanel(mapaGeneral, zonaId, { forzar = false } = {}) {
    mapaGeneral?.resaltar(zonaId);
    document.dispatchEvent(new CustomEvent('fichas:seleccionar', { detail: { id: zonaId, desplazar: false, forzar } }));
}

// ------------------------------------------------- buscador principal

/**
 * "Buscar proveedor por nombre o DNI..." sobre el mapa: consulta proveedores reales
 * (admin.zonas.proveedores.buscar) y, al elegir uno, enfoca y resalta su pin y abre su zona
 * en el panel. Un proveedor sin zona o sin ubicación no tiene pin: se abre su asignación.
 * La X limpia la selección (y cancela "Mover ubicación", confirmando si había cambios).
 */
function iniciarBuscadorPrincipal(pagina, mapaGeneral, mover, abrirAsignacion) {
    const caja = pagina.querySelector('[data-zonas-buscador]');

    if (!caja) {
        return;
    }

    const input = caja.querySelector('[data-zonas-buscador-input]');
    const resultados = caja.querySelector('[data-zonas-buscador-resultados]');
    const limpiar = caja.querySelector('[data-zonas-buscador-limpiar]');
    const url = pagina.dataset.urlBuscar;
    let temporizador;
    let controlador;

    const cerrar = () => {
        resultados.hidden = true;
        resultados.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
    };

    const abrir = (...hijos) => {
        resultados.replaceChildren(...hijos);
        resultados.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const mensaje = (texto) => {
        const p = document.createElement('p');
        p.className = 'zonas-buscador-mensaje';
        p.textContent = texto;
        abrir(p);
    };

    /** Si hay un movimiento a medias, pide confirmación antes de descartarlo. */
    const descartarMovimiento = () => {
        if (mover.hayCambios() && !window.confirm('Hay un cambio de ubicación sin guardar. ¿Descartarlo?')) {
            return false;
        }

        mover.cancelar();

        return true;
    };

    const elegir = async (proveedor) => {
        cerrar();

        if (!descartarMovimiento()) {
            return;
        }

        input.value = proveedor.nombre;

        const tienePin = proveedor.rutaId && proveedor.latitud !== null && proveedor.longitud !== null;

        // Sin mapa disponible (Google Maps falló) igual se abre su zona en el panel.
        const enfocado = tienePin && mapaGeneral && await mapaGeneral.seleccionarProveedor(proveedor.proveedorId);

        if (enfocado || (tienePin && window.__ecolactMapsFallo)) {
            if (proveedor.zonaId !== zonaDelPanel(pagina)) {
                mostrarZonaEnPanel(mapaGeneral, proveedor.zonaId);
            }

            return;
        }

        // Sin zona o sin punto registrado no hay pin: se ubica desde el modal de asignación.
        abrirAsignacion(proveedor);
    };

    const opcion = (proveedor) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.setAttribute('role', 'option');
        boton.className = 'zonas-buscador-opcion';

        const nombre = document.createElement('strong');
        nombre.textContent = proveedor.nombre;
        const dni = document.createElement('span');
        dni.textContent = `DNI ${proveedor.dni}`;
        const zona = document.createElement('span');

        if (!proveedor.rutaId) {
            zona.className = 'is-aviso';
            zona.textContent = 'Sin zona ni ruta';
        } else {
            zona.textContent = proveedor.latitud !== null ? proveedor.zonaNombre : `${proveedor.zonaNombre} · sin ubicación`;
        }

        boton.append(nombre, dni, zona);
        boton.addEventListener('click', () => elegir(proveedor));

        return boton;
    };

    input.addEventListener('input', () => {
        window.clearTimeout(temporizador);
        controlador?.abort();

        const texto = input.value.trim();

        if (texto === '') {
            cerrar();
            return;
        }

        temporizador = window.setTimeout(async () => {
            controlador = new AbortController();

            try {
                const respuesta = await fetch(`${url}?q=${encodeURIComponent(texto)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controlador.signal,
                });

                if (!respuesta.ok) {
                    throw new Error('respuesta no válida');
                }

                const proveedores = await respuesta.json();

                if (proveedores.length === 0) {
                    mensaje('No encontramos proveedores con ese nombre o DNI.');
                } else {
                    abrir(...proveedores.map(opcion));
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    mensaje('No se pudo realizar la búsqueda. Inténtalo de nuevo.');
                }
            }
        }, 250);
    });

    // Flechas para recorrer los resultados y Escape para cerrarlos.
    caja.addEventListener('keydown', (event) => {
        const opciones = [...resultados.querySelectorAll('[role="option"]')];
        const actual = opciones.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            cerrar();
            input.focus();
        } else if (event.key === 'ArrowDown' && opciones.length > 0) {
            event.preventDefault();
            opciones[Math.min(actual + 1, opciones.length - 1)].focus();
        } else if (event.key === 'ArrowUp' && actual >= 0) {
            event.preventDefault();
            (actual === 0 ? input : opciones[actual - 1]).focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!caja.contains(event.target)) {
            cerrar();
        }
    });

    limpiar.addEventListener('click', () => {
        if (!descartarMovimiento()) {
            return;
        }

        mapaGeneral?.limpiarProveedor();
        input.value = '';
        limpiar.hidden = true;
        cerrar();
        input.focus();
    });

    // El pin elegido (desde aquí o con un click en el mapa) se refleja en el buscador.
    document.addEventListener('zonas:proveedor-seleccionado', (event) => {
        const proveedor = event.detail.proveedor;

        input.value = proveedor ? proveedor.nombre : '';
        limpiar.hidden = !proveedor;
    });
}

// ------------------------------------------------- mover ubicación

/**
 * "Mover ubicación" (botón del popup del pin). Mientras dura, solo el pin elegido es
 * arrastrable y la barra del mapa ofrece Cancelar / Guardar. Al guardar se detecta la zona con
 * las delimitaciones del mapa y se confirma según el caso: misma zona (solo coordenadas), otra
 * zona (cambio de zona y ruta) o fuera de toda zona (no se guarda). El servidor vuelve a
 * calcular la zona; si coincide, guarda y devuelve el mapa recalculado, que se aplica sin
 * recargar: pin y color, contadores de las etiquetas y el panel de la zona destino.
 */
function iniciarMoverUbicacion(pagina, mapaGeneral) {
    const barra = pagina.querySelector('[data-zonas-mover-barra]');
    const inactivo = { hayCambios: () => false, cancelar: () => {} };

    if (!mapaGeneral || !barra) {
        return inactivo;
    }

    const nombreBarra = barra.querySelector('[data-zonas-mover-nombre]');
    const ayuda = barra.querySelector('[data-zonas-mover-ayuda]');
    const botonGuardar = barra.querySelector('[data-zonas-mover-guardar]');
    const modales = {
        misma: document.getElementById('modal-mover-misma-zona'),
        cambio: document.getElementById('modal-mover-cambio-zona'),
        fuera: document.getElementById('modal-mover-fuera'),
    };
    const catalogo = JSON.parse(pagina.dataset.catalogo || '[]');

    let estado = null;

    const soltar = (posicion) => {
        estado.posicion = posicion;
        estado.zona = mapaGeneral.zonaEn(posicion.lat, posicion.lng, estado.proveedor.zonaId);
        botonGuardar.disabled = false;
        barra.classList.toggle('is-fuera', !estado.zona);

        if (!estado.zona) {
            ayuda.textContent = 'Fuera de las zonas de acopio: esta ubicación no se puede guardar.';
        } else if (estado.zona.id === estado.proveedor.zonaId) {
            ayuda.textContent = `Sigue dentro de ${estado.zona.nombre}.`;
        } else {
            ayuda.textContent = `Nueva zona detectada: ${estado.zona.nombre}.`;
        }
    };

    const iniciar = (id) => {
        const proveedor = mapaGeneral.proveedor(id);

        if (!proveedor || estado || !mapaGeneral.activarArrastre(id, soltar)) {
            return;
        }

        estado = { proveedor, posicion: null, zona: null };
        nombreBarra.textContent = proveedor.nombre;
        ayuda.textContent = 'Arrastra el marcador a su nueva ubicación.';
        botonGuardar.disabled = true;
        barra.classList.remove('is-fuera');
        barra.hidden = false;
    };

    const terminar = ({ revertir }) => {
        mapaGeneral.desactivarArrastre({ revertir });
        estado = null;
        barra.hidden = true;
        barra.classList.remove('is-fuera');
    };

    const cancelar = () => {
        if (estado) {
            terminar({ revertir: true });
        }
    };

    // --- modales de confirmación -------------------------------------------

    const prepararModal = (modal) => {
        modal.querySelectorAll('[data-mover-nombre]').forEach((el) => {
            el.textContent = estado.proveedor.nombre;
        });
        modal.querySelectorAll('[data-mover-zona-actual]').forEach((el) => {
            el.textContent = estado.proveedor.zonaNombre;
            el.style.setProperty('--zona-color', mapaGeneral.zona(estado.proveedor.zonaId)?.color ?? '#475467');
        });

        const error = modal.querySelector('[data-mover-error]');

        if (error) {
            error.hidden = true;
            error.textContent = '';
        }

        const confirmar = modal.querySelector('[data-mover-confirmar]');

        if (confirmar) {
            confirmar.disabled = false;
        }
    };

    const mostrarError = (modal, texto) => {
        const error = modal.querySelector('[data-mover-error]');
        error.textContent = texto;
        error.hidden = false;
    };

    const zonaDelCatalogo = (zonaId) => catalogo.find((zona) => zona.id === zonaId) ?? null;

    /** Ruta destino: se muestra la única, se elige entre varias o se crea la principal si no hay. */
    const pintarRutas = () => {
        const modal = modales.cambio;
        const zona = zonaDelCatalogo(estado.zona.id);
        const rutas = zona?.rutas ?? [];
        const unica = modal.querySelector('[data-mover-ruta-unica]');
        const selector = modal.querySelector('[data-mover-ruta-selector]');
        const select = modal.querySelector('[data-mover-ruta]');
        const sinRuta = modal.querySelector('[data-mover-sin-ruta]');

        unica.hidden = rutas.length !== 1;
        selector.hidden = rutas.length < 2;
        sinRuta.hidden = rutas.length > 0 || zona === null;
        modal.querySelector('[data-mover-confirmar]').disabled = zona === null || rutas.length === 0;

        if (rutas.length === 1) {
            unica.textContent = `Pasará a la ruta ${rutas[0].nombre}.`;
        }

        select.replaceChildren(...rutas.map((ruta) => new Option(ruta.nombre, ruta.id)));
    };

    const abrirConfirmacion = () => {
        if (!estado?.posicion) {
            return;
        }

        const { proveedor, zona } = estado;

        if (!zona) {
            modales.fuera.querySelector('[data-mover-fuera-detalle]').textContent
                = `${proveedor.nombre} debe quedar dentro de alguna zona. Sigue moviendo el marcador o cancela para dejarlo donde estaba.`;
            abrirModalPorId(modales.fuera.id);
            return;
        }

        if (zona.id === proveedor.zonaId) {
            prepararModal(modales.misma);
            abrirModalPorId(modales.misma.id);
            return;
        }

        const modal = modales.cambio;
        prepararModal(modal);

        const nueva = modal.querySelector('[data-mover-zona-nueva]');
        nueva.textContent = zona.nombre;
        nueva.style.setProperty('--zona-color', zona.color);
        pintarRutas();

        if (zona.estado !== 'ACTIVO' || !zonaDelCatalogo(zona.id)) {
            mostrarError(modal, `La zona ${zona.nombre} está inactiva: no se pueden mover proveedores a ella.`);
        }

        abrirModalPorId(modal.id);
    };

    const guardar = async (modal) => {
        if (!estado?.zona || !estado.posicion) {
            return;
        }

        const confirmar = modal.querySelector('[data-mover-confirmar]');
        const { proveedor, posicion, zona } = estado;
        const cambioZona = zona.id !== proveedor.zonaId;
        const ruta = cambioZona && !modal.querySelector('[data-mover-ruta-selector]').hidden
            ? modal.querySelector('[data-mover-ruta]').value
            : null;

        confirmar.disabled = true;

        try {
            const respuesta = await fetch(pagina.dataset.urlMover.replace('__ID__', proveedor.id), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({
                    latitud: Number(posicion.lat.toFixed(7)),
                    longitud: Number(posicion.lng.toFixed(7)),
                    zona_id: zona.id,
                    ruta_id: ruta ? parseInt(ruta, 10) : null,
                }),
            });
            const json = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                throw new Error(json.message || 'No se pudo guardar la ubicación. Inténtalo de nuevo.');
            }

            const { movimiento } = json;

            terminar({ revertir: false });
            cerrarModal(modal);
            mapaGeneral.actualizar(json.mapa);
            mapaGeneral.seleccionarProveedor(movimiento.proveedorId, { enfocar: false });

            // El panel se vuelve a pedir: su pestaña Proveedores y sus métricas salen del servidor.
            if (movimiento.cambioZona || zonaDelPanel(pagina) === movimiento.zonaNueva.id) {
                mostrarZonaEnPanel(mapaGeneral, movimiento.zonaNueva.id, { forzar: true });
            }

            aviso(json.message);
        } catch (error) {
            mostrarError(modal, error.message || 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.');
            confirmar.disabled = false;
        }
    };

    modales.misma.querySelector('[data-mover-confirmar]').addEventListener('click', () => guardar(modales.misma));
    modales.cambio.querySelector('[data-mover-confirmar]').addEventListener('click', () => guardar(modales.cambio));
    modales.fuera.querySelector('[data-mover-cancelar-todo]').addEventListener('click', () => {
        cerrarModal(modales.fuera);
        cancelar();
    });

    // Alta rápida de la "Ruta principal" de una zona destino sin rutas (RutaController::store, rama JSON).
    const botonCrearRuta = modales.cambio.querySelector('[data-mover-crear-ruta]');
    botonCrearRuta.addEventListener('click', async () => {
        const zona = zonaDelCatalogo(estado?.zona?.id);

        if (!zona) {
            return;
        }

        botonCrearRuta.disabled = true;

        try {
            const respuesta = await fetch(pagina.dataset.urlNuevaRuta.replace('__ID__', zona.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ nombre: 'Ruta principal', estado: 'ACTIVO' }),
            });
            const json = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                throw new Error(json.message || 'No se pudo crear la ruta.');
            }

            zona.rutas.push(json.ruta);
            pintarRutas();
        } catch (error) {
            mostrarError(modales.cambio, error.message || 'No se pudo crear la ruta.');
        } finally {
            botonCrearRuta.disabled = false;
        }
    });

    botonGuardar.addEventListener('click', abrirConfirmacion);
    barra.querySelector('[data-zonas-mover-cancelar]').addEventListener('click', cancelar);
    document.addEventListener('zonas:mover-ubicacion', (event) => iniciar(event.detail.id));

    return {
        hayCambios: () => estado?.posicion != null,
        cancelar,
    };
}

// ------------------------------------------------- filtro de la pestaña Proveedores

function iniciarFiltroDeProveedores(pagina) {
    pagina.addEventListener('input', (event) => {
        const filtro = event.target.closest('[data-zona-proveedores-filtro]');

        if (!filtro) {
            return;
        }

        const seccion = filtro.closest('[data-fichas-tab-panel]');
        const texto = filtro.value.trim().toLowerCase();
        let visibles = 0;

        seccion.querySelectorAll('[data-zona-proveedor]').forEach((fila) => {
            const coincide = texto === '' || fila.dataset.buscarTexto.includes(texto);
            fila.hidden = !coincide;
            visibles += coincide ? 1 : 0;
        });

        const vacio = seccion.querySelector('[data-zona-proveedores-vacio]');

        if (vacio) {
            vacio.hidden = visibles > 0;
        }
    });
}

// ---------------------------------------------------------------- buscador

/** Buscador del modal "+ Asignar proveedor": elegir un resultado abre el modal de asignación. */
function iniciarBuscador(pagina, abrirAsignacion) {
    const buscador = document.querySelector('[data-buscador]');

    if (!buscador) {
        return;
    }

    const input = buscador.querySelector('input');
    const resultados = buscador.querySelector('[data-buscador-resultados]');
    const modalBuscador = buscador.closest('[data-modal]');
    const url = pagina.dataset.urlBuscar;
    let temporizador;
    let controlador;

    const cerrar = () => {
        resultados.classList.add('hidden');
        resultados.replaceChildren();
    };

    const mensaje = (texto) => {
        const p = document.createElement('p');
        p.className = 'px-4 py-3 text-sm text-text-muted';
        p.textContent = texto;
        resultados.replaceChildren(p);
        resultados.classList.remove('hidden');
    };

    const pintar = (proveedores) => {
        if (proveedores.length === 0) {
            mensaje('No encontramos proveedores con ese nombre o DNI.');
            return;
        }

        const items = proveedores.map((proveedor) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.setAttribute('role', 'option');
            boton.className = 'flex w-full flex-col gap-0.5 px-4 py-2.5 text-left text-sm hover:bg-text-muted/5 focus:bg-text-muted/5 focus:outline-none';

            const linea = document.createElement('span');
            linea.className = 'flex items-baseline gap-2';
            const nombre = document.createElement('strong');
            nombre.className = 'font-medium text-text';
            nombre.textContent = proveedor.nombre;
            const dni = document.createElement('span');
            dni.className = 'text-xs text-text-muted';
            dni.textContent = `DNI ${proveedor.dni}`;
            linea.append(nombre, dni);

            const estado = document.createElement('span');
            estado.className = proveedor.rutaId ? 'text-xs text-text-muted' : 'text-xs text-warning';
            estado.textContent = proveedor.rutaId
                ? `${proveedor.zonaNombre} · ${proveedor.rutaNombre}`
                : 'Sin zona ni ruta';

            boton.append(linea, estado);
            boton.addEventListener('click', () => {
                cerrar();
                input.value = '';
                modalBuscador?.querySelector('[data-modal-close]')?.click();
                abrirAsignacion(proveedor);
            });

            return boton;
        });

        resultados.replaceChildren(...items);
        resultados.classList.remove('hidden');
    };

    input.addEventListener('input', () => {
        window.clearTimeout(temporizador);
        controlador?.abort();

        const texto = input.value.trim();

        if (texto === '') {
            cerrar();
            return;
        }

        temporizador = window.setTimeout(async () => {
            controlador = new AbortController();

            try {
                const respuesta = await fetch(`${url}?q=${encodeURIComponent(texto)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controlador.signal,
                });

                if (!respuesta.ok) {
                    throw new Error('respuesta no válida');
                }

                pintar(await respuesta.json());
            } catch (error) {
                if (error.name !== 'AbortError') {
                    mensaje('No se pudo realizar la búsqueda. Inténtalo de nuevo.');
                }
            }
        }, 300);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            cerrar();
        }
    });

    // Al abrir el modal, el foco va directo al buscador.
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-open="modal-buscar-proveedor"]')) {
            window.requestAnimationFrame(() => input.focus());
        }
    });
}

// ------------------------------------------------------ modal de asignación

/**
 * Modal "Asignar proveedor a zona y ruta". Primero se ubica, después se asigna:
 *  - el mapa pinta SIEMPRE las zonas activas con la delimitación y el color del mapa general
 *    (el catálogo sale de ZonaMapService, igual que [data-zonas-mapa-general-datos]) y se encuadra
 *    para que se vean todas; sin delimitaciones, cae en el centro configurado con un aviso;
 *  - un click (o arrastrar el marcador, o escribir latitud/longitud) coloca un único marcador,
 *    rellena latitud/longitud y detecta la zona con point-in-polygon; la zona detectada queda
 *    elegida en el selector y sus rutas activas se cargan;
 *  - elegir otra zona en el selector la resalta y la encuadra (las demás siguen visibles);
 *  - Guardar solo se habilita con una ubicación dentro de la zona elegida. El servidor vuelve
 *    a verificarlo (GuardarAsignacionProveedor + LocalizadorZonas) antes de guardar.
 * Un proveedor sin coordenadas no tiene marcador hasta que se hace click: nunca se inventa uno.
 */
function iniciarModalAsignacion(pagina) {
    const modal = document.getElementById('modal-asignacion');
    const formulario = modal.querySelector('[data-asignacion-form]');
    const disparador = document.querySelector('[data-asignacion-disparador]');
    const selectZona = formulario.querySelector('#asignacion-zona');
    const selectRuta = formulario.querySelector('#asignacion-ruta');
    const rutaVacia = formulario.querySelector('[data-ruta-vacia]');
    const botonCrearRuta = formulario.querySelector('[data-crear-ruta-principal]');
    const errorCrearRuta = formulario.querySelector('[data-crear-ruta-error]');
    const inputLat = formulario.querySelector('#asignacion-latitud');
    const inputLng = formulario.querySelector('#asignacion-longitud');
    const inputReferencia = formulario.querySelector('#asignacion-referencia');
    const contenedorMapa = formulario.querySelector('[data-asignacion-mapa]');
    const ayudaMapa = formulario.querySelector('[data-mapa-ayuda]');
    const avisoMapa = formulario.querySelector('[data-mapa-aviso]');
    const zonaDetectadaEl = formulario.querySelector('[data-zona-detectada]');
    const asignacionActual = modal.querySelector('[data-asignacion-actual]');
    const errorGeneral = formulario.querySelector('[data-error-general]');
    const botonGuardar = formulario.querySelector('[data-asignacion-guardar]');

    const catalogo = JSON.parse(pagina.dataset.catalogo || '[]');
    const centroInicial = {
        lat: parseFloat(pagina.dataset.mapaLat),
        lng: parseFloat(pagina.dataset.mapaLng),
        zoom: parseInt(pagina.dataset.mapaZoom, 10) || 10,
    };
    const zonas = new Map(catalogo.map((zona) => [zona.id, {
        zona,
        vertices: verticesDeGeometria(zona.geometry, zona.nombre),
        poligono: null,
    }]));
    const hayDelimitaciones = [...zonas.values()].some((item) => item.vertices.length >= 3);
    const COLOR_SIN_ZONA = '#667085';
    const ZOOM_PROVEEDOR = 15;
    const AVISO_SIN_MAPA = 'No se pudo cargar el mapa. Verifica la configuración de Google Maps; mientras tanto puedes escribir la latitud y la longitud manualmente.';

    let mapaNoDisponible = false;
    let proveedorId = null;
    let mapa = null;
    let marcador = null;
    let mapsLib = null;
    let zonaDetectada = null;

    // --- selects dependientes -------------------------------------------

    const llenarZonas = (zonaId) => {
        selectZona.replaceChildren(new Option('Seleccionar zona', ''));
        catalogo.forEach((zona) => selectZona.append(new Option(zona.nombre, zona.id)));
        selectZona.value = zonaId && zonas.has(zonaId) ? String(zonaId) : '';
    };

    const llenarRutas = (rutaId) => {
        const zona = catalogo.find((z) => String(z.id) === selectZona.value);
        const rutas = zona ? zona.rutas : [];

        selectRuta.replaceChildren(new Option('Seleccionar ruta', ''));
        rutas.forEach((ruta) => selectRuta.append(new Option(ruta.nombre, ruta.id)));
        rutaVacia.hidden = !zona || rutas.length > 0;
        botonCrearRuta.hidden = !zona || rutas.length > 0;
        errorCrearRuta.hidden = true;

        if (rutaId && rutas.some((r) => r.id === rutaId)) {
            selectRuta.value = String(rutaId);
        } else if (rutas.length === 1) {
            selectRuta.value = String(rutas[0].id);
        }
    };

    /** Cambia la zona del selector (y sus rutas) solo si es otra: la ruta elegida se conserva. */
    const elegirZona = (zonaId) => {
        if (selectZona.value === String(zonaId ?? '')) {
            return;
        }

        selectZona.value = zonaId ? String(zonaId) : '';
        llenarRutas(null);
    };

    // Alta rápida de la "Ruta principal" de una zona que todavía no tiene
    // ninguna, sin salir del modal (ver RutaController::store, rama JSON).
    botonCrearRuta.addEventListener('click', async () => {
        const zonaId = selectZona.value;

        if (!zonaId) {
            return;
        }

        botonCrearRuta.disabled = true;
        errorCrearRuta.hidden = true;

        try {
            const respuesta = await fetch(pagina.dataset.urlNuevaRuta.replace('__ID__', zonaId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ nombre: 'Ruta principal', estado: 'ACTIVO' }),
            });

            const json = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                throw new Error(json.message || 'No se pudo crear la ruta.');
            }

            const zona = catalogo.find((z) => String(z.id) === zonaId);
            zona?.rutas.push(json.ruta);
            llenarRutas(json.ruta.id);
        } catch (error) {
            errorCrearRuta.textContent = error.message || 'No se pudo crear la ruta.';
            errorCrearRuta.hidden = false;
        } finally {
            botonCrearRuta.disabled = false;
        }
    });

    // --- errores ----------------------------------------------------------

    const limpiarErrores = () => {
        formulario.querySelectorAll('[data-error-for]').forEach((p) => {
            p.hidden = true;
            p.textContent = '';
        });
        formulario.querySelectorAll('[data-campo]').forEach((campo) => {
            campo.classList.remove('border-danger');
            campo.classList.add('border-text-muted/25');
        });
        errorGeneral.hidden = true;
        errorGeneral.textContent = '';
    };

    const mostrarErrores = (errores) => {
        Object.entries(errores).forEach(([campo, mensajes]) => {
            const parrafo = formulario.querySelector(`[data-error-for="${campo}"]`);
            const control = formulario.querySelector(`[data-campo="${campo}"]`);

            if (parrafo) {
                parrafo.textContent = mensajes[0];
                parrafo.hidden = false;
                control?.classList.add('border-danger');
                control?.classList.remove('border-text-muted/25');
            } else {
                errorGeneral.textContent = mensajes[0];
                errorGeneral.hidden = false;
            }
        });
    };

    // --- ubicación → zona -------------------------------------------------

    const coordenadasValidas = () => {
        const lat = parseFloat(inputLat.value);
        const lng = parseFloat(inputLng.value);

        return esCoordenadaValida(lat, lng) ? { lat, lng } : null;
    };

    const escribirCoordenadas = (lat, lng) => {
        inputLat.value = lat.toFixed(7);
        inputLng.value = lng.toFixed(7);
    };

    const avisar = (texto) => {
        avisoMapa.textContent = texto ?? '';
        avisoMapa.hidden = !texto;
    };

    /** Las demás zonas siguen visibles; la elegida se resalta y, si se pide, se encuadra. */
    const resaltarZona = (zonaId, { zoom = false } = {}) => {
        zonas.forEach((item, id) => resaltarDelimitacion(item.poligono, id === zonaId));

        const item = zonas.get(zonaId);

        if (zoom && mapa && item?.vertices.length >= 3) {
            encuadrar(mapa, limitesDeVertices(item.vertices), 32);
        }
    };

    const mostrarZonaDetectada = () => {
        const tono = (clase) => {
            zonaDetectadaEl.classList.remove('text-text-muted', 'text-text', 'text-warning');
            zonaDetectadaEl.classList.add(clase);
        };

        zonaDetectadaEl.replaceChildren();

        if (!coordenadasValidas()) {
            zonaDetectadaEl.textContent = 'Sin ubicación';
            tono('text-text-muted');
            return;
        }

        if (!zonaDetectada) {
            zonaDetectadaEl.textContent = 'Fuera de las zonas operativas';
            tono('text-warning');
            return;
        }

        const punto = document.createElement('span');
        punto.className = 'inline-block h-2.5 w-2.5 shrink-0 rounded-full';
        punto.style.backgroundColor = zonaDetectada.color;
        const nombre = document.createElement('strong');
        nombre.className = 'font-semibold';
        nombre.textContent = zonaDetectada.nombre;
        zonaDetectadaEl.append(punto, nombre);
        tono('text-text');
    };

    /**
     * Guardar exige una ubicación dentro de la zona elegida. Explica en el aviso del mapa por qué
     * no se puede guardar todavía.
     */
    const actualizarEstado = () => {
        const coordenadas = coordenadasValidas();
        const zonaElegida = selectZona.value ? zonas.get(parseInt(selectZona.value, 10))?.zona : null;

        mostrarZonaDetectada();
        ayudaMapa.textContent = coordenadas
            ? 'Puedes arrastrar el marcador para afinar la ubicación.'
            : 'Marca en el mapa la ubicación del proveedor.';

        if (!hayDelimitaciones) {
            avisar('No se pudieron cargar las delimitaciones de las zonas.');
        } else if (coordenadas && !zonaDetectada) {
            avisar('La ubicación seleccionada no pertenece a ninguna zona operativa.');
        } else if (coordenadas && zonaElegida && zonaElegida.id !== zonaDetectada.id) {
            avisar(`El marcador está en ${zonaDetectada.nombre}. Muévelo a ${zonaElegida.nombre} o vuelve a elegir ${zonaDetectada.nombre}.`);
        } else {
            avisar(mapaNoDisponible ? AVISO_SIN_MAPA : null);
        }

        botonGuardar.disabled = !(coordenadas && zonaDetectada && zonaElegida?.id === zonaDetectada.id);
    };

    const colorDelMarcador = () => zonaDetectada?.color ?? COLOR_SIN_ZONA;

    const colocarMarcador = (posicion) => {
        if (!mapa) {
            return;
        }

        if (marcador) {
            marcador.position = posicion;
            marcador.content = crearPinProveedor(colorDelMarcador(), !zonaDetectada);
            return;
        }

        marcador = new mapsLib.AdvancedMarkerElement({
            map: mapa,
            position: posicion,
            content: crearPinProveedor(colorDelMarcador(), !zonaDetectada),
            gmpDraggable: true,
            title: 'Ubicación del proveedor (arrástralo para afinarla)',
            zIndex: 30,
        });

        marcador.addListener('dragend', () => {
            const { lat, lng } = marcador.position;
            ubicar(typeof lat === 'function' ? lat() : lat, typeof lng === 'function' ? lng() : lng);
        });
    };

    const quitarMarcador = () => {
        if (marcador) {
            marcador.map = null;
            marcador = null;
        }
    };

    /**
     * Nueva ubicación (click, arrastre o coordenadas escritas): detecta la zona, la elige en el
     * selector (con sus rutas) y la resalta, sin cambiar el encuadre del mapa.
     */
    const ubicar = (lat, lng, { escribir = true } = {}) => {
        if (escribir) {
            escribirCoordenadas(lat, lng);
        }

        const preferida = selectZona.value ? parseInt(selectZona.value, 10) : null;
        zonaDetectada = zonaQueContiene(zonas.values(), lat, lng, preferida);

        if (zonaDetectada) {
            elegirZona(zonaDetectada.id);
        }

        resaltarZona(zonaDetectada?.id ?? null);
        colocarMarcador({ lat, lng });
        actualizarEstado();
    };

    const alEditarCoordenadas = () => {
        const coordenadas = coordenadasValidas();

        if (coordenadas) {
            ubicar(coordenadas.lat, coordenadas.lng, { escribir: false });
            mapa?.panTo(coordenadas);
            return;
        }

        zonaDetectada = null;
        quitarMarcador();
        actualizarEstado();
    };

    inputLat.addEventListener('change', alEditarCoordenadas);
    inputLng.addEventListener('change', alEditarCoordenadas);

    selectZona.addEventListener('change', () => {
        llenarRutas(null);
        resaltarZona(selectZona.value ? parseInt(selectZona.value, 10) : null, { zoom: true });
        actualizarEstado();
    });

    // --- mapa -------------------------------------------------------------

    // Sin Google Maps la zona se sigue detectando con las coordenadas escritas a mano.
    const sinMapa = () => {
        mapaNoDisponible = true;
        contenedorMapa.classList.add('hidden');
        actualizarEstado();
    };

    alFallarGoogleMaps(sinMapa);

    /** Todas las delimitaciones a la vista; sin ninguna, el centro configurado. */
    const encuadrarZonas = () => {
        const limites = new google.maps.LatLngBounds();

        zonas.forEach((item) => {
            if (item.vertices.length >= 3) {
                limites.union(limitesDeVertices(item.vertices));
            }
        });

        if (limites.isEmpty()) {
            mapa.setCenter({ lat: centroInicial.lat, lng: centroInicial.lng });
            mapa.setZoom(centroInicial.zoom);
            return;
        }

        encuadrar(mapa, limites, 24);
    };

    const crearMapa = async () => {
        mapsLib = await cargarGoogleMaps();

        if (!mapsLib) {
            return false;
        }

        mapa = new mapsLib.Map(contenedorMapa, {
            center: { lat: centroInicial.lat, lng: centroInicial.lng },
            zoom: centroInicial.zoom,
            mapId: getMapId(),
            streetViewControl: false,
            mapTypeControl: true,
            clickableIcons: false,
        });

        // Polígonos no clicables: el click llega al mapa con su latLng exacto, dentro o fuera de una zona.
        zonas.forEach((item) => {
            if (item.vertices.length >= 3) {
                item.poligono = dibujarDelimitacion(mapa, item.zona, { clickable: false });
            }
        });

        mapa.addListener('click', (evento) => ubicar(evento.latLng.lat(), evento.latLng.lng()));

        return true;
    };

    const prepararMapa = async () => {
        if (window.__ecolactMapsFallo || (!mapa && !(await crearMapa()))) {
            sinMapa();
            return;
        }

        contenedorMapa.classList.remove('hidden');

        const coordenadas = coordenadasValidas();
        resaltarZona(zonaDetectada?.id ?? (selectZona.value ? parseInt(selectZona.value, 10) : null));

        if (coordenadas) {
            colocarMarcador(coordenadas);
            mapa.setCenter(coordenadas);
            mapa.setZoom(ZOOM_PROVEEDOR);
        } else {
            quitarMarcador();
            encuadrarZonas();
        }
    };

    // --- apertura ---------------------------------------------------------

    const textoAsignacionActual = (datos) => {
        if (datos.rutaId) {
            return `Asignación actual: ${datos.zonaNombre} · ${datos.rutaNombre}`;
        }

        return datos.zonaRegistradaNombre ? `Zona registrada: ${datos.zonaRegistradaNombre} (todavía sin ruta)` : 'Todavía sin zona ni ruta.';
    };

    const abrir = (datos) => {
        proveedorId = datos.proveedorId;
        limpiarErrores();

        modal.querySelector('[data-asignacion-nombre]').textContent = datos.nombre;
        modal.querySelector('[data-asignacion-dni]').textContent = datos.dni;
        asignacionActual.textContent = textoAsignacionActual(datos);
        asignacionActual.hidden = false;

        // Sin ruta, se parte de la zona registrada del proveedor o de la zona que se está viendo;
        // el click en el mapa la corrige si el proveedor está en otra.
        llenarZonas(datos.zonaId ?? datos.zonaRegistradaId ?? zonaDelPanel(pagina));
        llenarRutas(datos.rutaId);

        inputLat.value = datos.latitud ?? '';
        inputLng.value = datos.longitud ?? '';
        inputReferencia.value = datos.referencia ?? '';

        const coordenadas = coordenadasValidas();
        zonaDetectada = coordenadas ? zonaQueContiene(zonas.values(), coordenadas.lat, coordenadas.lng, datos.zonaId) : null;
        actualizarEstado();

        disparador.click();

        // El mapa se crea/ajusta con el modal ya visible para que tenga tamaño.
        window.requestAnimationFrame(() => prepararMapa());
    };

    // --- envío ------------------------------------------------------------

    formulario.addEventListener('submit', async (event) => {
        event.preventDefault();
        limpiarErrores();

        if (botonGuardar.disabled) {
            return;
        }

        botonGuardar.disabled = true;

        const cuerpo = new FormData(formulario);
        cuerpo.append('_method', 'PUT');

        try {
            const respuesta = await fetch(pagina.dataset.urlAsignacion.replace('__ID__', proveedorId), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: cuerpo,
            });

            const json = await respuesta.json().catch(() => ({}));

            if (respuesta.ok && json.redirect) {
                // Vuelve al mapa general con la zona destino y el pin del proveedor enfocado.
                window.location.assign(json.redirect);
                return;
            }

            if (respuesta.status === 422 && json.errors) {
                mostrarErrores(json.errors);
            } else {
                errorGeneral.textContent = json.message || 'No se pudo guardar la asignación. Inténtalo de nuevo.';
                errorGeneral.hidden = false;
            }
        } catch {
            errorGeneral.textContent = 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.';
            errorGeneral.hidden = false;
        }

        actualizarEstado();
    });

    return { abrir };
}
