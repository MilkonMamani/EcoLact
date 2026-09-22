import { cargarGoogleMaps, getMapId, getCsrfToken } from '../maps.js';

/**
 * Panel "Detalle de ruta" (admin/rutas/show, y cada ruta desplegada en el
 * acordeón de admin/zonas): búsqueda + alta de proveedores a la ruta
 * (carrito), reordenación con botones accesibles, y mapa de vista previa de
 * las paradas vigentes. iniciarPanelRuta() se exporta para que
 * admin-zonas.js la invoque sobre cada panel cargado por AJAX; aquí también
 * se auto-inicializa sobre el panel ya presente en la página de detalle
 * standalone.
 */
export function iniciarPanelRuta(root) {
    if (!root || root.dataset.panelIniciado === '1') {
        return;
    }

    root.dataset.panelIniciado = '1';

    iniciarBusquedaProveedores(root);
    iniciarReordenacion(root);
    iniciarMapaVistaPrevia(root);
}

const root = document.querySelector('[data-ruta-detalle]');

if (root) {
    iniciarPanelRuta(root);
}

function iniciarBusquedaProveedores(root) {
    const buscador = root.querySelector('[data-ruta-buscador]');
    if (!buscador) {
        return;
    }

    const input = buscador.querySelector('[data-ruta-buscador-input]');
    const resultados = buscador.querySelector('[data-ruta-buscador-resultados]');
    const proveedorIdInput = root.querySelector('[data-ruta-agregar-proveedor-id]');
    const proveedorNombreLabel = root.querySelector('[data-ruta-agregar-proveedor-nombre]');
    const formAgregar = root.querySelector('[data-ruta-agregar-form]');
    const url = buscador.dataset.rutaBuscadorUrl;
    let timer;

    input?.addEventListener('input', () => {
        window.clearTimeout(timer);
        const q = input.value.trim();
        resultados.innerHTML = '';
        resultados.classList.add('hidden');

        if (q === '') {
            return;
        }

        timer = window.setTimeout(async () => {
            let proveedores = [];

            try {
                const response = await fetch(`${url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                proveedores = response.ok ? await response.json() : [];
            } catch {
                proveedores = [];
            }

            resultados.innerHTML = '';

            if (proveedores.length === 0) {
                resultados.innerHTML = '<p class="px-4 py-3 text-sm text-text-muted">No se encontraron proveedores.</p>';
            } else {
                proveedores.forEach((p) => {
                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'block w-full px-4 py-3 text-left text-sm hover:bg-text-muted/10';

                    const titulo = document.createElement('strong');
                    titulo.textContent = p.nombre;
                    const dni = document.createElement('span');
                    dni.className = 'ml-2 text-text-muted';
                    dni.textContent = p.dni;
                    boton.append(titulo, dni);

                    if (p.yaEnEstaRuta) {
                        const badge = document.createElement('span');
                        badge.className = 'ml-2 rounded-full bg-success/10 px-2 py-0.5 text-xs text-success';
                        badge.textContent = 'Ya está en esta ruta';
                        boton.append(badge);
                        boton.disabled = true;
                        boton.className += ' opacity-50 cursor-not-allowed';
                    } else if (p.rutaActual) {
                        const aviso = document.createElement('span');
                        aviso.className = 'block text-xs text-warning';
                        aviso.textContent = `Actualmente en: ${p.rutaActual} (se trasladará)`;
                        boton.append(aviso);
                    }

                    if (!p.yaEnEstaRuta) {
                        boton.addEventListener('click', () => {
                            proveedorIdInput.value = p.id;
                            proveedorNombreLabel.textContent = `${p.nombre} (DNI ${p.dni})`;
                            proveedorNombreLabel.classList.remove('hidden');
                            resultados.classList.add('hidden');
                            input.value = '';
                            formAgregar.querySelector('[data-ruta-agregar-submit]').disabled = false;
                        });
                    }

                    resultados.append(boton);
                });
            }

            resultados.classList.remove('hidden');
        }, 200);
    });
}

function iniciarReordenacion(root) {
    const tabla = root.querySelector('[data-ruta-detalles-table]');
    if (!tabla) {
        return;
    }

    const url = tabla.dataset.reorderUrl;

    tabla.addEventListener('click', async (event) => {
        const boton = event.target.closest('[data-move-up], [data-move-down]');
        if (!boton) {
            return;
        }

        const fila = boton.closest('[data-detalle-id]');
        const filas = [...tabla.querySelectorAll('[data-detalle-id]')];
        const indice = filas.indexOf(fila);
        const destino = boton.hasAttribute('data-move-up') ? indice - 1 : indice + 1;

        if (destino < 0 || destino >= filas.length) {
            return;
        }

        if (boton.hasAttribute('data-move-up')) {
            fila.parentElement.insertBefore(fila, filas[destino]);
        } else {
            fila.parentElement.insertBefore(filas[destino], fila);
        }

        const orden = [...tabla.querySelectorAll('[data-detalle-id]')].map((f) => f.dataset.detalleId);

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ orden }),
            });

            if (!response.ok) {
                throw new Error('No se pudo guardar el nuevo orden.');
            }

            await recargarPanel(root);
        } catch {
            window.alert('No se pudo guardar el nuevo orden. Se recargará la página para mostrar el estado actual.');
            window.location.reload();
        }
    });
}

/**
 * Vuelve a pedir el panel de esta ruta y lo reemplaza en su sitio, en vez de
 * recargar toda la página: así una reordenación hecha dentro del acordeón de
 * /admin/zonas no colapsa el resto de zonas/rutas ya desplegadas.
 */
async function recargarPanel(root) {
    const url = root.dataset.panelUrl;

    if (!url) {
        window.location.reload();
        return;
    }

    const response = await fetch(url, { headers: { Accept: 'text/html' } });

    if (!response.ok) {
        window.location.reload();
        return;
    }

    const contenedor = document.createElement('div');
    contenedor.innerHTML = await response.text();
    const nuevoRoot = contenedor.firstElementChild;

    root.replaceWith(nuevoRoot);
    iniciarPanelRuta(nuevoRoot);
}

async function iniciarMapaVistaPrevia(root) {
    const contenedor = root.querySelector('[data-ruta-mapa]');
    if (!contenedor) {
        return;
    }

    let paradas = [];
    try {
        paradas = JSON.parse(contenedor.dataset.paradas || '[]');
    } catch {
        paradas = [];
    }

    const conUbicacion = paradas.filter((p) => p.lat !== null && p.lng !== null);

    if (conUbicacion.length === 0) {
        contenedor.innerHTML = '<p class="p-4 text-sm text-text-muted">Ningún proveedor de esta ruta tiene coordenadas registradas todavía.</p>';
        return;
    }

    const maps = await cargarGoogleMaps();

    if (!maps) {
        contenedor.innerHTML = '<p class="p-4 text-sm text-warning">No se pudo cargar Google Maps (falta configurar la clave o hubo un error de red). La lista y el registro de proveedores siguen disponibles con normalidad.</p>';
        return;
    }

    const mapa = new maps.Map(contenedor, {
        center: { lat: conUbicacion[0].lat, lng: conUbicacion[0].lng },
        zoom: 12,
        mapId: getMapId() || undefined,
    });

    const bounds = new google.maps.LatLngBounds();

    conUbicacion.forEach((parada) => {
        const pin = new maps.PinElement({ glyph: String(parada.orden) });
        const marker = new maps.AdvancedMarkerElement({
            map: mapa,
            position: { lat: parada.lat, lng: parada.lng },
            title: `${parada.orden}. ${parada.nombre}`,
            content: pin.element,
        });

        const info = new google.maps.InfoWindow({
            content: `<strong>${escapeHtml(parada.orden + '. ' + parada.nombre)}</strong><br>${escapeHtml(parada.referencia || 'Sin referencia adicional')}`,
        });

        marker.addListener('click', () => info.open({ anchor: marker, map: mapa }));
        bounds.extend({ lat: parada.lat, lng: parada.lng });
    });

    if (conUbicacion.length > 1) {
        mapa.fitBounds(bounds);
    }
}

function escapeHtml(valor) {
    const div = document.createElement('div');
    div.textContent = valor;
    return div.innerHTML;
}
