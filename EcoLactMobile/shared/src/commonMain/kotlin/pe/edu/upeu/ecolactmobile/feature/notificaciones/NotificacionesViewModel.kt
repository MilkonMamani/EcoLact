package pe.edu.upeu.ecolactmobile.feature.notificaciones

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.fetchCached
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState

class NotificacionesViewModel(
    private val api: NotificacionesApi,
    private val cache: CacheRepository,
) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<List<NotificacionItem>>>(UiState.Loading)
    val state: StateFlow<UiState<List<NotificacionItem>>> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = cache.fetchCached("notificaciones") { api.listar() }) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(outcome.data, fromCache = false)
                    is CacheOutcome.Stale -> _state.value =
                        UiState.Success(outcome.data, fromCache = true, cachedAtIso = outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudieron cargar las notificaciones")
            }
        }
    }

    fun marcarLeida(id: Long) {
        scope.launch {
            try {
                api.marcarLeida(id)
                _state.update { current ->
                    if (current is UiState.Success) {
                        current.copy(data = current.data.map { if (it.id == id) it.copy(leido = true) else it })
                    } else current
                }
            } catch (_: Exception) {
                // Marcar-leído es best-effort: si falla (sin conexión), no bloqueamos la lectura local.
            }
        }
    }
}
