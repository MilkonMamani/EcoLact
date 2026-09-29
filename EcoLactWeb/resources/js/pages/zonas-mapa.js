import { cargarGoogleMaps, getMapId } from '../maps.js';

/**
 * Piezas de mapa de la pantalla /admin/zonas, compartidas por sus tres mapas:
 *  - mapa general (encima de la tabla): todas las zonas del filtro con su delimitación, una
 *    etiqueta "nombre + N proveedores" y un punto por proveedor con coordenadas;
 *  - mapa del Resumen del panel (admin-zonas.js): solo la zona seleccionada;
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

/** Vértices {lat, lng} del anillo exterior, sin repetir el primero al final. */
export function verticesDeGeometria(geometry) {
    const anillo = geometry?.coordinates?.[0] ?? [];
    const vertices = anillo.map(([lng, lat]) => ({ lat, lng }));

    if (vertices.length > 1) {
        const [primero, ultimo] = [vertices[0], vertices[vertices.length - 1]];

        if (primero.lat === ultimo.lat && primero.lng === ultimo.lng) {
            vertices.pop();
        }
    }

    return vertices;
}

export function dibujarDelimitacion(mapa, zona, { clickable = true, estilo = 'normal' } = {}) {
    return new google.maps.Polygon({
        map: mapa,
        paths: verticesDeGeometria(zona.geometry),
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

function formatoLitros(litros) {
    return `${Math.round(litros).toLocaleString('es-PE')} L`;
}

/** Datos del mapa general que el servidor dejó en la página (vacío si no hay zonas). */
export function datosMapaGeneral(pagina) {
    const datos = pagina.querySelector('[data-zonas-mapa-general-datos]');

    return datos ? JSON.parse(datos.textContent) : { zonas: [], proveedores: [] };
}

// ---------------------------------------------------------------- mapa general

/**
 * Click en un polígono, en su etiqueta o en la leyenda → misma selección que hacer click en la
 * fila (evento fichas:seleccionar de admin-fichas.js): se resalta la fila, se carga el panel y el
 * mapa hace zoom a la zona. En sentido contrario, admin-zonas.js llama a resaltar() cada vez que
 * el panel muestra otra zona (click en la tabla), así mapa y tabla comparten la selección.
 */
export function iniciarMapaGeneral(pagina, zonaInicial) {
    const seccion = pagina.querySelector('[data-zonas-mapa-general]');

    if (!seccion) {
        return { resaltar: () => {} };
    }

    const datos = datosMapaGeneral(pagina);
    const lienzo = seccion.querySelector('[data-zonas-mapa-general-lienzo]');
    const aviso = seccion.querySelector('[data-zonas-mapa-general-aviso]');
    const urlProveedor = seccion.dataset.urlProveedor;
    const zonas = new Map(datos.zonas.map((zona) => [zona.id, { zona, poligono: null, etiqueta: null, limites: null, puntos: [] }]));

    let mapa = null;
    let infoWindow = null;
    let limitesGenerales = null;
    let seleccion = zonaInicial;

    const resaltar = (id, { zoom = false } = {}) => {
        seleccion = id;

        seccion.querySelectorAll('[data-zonas-seleccionar]').forEach((boton) => {
            boton.classList.toggle('is-activa', parseInt(boton.dataset.zonasSeleccionar, 10) === id);
        });

        zonas.forEach((item, zonaId) => {
            item.poligono?.setOptions({ ...ESTILOS[zonaId === id ? 'activa' : 'normal'], zIndex: zonaId === id ? 2 : 1 });
            item.etiqueta?.classList.toggle('is-activa', zonaId === id);
        });

        if (!zoom || !mapa || id === null) {
            return;
        }

        const item = zonas.get(id);
        const limites = item?.limites ?? (item?.puntos.length ? limitesDeVertices(item.puntos) : null);

        encuadrar(mapa, limites, 56);
    };

    const seleccionar = (id) => {
        resaltar(id, { zoom: true });
        document.dispatchEvent(new CustomEvent('fichas:seleccionar', { detail: { id, desplazar: false } }));
    };

    seccion.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-zonas-seleccionar]');

        if (boton) {
            seleccionar(parseInt(boton.dataset.zonasSeleccionar, 10));
        }
    });

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

    const contenidoInfo = (proveedor) => {
        const caja = document.createElement('div');
        caja.className = 'zonas-info';

        const nombre = document.createElement('strong');
        nombre.textContent = proveedor.nombre;

        const lista = document.createElement('dl');
        [
            ['DNI', proveedor.dni],
            ['Zona', proveedor.zonaNombre],
            ['Última entrega', proveedor.ultimaEntrega ?? 'Sin entregas'],
            ['Litros (semana)', formatoLitros(proveedor.litrosSemana)],
        ].forEach(([termino, valor]) => {
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

        const enlace = document.createElement('a');
        enlace.href = urlProveedor.replace('__ID__', proveedor.id);
        enlace.textContent = 'Ver proveedor';
        caja.append(enlace);

        return caja;
    };

    const pintar = async () => {
        const mapsLib = await cargarGoogleMaps();

        if (!mapsLib) {
            aviso.hidden = false;
            aviso.textContent = 'No se pudo cargar Google Maps (falta configurar GOOGLE_MAPS_API_KEY o hubo un problema de red). La leyenda y la tabla siguen funcionando.';
            lienzo.hidden = true;
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
        limitesGenerales = new google.maps.LatLngBounds();

        zonas.forEach((item) => {
            const { zona } = item;

            if (!zona.geometry) {
                return;
            }

            item.poligono = dibujarDelimitacion(mapa, zona);
            item.poligono.addListener('click', () => seleccionar(zona.id));
            item.limites = limitesDeVertices(verticesDeGeometria(zona.geometry));
            limitesGenerales.union(item.limites);

            if (zona.etiqueta) {
                item.etiqueta = crearEtiqueta(zona);
                const marcador = new mapsLib.AdvancedMarkerElement({
                    map: mapa,
                    position: zona.etiqueta,
                    content: item.etiqueta,
                    title: `${zona.nombre} · ${etiquetaProveedores(zona.proveedores)}`,
                    gmpClickable: true,
                    zIndex: 20,
                });
                marcador.addListener('gmp-click', () => seleccionar(zona.id));
            }
        });

        datos.proveedores.forEach((proveedor) => {
            const item = zonas.get(proveedor.zonaId);
            const posicion = { lat: proveedor.lat, lng: proveedor.lng };
            const marcador = new mapsLib.AdvancedMarkerElement({
                map: mapa,
                position: posicion,
                content: crearPinProveedor(item.zona.color, proveedor.fueraDeDelimitacion),
                title: proveedor.nombre,
                gmpClickable: true,
                zIndex: 30,
            });

            marcador.addListener('gmp-click', () => {
                infoWindow.setContent(contenidoInfo(proveedor));
                infoWindow.open({ map: mapa, anchor: marcador });
            });

            item.puntos.push(posicion);
            limitesGenerales.extend(posicion);
        });

        encuadrar(mapa, limitesGenerales);
        resaltar(seleccion);
    };

    pintar();

    return {
        resaltar,
        seleccionada: () => seleccion,
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

        const mapsLib = await cargarGoogleMaps();

        if (!mapsLib) {
            aviso.hidden = false;
            aviso.textContent = 'No se pudo cargar Google Maps: la delimitación solo se puede dibujar sobre el mapa.';
            lienzo.hidden = true;
            return;
        }

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
            .filter((zona) => zona.id !== zonaId && zona.geometry)
            .forEach((zona) => {
                dibujarDelimitacion(mapa, zona, { clickable: false, estilo: 'referencia' });
                verticesDeGeometria(zona.geometry).forEach((vertice) => limitesReferencia.extend(vertice));

                const nombre = document.createElement('div');
                nombre.className = 'zonas-etiqueta is-referencia';
                nombre.style.setProperty('--zona-fondo', oscurecer(zona.color));
                nombre.textContent = zona.nombre;
                new mapsLib.AdvancedMarkerElement({ map: mapa, position: zona.etiqueta, content: nombre });
            });

        referencia.proveedores
            .filter((proveedor) => proveedor.zonaId === zonaId)
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
                marcador.addListener('gmp-click', () => finalizar());
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
