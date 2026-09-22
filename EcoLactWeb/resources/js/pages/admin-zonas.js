import { cargarGoogleMaps, getMapId, getCsrfToken } from '../maps.js';

/**
 * Pantalla /admin/zonas (maestro-detalle):
 *  - buscador de proveedores con debounce que abre el modal de asignación;
 *  - selección de zona sin recargar la página (se reemplaza el fragmento
 *    HTML que ya renderiza el servidor: tabla de proveedores + mapa);
 *  - mapa de Google Maps con los proveedores ubicados de la zona
 *    seleccionada, bajo el listado de zonas;
 *  - modal "Asignar proveedor a zona y ruta" con selector dependiente
 *    Zona → Ruta y mapa de Google Maps para fijar latitud/longitud.
 *
 * Google Maps se carga una sola vez por página (cargarGoogleMaps memoiza la
 * promesa) y solo cuando hace falta (mapa de la zona o modal de asignación).
 */
const pagina = document.querySelector('[data-zonas-page]');

if (pagina) {
    iniciarZonasPage(pagina);
}

function iniciarZonasPage(pagina) {
    const contenedorPanel = pagina.querySelector('[data-zona-panel]');
    const aviso = pagina.querySelector('[data-zonas-aviso]');

    const mostrarAviso = (mensaje) => {
        aviso.textContent = mensaje;
        aviso.hidden = false;
    };

    const ocultarAviso = () => {
        aviso.hidden = true;
    };

    const mapaZona = iniciarMapaDeZona(pagina);
    const asignacion = iniciarModalAsignacion(pagina);

    iniciarBuscador(pagina, asignacion.abrir);
    iniciarSeleccionDeZona(pagina, contenedorPanel, mapaZona, mostrarAviso, ocultarAviso);
    iniciarEnfoqueDeUbicacion(pagina, mapaZona);
    mapaZona.cargar();

    // Abrir el modal de asignación desde el lápiz de cada fila (delegado, porque
    // el panel se reemplaza al cambiar de zona).
    pagina.addEventListener('click', (event) => {
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
    window.history.replaceState({}, '', url);

    try {
        const respuesta = await fetch(`${pagina.dataset.urlBuscar}?q=${encodeURIComponent(dni)}`, { headers: { Accept: 'application/json' } });
        const proveedor = (await respuesta.json()).find((p) => p.dni === dni);

        if (proveedor) {
            abrirAsignacion(proveedor);
        }
    } catch {
        // Sin el modal el administrador aún puede usar el buscador de la pantalla.
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

// ---------------------------------------------------------------- buscador

function iniciarBuscador(pagina, abrirAsignacion) {
    const buscador = pagina.querySelector('[data-buscador]');
    const input = buscador.querySelector('input');
    const resultados = buscador.querySelector('[data-buscador-resultados]');
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

    document.addEventListener('click', (event) => {
        if (!buscador.contains(event.target)) {
            cerrar();
        }
    });
}

// ------------------------------------------------------ selección de zona

function iniciarSeleccionDeZona(pagina, contenedorPanel, mapaZona, mostrarAviso, ocultarAviso) {
    const enlaces = () => [...pagina.querySelectorAll('[data-zona-link]')];

    const marcarSeleccion = (clave) => {
        enlaces().forEach((enlace) => {
            const activa = enlace.dataset.zonaKey === String(clave);
            enlace.dataset.selected = activa ? 'true' : 'false';

            if (activa) {
                enlace.setAttribute('aria-current', 'page');
            } else {
                enlace.removeAttribute('aria-current');
            }
        });
    };

    pagina.addEventListener('click', async (event) => {
        const enlace = event.target.closest('[data-zona-link]');

        if (!enlace || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
            return;
        }

        event.preventDefault();
        ocultarAviso();

        try {
            await cargarPanel(contenedorPanel, enlace.dataset.panelUrl);
            marcarSeleccion(enlace.dataset.zonaKey);
            window.history.pushState({ zona: enlace.dataset.zonaKey }, '', enlace.href);
            mapaZona.cargar();
        } catch {
            mostrarAviso('No se pudo cargar la zona. Inténtalo de nuevo.');
        }
    });

    window.addEventListener('popstate', () => window.location.reload());
}

async function cargarPanel(contenedorPanel, url) {
    const respuesta = await fetch(url, { headers: { Accept: 'text/html' } });

    if (!respuesta.ok) {
        throw new Error('No se pudo cargar el panel.');
    }

    contenedorPanel.innerHTML = await respuesta.text();
}

// --------------------------------------------------- mapa de la zona

/**
 * Mapa con los proveedores ubicados de la zona (o "sin asignar") que se
 * está viendo. El lienzo [data-zona-mapa-lienzo] se reemplaza por completo
 * cada vez que se cambia de zona (viene dentro del fragmento AJAX), así que
 * el mapa de Google se vuelve a crear sobre el lienzo nuevo en cada
 * cargar(); es más simple que sincronizar marcadores contra un mapa vivo y
 * el costo de recrearlo es insignificante para esta pantalla.
 */
function iniciarMapaDeZona(pagina) {
    const zoomInicial = parseInt(pagina.dataset.mapaZoom, 10) || 10;

    let mapsLib = null;
    let mapa = null;
    let marcadores = new Map();

    const cargar = async () => {
        const lienzo = pagina.querySelector('[data-zona-mapa-lienzo]');

        mapa = null;
        marcadores = new Map();

        if (!lienzo) {
            return; // Estado vacío: la zona no tiene proveedores con ubicación.
        }

        const aviso = pagina.querySelector('[data-zona-mapa-aviso]');
        const datosEl = pagina.querySelector('[data-zona-mapa-datos]');
        const proveedores = datosEl ? JSON.parse(datosEl.textContent) : [];

        if (proveedores.length === 0) {
            return;
        }

        mapsLib ??= await cargarGoogleMaps();

        if (!mapsLib) {
            aviso.hidden = false;
            aviso.textContent = 'No se pudo cargar Google Maps. Usa "Ver ubicación" en cada fila para abrir el punto en Google Maps.';
            lienzo.classList.add('hidden');
            return;
        }

        mapa = new mapsLib.Map(lienzo, {
            center: { lat: proveedores[0].lat, lng: proveedores[0].lng },
            zoom: zoomInicial,
            mapId: getMapId(),
            streetViewControl: false,
        });

        const bounds = new google.maps.LatLngBounds();

        proveedores.forEach((proveedor) => {
            const marcador = new mapsLib.AdvancedMarkerElement({
                map: mapa,
                position: { lat: proveedor.lat, lng: proveedor.lng },
                title: proveedor.nombre,
            });

            marcadores.set(proveedor.id, marcador);
            bounds.extend({ lat: proveedor.lat, lng: proveedor.lng });
        });

        if (proveedores.length > 1) {
            mapa.fitBounds(bounds);
        }
    };

    const enfocar = (proveedorId) => {
        const marcador = marcadores.get(proveedorId);

        if (!mapa || !marcador) {
            return false;
        }

        mapa.panTo(marcador.position);
        mapa.setZoom(16);
        resaltar(marcador);
        pagina.querySelector('[data-zona-mapa-lienzo]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        return true;
    };

    const resaltar = (marcador) => {
        const contenido = marcador.content;

        if (!contenido) {
            return;
        }

        contenido.animate?.(
            [{ transform: 'scale(1)' }, { transform: 'scale(1.4)' }, { transform: 'scale(1)' }],
            { duration: 500, easing: 'ease-in-out' },
        );
    };

    return { cargar, enfocar };
}

/**
 * El enlace "Ver ubicación" de la tabla enfoca el marcador correspondiente
 * en el mapa de la zona en vez de salir de /admin/zonas. Si el mapa no está
 * disponible (Google Maps no cargó, o el proveedor todavía no aparece en el
 * mapa por cualquier motivo) se conserva el comportamiento por defecto del
 * enlace: abrir la ubicación en Google Maps en una pestaña nueva.
 */
function iniciarEnfoqueDeUbicacion(pagina, mapaZona) {
    pagina.addEventListener('click', (event) => {
        const enlace = event.target.closest('[data-ver-ubicacion]');

        if (!enlace) {
            return;
        }

        const proveedorId = Number(enlace.dataset.verUbicacion);

        if (mapaZona.enfocar(proveedorId)) {
            event.preventDefault();
        }
    });
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
        const zonaSeleccionada = pagina.querySelector('[data-zona-link][data-selected="true"]')?.dataset.zonaKey;
        const zonaInicial = datos.zonaId ?? (zonaSeleccionada && zonaSeleccionada !== 'sin-asignar' ? parseInt(zonaSeleccionada, 10) : null);

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
