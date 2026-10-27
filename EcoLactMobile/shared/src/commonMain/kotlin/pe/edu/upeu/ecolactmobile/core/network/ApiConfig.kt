package pe.edu.upeu.ecolactmobile.core.network

/**
 * Host del backend Laravel. Aún no hay un valor real definido — pídele al equipo la URL
 * del servidor (o el par host/puerto de desarrollo, ej. 10.0.2.2 para el emulador Android
 * apuntando a un Laravel local) antes de compilar contra un backend real.
 */
object ApiConfig {
    /**
     * Debe terminar en "/": todos los paths de cada Api se escriben SIN "/" inicial (ej. "login", "proveedor/dashboard").
     * `10.0.2.2` es el alias que usa el emulador de Android para llegar al `127.0.0.1` de la máquina anfitriona —
     * el backend real corre en `http://127.0.0.1:8000` en esta misma PC (`php artisan serve`).
     */
    var baseUrl: String = "http://10.0.2.2:8000/api/v1/"
}
