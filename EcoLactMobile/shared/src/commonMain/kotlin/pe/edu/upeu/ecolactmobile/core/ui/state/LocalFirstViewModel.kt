package pe.edu.upeu.ecolactmobile.core.ui.state

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.cache.NoLocalDataException

/**
 * ViewModel offline-first: la pantalla SIEMPRE muestra lo que hay en SQLite ([source]) y la red
 * solo sirve para actualizar esa copia ([refresher] escribe en SQLite; la UI se entera sola).
 *
 * Reglas de estado:
 * - hay copia local → [UiState.Success], aunque la red falle (el error remoto queda en segundo
 *   plano, nunca tapa los datos); `fromCache` = sin conexión ahora mismo;
 * - no hay copia y se está descargando → [UiState.Loading];
 * - no hay copia y no hay forma de obtenerla (sin internet o el servidor falló) → [UiState.Error].
 *
 * [refreshOnStart]: true para detalles que quizá nunca se descargaron; las listas principales
 * ya las mantiene al día la sincronización automática del rol. Con true, también se vuelve a
 * pedir al recuperar la conexión.
 */
open class LocalFirstViewModel<T>(
    source: Flow<CachedValue<T>?>,
    private val isOnline: StateFlow<Boolean>,
    private val refresher: suspend () -> Unit,
    refreshOnStart: Boolean = false,
) : ScreenModel() {
    private val refreshing = MutableStateFlow(refreshOnStart && isOnline.value)
    private val refreshError = MutableStateFlow<String?>(null)

    val state: StateFlow<UiState<T>> =
        combine(source, isOnline, refreshing, refreshError) { cached, online, busy, error ->
            when {
                cached != null -> UiState.Success(cached.value, fromCache = !online, cachedAtIso = cached.cachedAtIso)
                busy -> UiState.Loading
                !online -> UiState.Error(NoLocalDataException().message.orEmpty())
                error != null -> UiState.Error(error)
                else -> UiState.Loading
            }
        }.stateIn(scope, SharingStarted.Eagerly, UiState.Loading)

    init {
        if (refreshOnStart) {
            refresh()
            scope.launch {
                isOnline.drop(1).filter { it }.collect { refresh() }
            }
        } else {
            // Primera vez en este teléfono: no esperar a la próxima sincronización automática.
            scope.launch { if (source.first() == null) refresh() }
        }
    }

    /** Pide datos nuevos al servidor. Sin conexión no hace nada: se sigue mostrando lo guardado. */
    fun refresh() {
        if (!isOnline.value) {
            refreshing.value = false
            return
        }
        scope.launch {
            refreshing.value = true
            try {
                refresher()
                refreshError.value = null
            } catch (e: Exception) {
                refreshError.value = e.message ?: "No se pudo cargar la información"
            } finally {
                refreshing.value = false
            }
        }
    }
}
