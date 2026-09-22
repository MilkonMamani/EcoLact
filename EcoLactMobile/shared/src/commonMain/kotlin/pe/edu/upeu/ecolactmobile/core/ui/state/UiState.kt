package pe.edu.upeu.ecolactmobile.core.ui.state

/**
 * Estado genérico de una pantalla que carga datos de un repositorio con cache offline.
 * `fromCache` + `cachedAtIso` permiten a la pantalla mostrar "datos guardados hasta las..."
 * en vez de aparentar que la información es en tiempo real (regla de cada .md, sección Offline).
 */
sealed interface UiState<out T> {
    data object Loading : UiState<Nothing>
    data class Success<T>(val data: T, val fromCache: Boolean, val cachedAtIso: String? = null) : UiState<T>
    data class Error(val message: String) : UiState<Nothing>
}
