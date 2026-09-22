package pe.edu.upeu.ecolactmobile.core.ui.state

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome

/**
 * Base reutilizable para cualquier pantalla de solo-lectura con fallback a cache offline:
 * intenta red, y si falla sirve la última copia local marcada con su hora (patrón usado en
 * casi todas las pantallas de los 6 roles).
 */
open class ResourceViewModel<T>(private val loader: suspend () -> CacheOutcome<T>) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<T>>(UiState.Loading)
    val state: StateFlow<UiState<T>> = _state.asStateFlow()

    init {
        refresh()
    }

    fun refresh() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = loader()) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(outcome.data, fromCache = false)
                    is CacheOutcome.Stale -> _state.value =
                        UiState.Success(outcome.data, fromCache = true, cachedAtIso = outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudo cargar la información")
            }
        }
    }
}
