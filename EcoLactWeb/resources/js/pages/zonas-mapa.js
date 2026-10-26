import { alFallarGoogleMaps, cargarGoogleMaps, esCoordenadaValida, getMapId } from '../maps.js';

/**
 * Piezas de mapa de la pantalla /admin/zonas, compartidas por sus dos mapas:
 *  - mapa general (el centro de la pantalla): todas las zonas con su delimitación, una
 *    etiqueta "nombre + N proveedores" y un pin por proveedor con coordenadas, que se puede
 *    seleccionar y, solo en "Mover ubicación", arrastrar;
 *  - editor de delimitación de "Editar zona": dibujar/ajustar el polígono y guardarlo como GeoJSON.
 *
 * La geometría llega del backend como GeoJSON Polygon ([longitud, latitud], anillo cerrado).
 * Estas delimitaciones son operativas y referenciales, no límites oficiales.
 */

const ZOOM_MAXIMO_AL_ENCUADRAR = 16;

/**
 * Relleno semitransparente (se siguen viendo calles, poblados y el lago) y borde fuerte del
 * mismo color. La zona seleccionada engrosa el borde y sube un poco el relleno; las demás
 * conservan su estilo normal para no perder el contexto. "referencia" es para el editor.
 */
const ESTILOS = {
    normal: { fillOpacity: 0.22, strokeOpacity: 0.95, strokeWeight: 3 },
    activa: { fillOpacity: 0.3, strokeOpacity: 1, strokeWeight: 4 },
    referencia: { fillOpacity: 0.1, strokeOpacity: 0.6, strokeWeight: 2 },
};

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Vértices {lat, lng} del anillo exterior, sin repetir el primero al final. GeoJSON guarda
 * [longitud, latitud]; Google Maps usa {lat, lng}. Una geometría nula devuelve [] y una que no
 * sea un Polygon válido (anillo de al menos 4 puntos con el cierre, coordenadas en rango)
 * también, con un aviso en consola que nombra la zona: nunca rompe el resto del mapa.
 */
export function verticesDeGeometria(geometry, nombre = 'sin nombre') {
    if (!geometry) {
        return [];
    }

    const anillo = geometry.type === 'Polygon' && Array.isArray(geometry.coordinates) ? geometry.coordinates[0] : null;
    const vertices = Array.isArray(anillo)
        ? anillo.map((punto) => (Array.isArray(punto) ? { lat: Number(punto[1]), lng: Number(punto[0]) } : null))
        : [];

    if (!Array.isArray(anillo) || anillo.length < 4 || vertices.some((v) => !v || !esCoordenadaValida(v.lat, v.lng))) {
        console.warn(`[EcoLact] La delimitación de la zona "${nombre}" no es un GeoJSON Polygon válido; no se dibuja.`, geometry);
        return [];
    }

    const [primero, ultimo] = [vertices[0], vertices[vertices.length - 1]];

    if (primero.lat === ultimo.lat && primero.lng === ultimo.lng) {
        vertices.pop();
    }

    return vertices;
}

export function dibujarDelimitacion(mapa, zona, { clickable = true, estilo = 'normal' } = {}) {
    return new google.maps.Polygon({
        map: mapa,
        paths: verticesDeGeometria(zona.geometry, zona.nombre),
        strokeColor: zona.color,
        fillColor: zona.color,
        clickable,
        zIndex: 1,
        ...ESTILOS[estilo],
    });
}

/** Oscurece un color #rrggbb (fondo de la etiqueta: texto blanco legible incluso sobre mostaza). */
export function oscurecer(color, proporcion = 0.22) {
    const canales = [1, 3, 5].map((i) => Math.round(parseInt(color.slice(i, i + 2), 16) * (1 - proporcion)));

    return `#${canales.map((canal) => canal.toString(16).padStart(2, '0')).join('')}`;
}

/**
 * Pin clásico de mapa (lágrima invertida, 30×39 px) del color de la zona, con borde blanco y
 * sombra para separarlo del polígono. Es el contenido de un AdvancedMarkerElement, que lo ancla
 * por su punta inferior sobre la coordenada real del proveedor. Un proveedor cuya ubicación cae
 * fuera de la delimitación de su zona lleva el centro naranja.
 */
