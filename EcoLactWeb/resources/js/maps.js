/**
 * Cargador único de la Google Maps JavaScript API para todo el proyecto.
 * Usa el patrón oficial recomendado (bootstrap loader + loading=async +
 * callback + google.maps.importLibrary) en vez de <script src> clásico, y
 * memoiza la promesa en `window` para que, sin importar cuántas veces una
 * página llame a cargarGoogleMaps(), el script y las librerías solo se
 * carguen una vez. Devuelve null (nunca lanza) cuando no hay API key
 * configurada o la carga falla, para que cada pantalla pueda mostrar un
 * aviso y seguir operando en modo "sin mapa" (lista, búsqueda y registro
 * manual siguen funcionando).
 *
 * Google rechaza la key DESPUÉS de cargar el script (key inválida, referer no
 * autorizado, API sin activar, sin facturación o cuota agotada): en ese caso
 * llama a window.gm_authFailure y reemplaza cada mapa por su propio bloque
 * "Esta página no ha cargado Google Maps correctamente". Ese fallo se anuncia
 * con alFallarGoogleMaps() para que cada pantalla muestre su aviso; el detalle
 * técnico sigue en la consola.
 */
const TIEMPO_MAXIMO_MS = 20000;
const EVENTO_FALLO = 'ecolact:google-maps-fallo';

function fallar(motivo, detalle = null) {
    if (window.__ecolactMapsFallo) {
        return;
    }

    window.__ecolactMapsFallo = motivo;
    console.error(`[EcoLact] Google Maps no está disponible: ${motivo}`, detalle ?? '');
    document.dispatchEvent(new CustomEvent(EVENTO_FALLO, { detail: { motivo } }));
}

/**
 * Ejecuta `alFallar(motivo)` si Google Maps falló o cuando falle (incluido el
 * rechazo de la key que Google informa después de haber cargado los mapas).
 */
export function alFallarGoogleMaps(alFallar) {
    if (window.__ecolactMapsFallo) {
        alFallar(window.__ecolactMapsFallo);
        return;
    }

    document.addEventListener(EVENTO_FALLO, (event) => alFallar(event.detail.motivo), { once: true });
}

export function cargarGoogleMaps() {
    if (window.__ecolactMapsPromise) {
        return window.__ecolactMapsPromise;
    }

    const apiKey = document.querySelector('meta[name="google-maps-api-key"]')?.content?.trim();

    if (!apiKey) {
        fallar('falta GOOGLE_MAPS_API_KEY (config services.google_maps.api_key).');
        window.__ecolactMapsPromise = Promise.resolve(null);
        return window.__ecolactMapsPromise;
    }

    // Google llama a esta función global cuando rechaza la key; la causa exacta
    // (RefererNotAllowedMapError, BillingNotEnabledMapError, cuota, ...) la escribe él mismo en la consola.
    window.gm_authFailure = () => fallar(
        'Google rechazó la API key (revisa el error anterior de la consola: restricción de sitios, API no activada, facturación o cuota).',
    );

    window.__ecolactMapsPromise = new Promise((resolve) => {
        const limite = window.setTimeout(() => {
            fallar(`la API no respondió en ${TIEMPO_MAXIMO_MS / 1000} s (red o bloqueador).`);
            resolve(null);
        }, TIEMPO_MAXIMO_MS);

        window.__ecolactMapsInit = () => {
            window.clearTimeout(limite);
            resolve(true);
        };

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&loading=async&callback=__ecolactMapsInit&v=weekly`;
        script.onerror = () => {
            window.clearTimeout(limite);
            fallar('no se pudo descargar https://maps.googleapis.com/maps/api/js (red, DNS o bloqueador).');
            resolve(null);
        };
        document.head.appendChild(script);
    }).then(async (cargado) => {
        if (!cargado || !window.google?.maps) {
            return null;
        }

        try {
            const { Map } = await google.maps.importLibrary('maps');
            const marker = await google.maps.importLibrary('marker');

            return { Map, AdvancedMarkerElement: marker.AdvancedMarkerElement, PinElement: marker.PinElement };
        } catch (error) {
            fallar('no se pudieron importar las librerías maps/marker.', error);
            return null;
        }
    });

    return window.__ecolactMapsPromise;
}

/**
 * Los AdvancedMarkerElement exigen un Map ID. Si no se configuró
 * GOOGLE_MAPS_MAP_ID, se usa DEMO_MAP_ID (el ID de demostración que
 * Google documenta para desarrollo): así los marcadores funcionan sin
 * configuración adicional; para producción conviene crear un Map ID propio.
 */
export function getMapId() {
    return document.querySelector('meta[name="google-maps-map-id"]')?.content?.trim() || 'DEMO_MAP_ID';
}

/** Latitud y longitud numéricas, finitas y dentro de rango (nunca null, '', NaN). */
export function esCoordenadaValida(lat, lng) {
    return typeof lat === 'number' && typeof lng === 'number'
        && Number.isFinite(lat) && Number.isFinite(lng)
        && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
