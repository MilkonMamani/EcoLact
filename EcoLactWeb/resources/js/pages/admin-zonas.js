import { cargarGoogleMaps, getMapId, getCsrfToken } from '../maps.js';
import { crearPinProveedor, dibujarDelimitacion, encuadrar, iniciarEditorDelimitacion, iniciarMapaGeneral, verticesDeGeometria } from './zonas-mapa.js';

/**
 * Pantalla /admin/zonas. La selección de fila, el panel por fetch, las pestañas y el skeleton
 * son los de admin-fichas.js (igual que Proveedores y Acopiadores) y el switch de estado es
 * admin-estado-switch.js. Aquí solo va lo propio de Zonas:
 *  - mapa general de zonas (zonas-mapa.js), sincronizado con la tabla y el panel;
 *  - editor de delimitación de "Editar zona" (zonas-mapa.js);
 *  - mini mapa de Google Maps en el Resumen con SOLO la zona seleccionada (su delimitación y
 *    sus proveedores ubicados), y el mismo mapa ampliado en "Ver mapa completo";
 *  - filtro local de la pestaña Proveedores;
 *  - "+ Asignar proveedor": buscador de proveedores (nombre o DNI) que abre el modal de asignación;
 *  - modal "Asignar proveedor a zona y ruta" con selector dependiente Zona → Ruta y mapa
 *    para fijar latitud/longitud.
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
    const mapaZona = iniciarMapaDeZona(pagina);
    const asignacion = iniciarModalAsignacion(pagina);

    iniciarEditorDelimitacion(pagina);
    iniciarBuscador(pagina, asignacion.abrir);
    iniciarFiltroDeProveedores(pagina);
    mapaZona.cargar();

    // El panel se reemplaza al elegir otra zona (desde la tabla o desde el mapa): se vuelve a
    // pintar su mini mapa y el mapa general resalta esa zona y se acerca a ella.
    document.addEventListener('fichas:panel-actualizado', () => {
        const zonaId = zonaDelPanel(pagina);

        mapaZona.cargar();

        if (zonaId !== mapaGeneral.seleccionada?.()) {
            mapaGeneral.resaltar(zonaId, { zoom: true });
        }
    });

    // Un mapa creado con su pestaña oculta queda sin tamaño: se rehace al volver al Resumen.
    pagina.addEventListener('click', (event) => {
        if (event.target.closest('[data-fichas-tab="resumen"]')) {
            window.requestAnimationFrame(() => mapaZona.cargar());
        }

        if (event.target.closest('[data-zona-mapa-ampliar]')) {
            window.requestAnimationFrame(() => mapaZona.cargarCompleto());
        }

        // Lápiz de cada fila de proveedor (delegado, porque el panel se reemplaza).
        const boton = event.target.closest('[data-asignar-abrir]');

        if (boton) {
            asignacion.abrir(JSON.parse(boton.dataset.asignarAbrir));
        }
    });

    // Enlace profundo desde el perfil del proveedor: ?asignar=<DNI> abre el mismo
    // modal de asignación (zona, ruta, mapa) ya cargado con los datos del proveedor.
    const dniAsignar = new URLSearchParams(window.location.search).get('asignar');

    if (dniAsignar) {
        abrirAsignacionPorDni(pagina, dniAsignar, asignacion.abrir);
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

/** Zona que se está viendo en el panel (null en "Proveedores sin zona"). */
function zonaDelPanel(pagina) {
    const id = pagina.querySelector('[data-fichas-panel] [data-zona-id]')?.dataset.zonaId;

    return id ? parseInt(id, 10) : null;
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

// --------------------------------------------------- mapa de la zona

/**
 * Mini mapa del Resumen: SOLO la zona del panel, con su delimitación (si ya se dibujó) y los
 * puntos de sus proveedores ubicados, del color de la zona. El lienzo llega dentro del fragmento
 * del panel, así que el mapa se vuelve a crear sobre el lienzo nuevo en cada cargar(); "Ver mapa
 * completo" pinta lo mismo en el lienzo del modal.
 */
function iniciarMapaDeZona(pagina) {
    const zoomInicial = parseInt(pagina.dataset.mapaZoom, 10) || 10;
    let mapsLib = null;

    const datos = () => {
        const datosEl = pagina.querySelector('[data-fichas-panel] [data-zona-mapa-datos]');

        return datosEl ? JSON.parse(datosEl.textContent) : null;
    };

    const pintar = async (lienzo, contenido, aviso = null) => {
        if (!lienzo || !contenido || lienzo.offsetParent === null) {
            return; // Sin datos, o el lienzo está oculto (otra pestaña): se pinta al mostrarse.
        }

        const { zona, proveedores } = contenido;

        if (proveedores.length === 0 && !zona.geometry) {
            return;
        }

        mapsLib ??= await cargarGoogleMaps();

        if (!mapsLib) {
            if (aviso) {
                aviso.hidden = false;
                aviso.textContent = 'No se pudo cargar Google Maps. Usa "Registrada" en la pestaña Proveedores para abrir cada punto en Google Maps.';
            }
            lienzo.classList.add('hidden');
            return;
        }

        const vertices = verticesDeGeometria(zona.geometry);
        const inicio = proveedores[0] ?? vertices[0];
        const mapa = new mapsLib.Map(lienzo, {
            center: { lat: inicio.lat, lng: inicio.lng },
            zoom: zoomInicial,
            mapId: getMapId(),
            streetViewControl: false,
            mapTypeControl: false,
            clickableIcons: false,
        });

        const bounds = new google.maps.LatLngBounds();

        if (zona.geometry) {
            dibujarDelimitacion(mapa, zona, { clickable: false });
            vertices.forEach((vertice) => bounds.extend(vertice));
        }

        proveedores.forEach((proveedor) => {
            new mapsLib.AdvancedMarkerElement({
                map: mapa,
                position: { lat: proveedor.lat, lng: proveedor.lng },
                content: crearPinProveedor(zona.color, proveedor.fueraDeDelimitacion),
                title: proveedor.fueraDeDelimitacion ? `${proveedor.nombre} · fuera de la delimitación` : proveedor.nombre,
            });
            bounds.extend({ lat: proveedor.lat, lng: proveedor.lng });
        });

        encuadrar(mapa, bounds, 24);
    };

    const cargar = () => pintar(
        pagina.querySelector('[data-fichas-panel] [data-zona-mapa-lienzo]'),
        datos(),
        pagina.querySelector('[data-fichas-panel] [data-zona-mapa-aviso]'),
    );

    const cargarCompleto = () => pintar(document.querySelector('[data-zona-mapa-completo]'), datos());

    return { cargar, cargarCompleto };
}

// ------------------------------------------------------ modal de asignación

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
    const avisoMapa = formulario.querySelector('[data-mapa-aviso]');
    const errorGeneral = formulario.querySelector('[data-error-general]');
    const botonGuardar = formulario.querySelector('[data-asignacion-guardar]');

    const catalogo = JSON.parse(pagina.dataset.catalogo || '[]');
    const centroInicial = {
        lat: parseFloat(pagina.dataset.mapaLat),
        lng: parseFloat(pagina.dataset.mapaLng),
        zoom: parseInt(pagina.dataset.mapaZoom, 10) || 10,
    };

    let proveedorId = null;
    let mapa = null;
    let marcador = null;
    let mapsLib = null;

    // --- selects dependientes -------------------------------------------

    const llenarZonas = (zonaId) => {
        selectZona.replaceChildren(new Option('Seleccionar zona', ''));
        catalogo.forEach((zona) => selectZona.append(new Option(zona.nombre, zona.id)));
        selectZona.value = zonaId ? String(zonaId) : '';
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

    selectZona.addEventListener('change', () => llenarRutas(null));

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

    // --- mapa -------------------------------------------------------------

    const coordenadasValidas = () => {
        const lat = parseFloat(inputLat.value);
        const lng = parseFloat(inputLng.value);

        if (Number.isNaN(lat) || Number.isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return null;
        }

        return { lat, lng };
    };

    const escribirCoordenadas = (lat, lng) => {
        inputLat.value = lat.toFixed(7);
        inputLng.value = lng.toFixed(7);
    };

    const colocarMarcador = (posicion) => {
        if (!mapa) {
            return;
        }

        if (marcador) {
            marcador.position = posicion;
            return;
        }

        marcador = new mapsLib.AdvancedMarkerElement({
            map: mapa,
            position: posicion,
            gmpDraggable: true,
            title: 'Ubicación del proveedor',
        });

        marcador.addListener('dragend', () => {
            const { lat, lng } = marcador.position;
            escribirCoordenadas(typeof lat === 'function' ? lat() : lat, typeof lng === 'function' ? lng() : lng);
        });
    };

    const quitarMarcador = () => {
        if (marcador) {
            marcador.map = null;
            marcador = null;
        }
    };

    const sincronizarMapaConInputs = () => {
        const coordenadas = coordenadasValidas();

        if (!mapa) {
            return;
        }

        if (coordenadas) {
            colocarMarcador(coordenadas);
            mapa.panTo(coordenadas);
        } else {
            quitarMarcador();
        }
    };

    inputLat.addEventListener('change', sincronizarMapaConInputs);
    inputLng.addEventListener('change', sincronizarMapaConInputs);

    const prepararMapa = async () => {
        avisoMapa.hidden = true;

        if (!mapa) {
            mapsLib = await cargarGoogleMaps();

            if (!mapsLib) {
                avisoMapa.hidden = false;
                avisoMapa.textContent = 'No se pudo cargar Google Maps (falta configurar GOOGLE_MAPS_API_KEY o hubo un problema de red). Puedes escribir la latitud y la longitud manualmente.';
                contenedorMapa.classList.add('hidden');
                return;
            }

            mapa = new mapsLib.Map(contenedorMapa, {
                center: { lat: centroInicial.lat, lng: centroInicial.lng },
                zoom: centroInicial.zoom,
                mapId: getMapId(),
                streetViewControl: false,
                mapTypeControl: true,
            });

            mapa.addListener('click', (evento) => {
                const lat = evento.latLng.lat();
                const lng = evento.latLng.lng();
                colocarMarcador({ lat, lng });
                escribirCoordenadas(lat, lng);
            });
        }

        contenedorMapa.classList.remove('hidden');

        const coordenadas = coordenadasValidas();

        if (coordenadas) {
            colocarMarcador(coordenadas);
            mapa.setCenter(coordenadas);
            mapa.setZoom(16);
        } else {
            quitarMarcador();
            mapa.setCenter({ lat: centroInicial.lat, lng: centroInicial.lng });
            mapa.setZoom(centroInicial.zoom);
        }
    };

    // --- apertura ---------------------------------------------------------

    const abrir = (datos) => {
        proveedorId = datos.proveedorId;
        limpiarErrores();

        modal.querySelector('[data-asignacion-nombre]').textContent = datos.nombre;
        modal.querySelector('[data-asignacion-dni]').textContent = datos.dni;

        // Un proveedor sin zona toma por defecto la zona que se está viendo.
        const zonaInicial = datos.zonaId ?? zonaDelPanel(pagina);

        llenarZonas(zonaInicial);
        llenarRutas(datos.rutaId);

        inputLat.value = datos.latitud ?? '';
        inputLng.value = datos.longitud ?? '';
        inputReferencia.value = datos.referencia ?? '';

        disparador.click();
        selectZona.focus();

        // El mapa se crea/ajusta con el modal ya visible para que tenga tamaño.
        window.requestAnimationFrame(() => prepararMapa());
    };

    // --- envío ------------------------------------------------------------

    formulario.addEventListener('submit', async (event) => {
        event.preventDefault();
        limpiarErrores();
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

        botonGuardar.disabled = false;
    });

    return { abrir };
}