export function crearPinProveedor(color, fueraDeDelimitacion = false) {
    const pin = document.createElement('span');
    pin.className = fueraDeDelimitacion ? 'zonas-pin is-fuera' : 'zonas-pin';

    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('width', '30');
    svg.setAttribute('height', '39');
    svg.setAttribute('viewBox', '0 0 26 34');
    svg.setAttribute('aria-hidden', 'true');

    const gota = document.createElementNS(SVG_NS, 'path');
    gota.setAttribute('d', 'M13 1.5C6.6 1.5 1.5 6.6 1.5 12.9c0 8.3 9.9 18.4 10.8 19.3a1 1 0 0 0 1.4 0c.9-.9 10.8-11 10.8-19.3C24.5 6.6 19.4 1.5 13 1.5z');
    gota.setAttribute('fill', color);
    gota.setAttribute('stroke', '#fff');
    gota.setAttribute('stroke-width', '2');

    const centro = document.createElementNS(SVG_NS, 'circle');
    centro.setAttribute('cx', '13');
    centro.setAttribute('cy', '12.8');
    centro.setAttribute('r', '4.3');
    centro.setAttribute('fill', fueraDeDelimitacion ? '#f79009' : '#fff');

    svg.append(gota, centro);
    pin.append(svg);

    return pin;
}

/** fitBounds sin acercarse de más cuando hay un solo punto o una zona muy pequeña. */
export function encuadrar(mapa, limites, relleno = 40) {
    if (!limites || limites.isEmpty()) {
        return;
    }

    mapa.fitBounds(limites, relleno);
    google.maps.event.addListenerOnce(mapa, 'idle', () => {
        if (mapa.getZoom() > ZOOM_MAXIMO_AL_ENCUADRAR) {
            mapa.setZoom(ZOOM_MAXIMO_AL_ENCUADRAR);
        }
    });
}

function limitesDeVertices(vertices) {
    const limites = new google.maps.LatLngBounds();
    vertices.forEach((vertice) => limites.extend(vertice));

    return limites;
}

/** Huata (configurable): punto de partida mientras no haya polígonos ni pines que encuadrar. */
function centroInicial(pagina) {
    return {
        center: { lat: parseFloat(pagina.dataset.zonasLat), lng: parseFloat(pagina.dataset.zonasLng) },
        zoom: parseInt(pagina.dataset.zonasZoom, 10) || 12,
    };
}

function etiquetaProveedores(cantidad) {
    return `${cantidad} ${cantidad === 1 ? 'proveedor' : 'proveedores'}`;
}

/** Datos del mapa general que el servidor dejó en la página (vacío si no hay zonas). */
export function datosMapaGeneral(pagina) {
    const datos = pagina.querySelector('[data-zonas-mapa-general-datos]');

    return datos ? JSON.parse(datos.textContent) : { zonas: [], proveedores: [] };
}

// ---------------------------------------------------------------- mapa general

/** Zoom al enfocar un proveedor elegido en el buscador: se ve su entorno y la zona alrededor. */
const ZOOM_PROVEEDOR = 15;

/**
 * Point-in-polygon por cruce de rayos (x = longitud, y = latitud): el mismo algoritmo que
 * DelimitacionZona::contiene() en el servidor, que es quien decide al guardar.
 */
export function contienePunto(vertices, lat, lng) {
    let dentro = false;

    for (let i = 0, j = vertices.length - 1; i < vertices.length; j = i++) {
        const { lat: yi, lng: xi } = vertices[i];
        const { lat: yj, lng: xj } = vertices[j];

        if ((yi > lat) !== (yj > lat) && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) {
            dentro = !dentro;
        }
    }

    return dentro;
}

/** {lat, lng} numéricos de un AdvancedMarkerElement (su position puede ser LatLng o literal). */
function posicionDe(marcador) {
    const { lat, lng } = marcador.position;

    return { lat: typeof lat === 'function' ? lat() : lat, lng: typeof lng === 'function' ? lng() : lng };
}

/**
 * Mapa general de Zonas.
 *
 * Zonas: click en un polígono, en su etiqueta o en la leyenda abre esa zona en el panel (evento
 * fichas:seleccionar de admin-fichas.js) y el mapa se acerca a ella; admin-zonas.js llama a
 * resaltar() cuando el panel muestra otra zona.
 *
 * Proveedores: un único AdvancedMarkerElement por proveedor, NUNCA arrastrable por defecto.
 * seleccionarProveedor() (buscador o click en el pin) lo resalta, atenúa los demás y abre un
 * popup con "Ver proveedor" y "Mover ubicación". Solo activarArrastre() vuelve arrastrable ese
 * mismo marcador (no se crea otro) y desactivarArrastre() lo devuelve a su estado bloqueado.
 * Cada cambio de selección se anuncia con el evento zonas:proveedor-seleccionado.
 *
 * actualizar() aplica los datos recalculados por el servidor tras mover a un proveedor: color y
 * posición de los pines y contadores de las etiquetas, sin recargar la página.
 */
