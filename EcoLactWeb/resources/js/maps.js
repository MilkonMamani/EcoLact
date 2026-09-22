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
 */
export function cargarGoogleMaps() {
    if (window.__ecolactMapsPromise) {
        return window.__ecolactMapsPromise;
    }

    const apiKey = document.querySelector('meta[name="google-maps-api-key"]')?.content?.trim();

    if (!apiKey) {
        window.__ecolactMapsPromise = Promise.resolve(null);
        return window.__ecolactMapsPromise;
    }

    window.__ecolactMapsPromise = new Promise((resolve) => {
        window.__ecolactMapsInit = resolve;

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&loading=async&callback=__ecolactMapsInit&v=weekly`;
        script.onerror = () => resolve(null);
        document.head.appendChild(script);
    }).then(async () => {
        if (!window.google?.maps) {
            return null;
        }

        try {
            const { Map } = await google.maps.importLibrary('maps');
            const marker = await google.maps.importLibrary('marker');

            return { Map, AdvancedMarkerElement: marker.AdvancedMarkerElement, PinElement: marker.PinElement };
        } catch {
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

export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