export function iniciarMapaGeneral(pagina, zonaInicial) {
    const seccion = pagina.querySelector('[data-zonas-mapa-general]');

    if (!seccion) {
        return null;
    }

    const datosEl = pagina.querySelector('[data-zonas-mapa-general-datos]');
    const datos = datosMapaGeneral(pagina);
    const lienzo = seccion.querySelector('[data-zonas-mapa-general-lienzo]');
    const estadoMapa = seccion.querySelector('[data-zonas-mapa-estado]');
    const urlProveedor = seccion.dataset.urlProveedor;
    const zonas = new Map(datos.zonas.map((zona) => [zona.id, {
        zona,
        vertices: verticesDeGeometria(zona.geometry, zona.nombre),
        poligono: null,
        etiqueta: null,
        marcadorEtiqueta: null,
        limites: null,
    }]));
    const pines = new Map();

    let mapsLib = null;
    let mapa = null;
    let infoWindow = null;
    let seleccion = zonaInicial;
    let proveedorSeleccionado = null;
    let arrastre = null;
    let marcarListo;
    const listo = new Promise((resolver) => {
        marcarListo = resolver;
    });

    // ------------------------------------------------------------------ zonas

    const resaltar = (id, { zoom = false } = {}) => {
        seleccion = id;

        seccion.querySelectorAll('[data-zonas-seleccionar]').forEach((boton) => {
            boton.classList.toggle('is-activa', parseInt(boton.dataset.zonasSeleccionar, 10) === id);
        });

        zonas.forEach((item, zonaId) => {
            item.poligono?.setOptions({ ...ESTILOS[zonaId === id ? 'activa' : 'normal'], zIndex: zonaId === id ? 2 : 1 });
            item.etiqueta?.classList.toggle('is-activa', zonaId === id);
        });

        if (!zoom || !mapa || id === null || arrastre) {
            return;
        }

        const item = zonas.get(id);
        const puntos = [...pines.values()].filter((pin) => pin.proveedor.zonaId === id).map((pin) => posicionDe(pin.marcador));
        const limites = item?.limites ?? (puntos.length ? limitesDeVertices(puntos) : null);

        encuadrar(mapa, limites, 56);
    };

    const seleccionarZona = (id) => {
        if (arrastre) {
            return; // Mientras se mueve un pin, el mapa no cambia de zona ni de encuadre.
        }

        resaltar(id, { zoom: true });
        document.dispatchEvent(new CustomEvent('fichas:seleccionar', { detail: { id, desplazar: false } }));
    };

    seccion.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-zonas-seleccionar]');

        if (boton) {
            seleccionarZona(parseInt(boton.dataset.zonasSeleccionar, 10));
        }
    });

    /**
     * Zona cuya delimitación contiene el punto; si dos se solapan y una es `preferida` (la zona
     * actual del proveedor), gana esa, igual que en el servidor.
     */
    const zonaEn = (lat, lng, preferida = null) => {
        const contenedoras = [...zonas.values()].filter((item) => item.vertices.length >= 3 && contienePunto(item.vertices, lat, lng));

        return (contenedoras.find((item) => item.zona.id === preferida) ?? contenedoras[0])?.zona ?? null;
    };

    const crearEtiqueta = (zona) => {
        const etiqueta = document.createElement('div');
        etiqueta.className = 'zonas-etiqueta';
        etiqueta.style.setProperty('--zona-fondo', oscurecer(zona.color));

        const nombre = document.createElement('strong');
        nombre.textContent = zona.nombre;
        const cantidad = document.createElement('span');
        cantidad.textContent = etiquetaProveedores(zona.proveedores);
        etiqueta.append(nombre, cantidad);

        return etiqueta;
    };

    // ------------------------------------------------------------- proveedores

    const colorDe = (proveedor) => zonas.get(proveedor.zonaId)?.zona.color ?? '#475467';

    /** Contenido del pin según el estado: seleccionado (más grande, con halo), atenuado o normal. */
    const contenidoPin = (proveedor) => {
        const pin = crearPinProveedor(colorDe(proveedor), proveedor.fueraDeDelimitacion);
        const seleccionado = proveedor.id === proveedorSeleccionado;

        pin.style.setProperty('--pin-color', colorDe(proveedor));
        pin.classList.toggle('is-seleccionado', seleccionado);
        pin.classList.toggle('is-atenuado', proveedorSeleccionado !== null && !seleccionado);
        pin.classList.toggle('is-moviendo', seleccionado && arrastre !== null);

        return pin;
    };

    const refrescarPines = () => {
        pines.forEach((pin) => {
            pin.marcador.content = contenidoPin(pin.proveedor);
            pin.marcador.zIndex = pin.proveedor.id === proveedorSeleccionado ? 60 : 30;
        });
    };

    const contenidoInfo = (proveedor) => {
        const caja = document.createElement('div');
        caja.className = 'zonas-info';

        const nombre = document.createElement('strong');
        nombre.textContent = proveedor.nombre;

        const lista = document.createElement('dl');
        [['DNI', proveedor.dni], ['Zona actual', proveedor.zonaNombre]].forEach(([termino, valor]) => {
            const dt = document.createElement('dt');
            dt.textContent = termino;
            const dd = document.createElement('dd');
            dd.textContent = valor;
            lista.append(dt, dd);
        });

        caja.append(nombre, lista);

        if (proveedor.fueraDeDelimitacion) {
            const fuera = document.createElement('p');
            fuera.className = 'is-fuera';
            fuera.textContent = `La ubicación registrada se encuentra fuera de la delimitación operativa de ${proveedor.zonaNombre}.`;
            caja.append(fuera);
        }

        const acciones = document.createElement('div');
        acciones.className = 'zonas-info-acciones';

        const enlace = document.createElement('a');
        enlace.href = urlProveedor.replace('__ID__', proveedor.id);
        enlace.className = 'fichas-btn is-outline is-sm';
        enlace.textContent = 'Ver proveedor';

        const mover = document.createElement('button');
        mover.type = 'button';
        mover.className = 'fichas-btn is-primary is-sm';
        mover.textContent = 'Mover ubicación';
        mover.addEventListener('click', () => {
            document.dispatchEvent(new CustomEvent('zonas:mover-ubicacion', { detail: { id: proveedor.id } }));
        });

        acciones.append(enlace, mover);
        caja.append(acciones);

        return caja;
    };

    const anunciarSeleccion = () => {
        const proveedor = pines.get(proveedorSeleccionado)?.proveedor ?? null;
        document.dispatchEvent(new CustomEvent('zonas:proveedor-seleccionado', { detail: { proveedor } }));
    };

    const abrirInfo = (pin) => {
        infoWindow.setContent(contenidoInfo(pin.proveedor));
        infoWindow.open({ map: mapa, anchor: pin.marcador });
    };

    /** Resalta el pin del proveedor; con `enfocar` centra el mapa en él con un zoom moderado. */
    const seleccionarProveedor = async (id, { enfocar = true } = {}) => {
        await listo;

        const pin = pines.get(id);

        if (!pin || !mapa || (arrastre && arrastre.id !== id)) {
            return false;
        }

        proveedorSeleccionado = id;
        refrescarPines();

        if (enfocar) {
            mapa.panTo(posicionDe(pin.marcador));

            if (mapa.getZoom() < ZOOM_PROVEEDOR) {
                mapa.setZoom(ZOOM_PROVEEDOR);
            }
        }

        if (!arrastre) {
            abrirInfo(pin);
        }

        anunciarSeleccion();

        return true;
    };

    const limpiarProveedor = () => {
        if (proveedorSeleccionado === null) {
            return;
        }

        proveedorSeleccionado = null;
        infoWindow?.close();
        refrescarPines();
        anunciarSeleccion();
    };

    const crearPin = (proveedor) => {
        if (!esCoordenadaValida(proveedor.lat, proveedor.lng)) {
            console.warn(`[EcoLact] ${proveedor.nombre} tiene coordenadas inválidas; no se dibuja su pin.`, proveedor);
            return null;
        }

        const marcador = new mapsLib.AdvancedMarkerElement({
            map: mapa,
            position: { lat: proveedor.lat, lng: proveedor.lng },
            content: contenidoPin(proveedor),
            title: proveedor.nombre,
            gmpClickable: true,
            gmpDraggable: false,
            zIndex: 30,
        });
        const pin = { proveedor, marcador };

        // Durante "Mover ubicación" los demás pines quedan bloqueados.
        marcador.addEventListener('gmp-click', () => {
            if (!arrastre) {
                seleccionarProveedor(pin.proveedor.id, { enfocar: false });
            }
        });

        pines.set(proveedor.id, pin);

        return pin;
    };

    // ------------------------------------------------------------ mover ubicación

    /**
     * Vuelve arrastrable SOLO el marcador del proveedor seleccionado. `alSoltar` recibe la
     * nueva posición cada vez que se suelta el pin.
     */
    const activarArrastre = (id, alSoltar) => {
        const pin = pines.get(id);

        if (!pin || !mapa || arrastre || id !== proveedorSeleccionado) {
            return false;
        }

        infoWindow.close();
        arrastre = { id, original: posicionDe(pin.marcador), escucha: null };
        pin.marcador.gmpDraggable = true;
        arrastre.escucha = pin.marcador.addListener('dragend', () => alSoltar(posicionDe(pin.marcador)));
        refrescarPines();

        return true;
    };

    /** Bloquea de nuevo el marcador; con `revertir` vuelve a su posición original. */
    const desactivarArrastre = ({ revertir = true } = {}) => {
        if (!arrastre) {
            return;
        }

        const pin = pines.get(arrastre.id);

        arrastre.escucha?.remove();

        if (pin) {
            pin.marcador.gmpDraggable = false;

            if (revertir) {
                pin.marcador.position = arrastre.original;
            }
        }

        arrastre = null;
        refrescarPines();

        if (pin && pin.proveedor.id === proveedorSeleccionado) {
            abrirInfo(pin);
        }
    };

    // ------------------------------------------------------------ datos del servidor

    /** Aplica el mapa recalculado por el servidor (tras mover a un proveedor). */
    const actualizar = (nuevos) => {
        if (datosEl) {
            datosEl.textContent = JSON.stringify(nuevos);
        }

        nuevos.zonas.forEach((zona) => {
            const item = zonas.get(zona.id);

            if (!item) {
                return;
            }

            item.zona = { ...item.zona, ...zona };

            if (item.etiqueta) {
                item.etiqueta.querySelector('span').textContent = etiquetaProveedores(zona.proveedores);
                item.marcadorEtiqueta.title = `${zona.nombre} · ${etiquetaProveedores(zona.proveedores)}`;
            }
        });

        if (!mapa) {
            return;
        }

        const vigentes = new Set();

        nuevos.proveedores.forEach((proveedor) => {
            const pin = pines.get(proveedor.id);
            vigentes.add(proveedor.id);

            if (pin && esCoordenadaValida(proveedor.lat, proveedor.lng)) {
                pin.proveedor = proveedor;
                pin.marcador.position = { lat: proveedor.lat, lng: proveedor.lng };
            } else if (!pin) {
                crearPin(proveedor);
            }
        });

        pines.forEach((pin, id) => {
            if (!vigentes.has(id)) {
                pin.marcador.map = null;
                pines.delete(id);
            }
        });

        if (!pines.has(proveedorSeleccionado)) {
            proveedorSeleccionado = null;
            infoWindow.close();
        }

        refrescarPines();

        const seleccionado = pines.get(proveedorSeleccionado);

        if (seleccionado && !arrastre) {
            abrirInfo(seleccionado);
        }

        anunciarSeleccion();
    };

    // ------------------------------------------------------------------ pintado

    /**
     * Google Maps no está disponible (sin key, red, o Google rechazó la key después de cargar):
     * en vez del bloque de error de Google se muestra el aviso de EcoLact con "Reintentar".
     * La leyenda, el buscador y el panel siguen funcionando.
     */
    const mostrarFallo = () => {
        lienzo.hidden = true;
        estadoMapa.hidden = false;
        estadoMapa.classList.add('is-error');
        estadoMapa.querySelector('[data-zonas-mapa-estado-texto]').textContent = 'No se pudo cargar el mapa. Verifica la configuración de Google Maps.';
        estadoMapa.querySelector('[data-zonas-mapa-reintentar]').hidden = false;
        infoWindow?.close();
        document.dispatchEvent(new CustomEvent('zonas:mapa-no-disponible'));
    };

    estadoMapa.querySelector('[data-zonas-mapa-reintentar]').addEventListener('click', () => window.location.reload());
    alFallarGoogleMaps(mostrarFallo);

    const pintar = async () => {
        mapsLib = await cargarGoogleMaps();

        if (!mapsLib) {
            mostrarFallo();
            marcarListo();
            return;
        }

        mapa = new mapsLib.Map(lienzo, {
            ...centroInicial(pagina),
            mapId: getMapId(),
            streetViewControl: false,
            mapTypeControl: false,
            clickableIcons: false,
        });
        infoWindow = new google.maps.InfoWindow();
        infoWindow.addListener('closeclick', () => limpiarProveedor());
        google.maps.event.addListenerOnce(mapa, 'tilesloaded', () => {
            if (!window.__ecolactMapsFallo) {
                estadoMapa.hidden = true;
            }
        });

        const limitesGenerales = new google.maps.LatLngBounds();

        zonas.forEach((item) => {
            const { zona } = item;

            if (item.vertices.length < 3) {
                return; // Sin delimitación (o inválida): la zona sigue en la leyenda y en el panel.
            }

            item.poligono = dibujarDelimitacion(mapa, zona);
            item.poligono.addListener('click', () => seleccionarZona(zona.id));
            item.limites = limitesDeVertices(item.vertices);
            limitesGenerales.union(item.limites);

            if (zona.etiqueta) {
                item.etiqueta = crearEtiqueta(zona);
                item.marcadorEtiqueta = new mapsLib.AdvancedMarkerElement({
                    map: mapa,
                    position: zona.etiqueta,
                    content: item.etiqueta,
                    title: `${zona.nombre} · ${etiquetaProveedores(zona.proveedores)}`,
                    gmpClickable: true,
                    zIndex: 20,
                });
                item.marcadorEtiqueta.addEventListener('gmp-click', () => seleccionarZona(zona.id));
            }
        });

        datos.proveedores.forEach((proveedor) => {
            if (crearPin(proveedor)) {
                limitesGenerales.extend({ lat: proveedor.lat, lng: proveedor.lng });
            }
        });

        encuadrar(mapa, limitesGenerales);
        resaltar(seleccion);
        marcarListo();
    };

    pintar();

    return {
        resaltar,
        seleccionada: () => seleccion,
        zonaEn,
        zona: (id) => zonas.get(id)?.zona ?? null,
        proveedor: (id) => pines.get(id)?.proveedor ?? null,
        seleccionarProveedor,
        limpiarProveedor,
        activarArrastre,
        desactivarArrastre,
        actualizar,
    };
}

// ---------------------------------------------------------------- editor de delimitación

/**
 * "Editar zona" → "Editar delimitación". Acciones: "Dibujar delimitación" (cada click agrega un
 * vértice, mínimo 3), "Editar vértices" (arrastrar vértices, crear uno arrastrando el punto medio
 * de un lado, quitar con click derecho), "Borrar delimitación" y "Guardar".
 * Cada cambio se escribe como GeoJSON Polygon en el campo oculto poligono_geojson, que se envía
 * con el mismo formulario de la zona; el servidor vuelve a validar la geometría (mínimo 3
 * puntos, sin lados que se crucen) antes de guardarla. Sin puntos, se quita la delimitación.
 * Como referencia se muestran, bloqueadas, las demás zonas y los proveedores de esta.
 */
export function iniciarEditorDelimitacion(pagina) {
    const editores = new WeakMap();

    const serializar = (path) => {
        const vertices = path.getArray().map((punto) => [Number(punto.lng().toFixed(7)), Number(punto.lat().toFixed(7))]);

        return vertices.length === 0 ? '' : JSON.stringify({ type: 'Polygon', coordinates: [[...vertices, vertices[0]]] });
    };

    const preparar = async (seccion) => {
        if (editores.has(seccion)) {
            return;
        }

        editores.set(seccion, null);

        const lienzo = seccion.querySelector('[data-delimitacion-mapa]');
        const inputGeoJson = seccion.querySelector('[data-delimitacion-geojson]');
        const inputColor = seccion.querySelector('[data-delimitacion-color]');
        const contador = seccion.querySelector('[data-delimitacion-puntos]');
        const resumen = seccion.querySelector('[data-delimitacion-resumen]');
        const aviso = seccion.querySelector('[data-delimitacion-aviso]');
        const zonaId = parseInt(seccion.dataset.delimitacionZona, 10);

        const sinMapa = () => {
            aviso.hidden = false;
            aviso.textContent = 'No se pudo cargar el mapa: la delimitación solo se puede dibujar sobre él. Verifica la configuración de Google Maps.';
            lienzo.hidden = true;
        };
        const mapsLib = await cargarGoogleMaps();

        // Con la key ya rechazada por Google, crear el mapa solo produce errores internos de la API.
        if (!mapsLib || window.__ecolactMapsFallo) {
            sinMapa();
            return;
        }

        alFallarGoogleMaps(sinMapa);

        const ayuda = seccion.querySelector('[data-delimitacion-ayuda]');
        const botonesModo = seccion.querySelectorAll('[data-delimitacion-modo]');

        const mapa = new mapsLib.Map(lienzo, {
            ...centroInicial(pagina),
            mapId: getMapId(),
            streetViewControl: false,
            mapTypeControl: true,
            clickableIcons: false,
        });

        // Las demás zonas se ven como referencia (bloqueadas, con su nombre) para dibujar
        // esta pegada a ellas sin solaparse; también los proveedores ubicados de esta zona.
        const referencia = datosMapaGeneral(pagina);
        const limitesReferencia = new google.maps.LatLngBounds();

        referencia.zonas
            .filter((zona) => zona.id !== zonaId && verticesDeGeometria(zona.geometry, zona.nombre).length >= 3)
            .forEach((zona) => {
                dibujarDelimitacion(mapa, zona, { clickable: false, estilo: 'referencia' });
                verticesDeGeometria(zona.geometry, zona.nombre).forEach((vertice) => limitesReferencia.extend(vertice));

                const nombre = document.createElement('div');
                nombre.className = 'zonas-etiqueta is-referencia';
                nombre.style.setProperty('--zona-fondo', oscurecer(zona.color));
                nombre.textContent = zona.nombre;
                if (zona.etiqueta && esCoordenadaValida(zona.etiqueta.lat, zona.etiqueta.lng)) {
                    new mapsLib.AdvancedMarkerElement({ map: mapa, position: zona.etiqueta, content: nombre });
                }
            });

        referencia.proveedores
            .filter((proveedor) => proveedor.zonaId === zonaId && esCoordenadaValida(proveedor.lat, proveedor.lng))
            .forEach((proveedor) => {
                const posicion = { lat: proveedor.lat, lng: proveedor.lng };
                new mapsLib.AdvancedMarkerElement({ map: mapa, position: posicion, content: crearPinProveedor(inputColor.value), title: proveedor.nombre });
                limitesReferencia.extend(posicion);
            });

        let vertices = [];

        try {
            vertices = inputGeoJson.value ? verticesDeGeometria(JSON.parse(inputGeoJson.value)) : [];
        } catch {
            vertices = [];
        }

        // El contorno CERRADO es un Polygon editable (vértices arrastrables, punto medio de cada
        // lado para crear uno nuevo). Mientras se dibuja, en cambio, se muestra una Polyline con un
        // punto por vértice: un Polygon de 1 o 2 vértices no se ve, y eso hacía parecer que los
        // clicks no dibujaban nada.
        const poligono = new google.maps.Polygon({
            map: mapa,
            paths: vertices,
            editable: true,
            strokeColor: inputColor.value,
            fillColor: inputColor.value,
            ...ESTILOS.normal,
            zIndex: 5,
        });
        const trazo = new google.maps.Polyline({
            map: mapa,
            path: [],
            clickable: false,
            strokeColor: inputColor.value,
            strokeOpacity: 1,
            strokeWeight: 3,
            zIndex: 6,
        });
        const puntosTrazo = trazo.getPath();
        const botonFinalizar = seccion.querySelector('[data-delimitacion-finalizar]');
        let nodos = [];
        let modo = 'editar';

        const crearNodo = (latLng, esPrimero) => {
            const nodo = document.createElement('span');
            nodo.className = esPrimero ? 'zonas-vertice is-primero' : 'zonas-vertice';
            nodo.style.setProperty('--zona-color', inputColor.value);

            const marcador = new mapsLib.AdvancedMarkerElement({
                map: mapa,
                position: latLng,
                content: nodo,
                zIndex: 40,
                gmpClickable: esPrimero,
                title: esPrimero ? 'Cerrar la delimitación en este punto' : undefined,
            });

            if (esPrimero) {
                marcador.addEventListener('gmp-click', () => finalizar());
            }

            return marcador;
        };

        const limpiarTrazo = () => {
            nodos.forEach((nodo) => {
                nodo.map = null;
            });
            nodos = [];
            puntosTrazo.clear();
        };

        const sincronizar = () => {
            const dibujando = modo === 'dibujar';
            const contorno = poligono.getPath();
            const total = dibujando ? puntosTrazo.getLength() : contorno.getLength();

            // Solo el contorno cerrado se envía: mientras se dibuja, el campo conserva lo último cerrado.
            if (!dibujando) {
                inputGeoJson.value = serializar(contorno);
            }

            botonFinalizar.hidden = !dibujando;
            botonFinalizar.disabled = puntosTrazo.getLength() < 3;
            contador.textContent = total === 0 ? 'Sin puntos' : `${total} ${total === 1 ? 'punto' : 'puntos'}${dibujando ? ' (dibujando)' : ''}`;
            resumen.textContent = contorno.getLength() === 0 ? 'Delimitación pendiente' : `${contorno.getLength()} puntos dibujados`;
        };

        const cambiarModo = (nuevo) => {
            modo = nuevo;
            botonesModo.forEach((boton) => boton.setAttribute('aria-pressed', String(boton.dataset.delimitacionModo === modo)));
            poligono.setOptions({ editable: modo === 'editar', clickable: modo === 'editar' });
            mapa.setOptions({ draggableCursor: modo === 'dibujar' ? 'crosshair' : null });
            ayuda.textContent = modo === 'dibujar'
                ? 'Haz click en el mapa, punto por punto, siguiendo el contorno de la zona. Para cerrarla, haz click en el primer punto o pulsa "Finalizar delimitación" (mínimo 3 puntos).'
                : 'Arrastra cualquier vértice para ajustar el contorno; arrastra el punto medio de un lado para crear un vértice nuevo; click derecho sobre un vértice para quitarlo.';
            sincronizar();
        };

        /** Cierra el contorno dibujado: pasa de la Polyline al Polygon editable. */
        const finalizar = () => {
            if (modo !== 'dibujar' || puntosTrazo.getLength() < 3) {
                return false;
            }

            poligono.setPath(puntosTrazo.getArray().slice());
            limpiarTrazo();
            cambiarModo('editar');
            escucharContorno();

            return true;
        };

        /** setPath() reemplaza el MVCArray del polígono: hay que volver a escuchar sus cambios. */
        let escuchas = [];
        const escucharContorno = () => {
            escuchas.forEach((escucha) => escucha.remove());
            const contorno = poligono.getPath();
            escuchas = ['insert_at', 'set_at', 'remove_at'].map((evento) => contorno.addListener(evento, sincronizar));
        };

        const empezarDibujo = () => {
            poligono.setPath([]);
            escucharContorno();
            limpiarTrazo();
            cambiarModo('dibujar');
        };

        mapa.addListener('click', (evento) => {
            if (modo !== 'dibujar' || !evento.latLng) {
                return;
            }

            nodos.push(crearNodo(evento.latLng, puntosTrazo.getLength() === 0));
            puntosTrazo.push(evento.latLng);
            sincronizar();
        });
        poligono.addListener('contextmenu', (evento) => {
            if (modo === 'editar' && evento.vertex !== undefined) {
                poligono.getPath().removeAt(evento.vertex);
            }
        });

        botonesModo.forEach((boton) => boton.addEventListener('click', () => {
            if (boton.dataset.delimitacionModo === 'editar') {
                // Si había un dibujo a medio hacer con 3 o más puntos, se cierra; si no, se descarta.
                if (modo === 'dibujar' && !finalizar()) {
                    limpiarTrazo();
                    cambiarModo('editar');
                }

                return;
            }

            // Dibujar sobre una delimitación ya hecha empieza un contorno nuevo (previa confirmación).
            if (poligono.getPath().getLength() > 0
                && !window.confirm('¿Reemplazar la delimitación actual por un dibujo nuevo? Hasta que pulses Guardar no se pierde nada.')) {
                return;
            }

            empezarDibujo();
        }));
        botonFinalizar.addEventListener('click', () => finalizar());
        seccion.querySelector('[data-delimitacion-deshacer]').addEventListener('click', () => {
            if (modo === 'dibujar' && puntosTrazo.getLength() > 0) {
                puntosTrazo.pop();
                const ultimo = nodos.pop();
                ultimo.map = null;
                sincronizar();
            } else if (modo === 'editar' && poligono.getPath().getLength() > 0) {
                poligono.getPath().pop();
            }
        });
        seccion.querySelector('[data-delimitacion-borrar]').addEventListener('click', () => {
            if (poligono.getPath().getLength() === 0 && puntosTrazo.getLength() === 0) {
                return;
            }

            if (window.confirm('¿Borrar la delimitación de esta zona? Se quitará al pulsar Guardar.')) {
                empezarDibujo();
                inputGeoJson.value = '';
            }
        });
        inputColor.addEventListener('input', () => {
            poligono.setOptions({ strokeColor: inputColor.value, fillColor: inputColor.value });
            trazo.setOptions({ strokeColor: inputColor.value });
        });

        escucharContorno();
        encuadrar(mapa, vertices.length > 0 ? limitesDeVertices(vertices) : limitesReferencia);
        cambiarModo(vertices.length > 0 ? 'editar' : 'dibujar');

        editores.set(seccion, {
            /** Antes de enviar: cierra un dibujo válido a medias; devuelve false si quedó incompleto. */
            listoParaGuardar: () => modo !== 'dibujar' || puntosTrazo.getLength() === 0 || finalizar(),
        });
    };

    const activar = (seccion) => {
        seccion.querySelector('[data-delimitacion-activar]').hidden = true;
        seccion.querySelector('[data-delimitacion-editor]').hidden = false;
        seccion.querySelectorAll('[data-delimitacion-geojson], [data-delimitacion-color]').forEach((campo) => {
            campo.disabled = false;
        });
        window.requestAnimationFrame(() => preparar(seccion));
    };

    document.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-delimitacion-activar]');

        if (boton) {
            activar(boton.closest('[data-delimitacion]'));
        }

        // Atajo "Dibujar/Editar delimitación" del panel: abre Editar zona con el editor desplegado.
        const atajo = event.target.closest('[data-delimitacion-abrir]');
        const seccionAtajo = atajo ? document.getElementById(atajo.dataset.modalOpen)?.querySelector('[data-delimitacion]') : null;

        if (seccionAtajo) {
            window.requestAnimationFrame(() => activar(seccionAtajo));
        }

        // Un modal que vuelve abierto tras un error de validación: su editor se prepara al verse.
        const disparador = event.target.closest('[data-modal-open]');
        const modal = disparador ? document.getElementById(disparador.dataset.modalOpen) : null;
        const abierta = modal?.querySelector('[data-delimitacion][data-delimitacion-abierta="true"]');

        if (abierta) {
            window.requestAnimationFrame(() => preparar(abierta));
        }
    });

    // Validación previa al envío: un dibujo de 1 o 2 puntos no forma un polígono (0 puntos = quitar la delimitación).
    document.addEventListener('submit', (event) => {
        const seccion = event.target.querySelector?.('[data-delimitacion]');
        const editor = seccion ? editores.get(seccion) : null;

        if (!editor) {
            return;
        }

        const error = seccion.querySelector('[data-delimitacion-error]');

        if (!editor.listoParaGuardar()) {
            event.preventDefault();
            error.textContent = 'La delimitación necesita al menos 3 puntos: sigue dibujando o pulsa "Borrar delimitación".';
            error.hidden = false;
        }
    }, true);

    // Tras un error de validación el formulario vuelve con el editor abierto y el modal visible.
    pagina.ownerDocument.querySelectorAll('[data-delimitacion][data-delimitacion-abierta="true"]').forEach((seccion) => {
        if (seccion.offsetParent !== null) {
            window.requestAnimationFrame(() => preparar(seccion));
        }
    });
}
