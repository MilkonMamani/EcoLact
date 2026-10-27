package pe.edu.upeu.ecolactmobile.feature.proveedor

import kotlinx.coroutines.NonCancellable
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.core.sync.SyncManager
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import kotlin.time.Duration
import kotlin.time.Duration.Companion.minutes
import kotlin.time.Duration.Companion.seconds

/** Estado de la sincronización automática, solo para la pantalla "Sincronización". */
sealed interface ProveedorSyncStatus {
    data object Offline : ProveedorSyncStatus
    data object Syncing : ProveedorSyncStatus
    data object AlDia : ProveedorSyncStatus

    /** Hay internet pero el servidor no respondió: se sigue mostrando lo guardado y se reintenta solo. */
    data class Fallo(val mensaje: String) : ProveedorSyncStatus
}

/**
 * Coordina el modo offline-first del proveedor mientras su shell está en pantalla. El usuario
 * nunca tiene que tocar "Sincronizar":
 * 1. observa la conectividad real ([ConnectivityObserver]);
 * 2. con conexión (al entrar o al volver la señal), primero envía la cola de acciones pendientes
 *    ([SyncManager.syncNow]: orden, sin duplicados ni reintentos infinitos) y luego descarga
 *    todo lo del proveedor a SQLite. Las pantallas observan SQLite, así que se actualizan solas;
 * 3. mientras siga en línea, repite cada [INTERVALO] (pagos marcados, notificaciones nuevas) y,
 *    si el servidor falló, reintenta con espera creciente.
 */
class ProveedorSync(
    private val repository: ProveedorRepository,
    private val notificaciones: NotificacionesRepository,
    private val syncManager: SyncManager,
    connectivityObserver: ConnectivityObserver,
) : ScreenModel() {
    val isOnline: StateFlow<Boolean> = connectivityObserver.isOnline

    private val _status = MutableStateFlow<ProveedorSyncStatus>(
        if (isOnline.value) ProveedorSyncStatus.Syncing else ProveedorSyncStatus.Offline,
    )
    val status: StateFlow<ProveedorSyncStatus> = _status.asStateFlow()

    private val syncMutex = Mutex()

    init {
        scope.launch {
            // collectLatest: al perder la señal se cancela la descarga en curso y el ciclo
            // periódico, en vez de esperar timeouts.
            isOnline.collectLatest { online ->
                if (!online) {
                    _status.value = ProveedorSyncStatus.Offline
                    return@collectLatest
                }
                var espera = REINTENTO_INICIAL
                while (true) {
                    val ok = sync()
                    espera = if (ok) REINTENTO_INICIAL else minOf(espera * 2, INTERVALO)
                    delay(if (ok) INTERVALO else espera)
                }
            }
        }
    }

    private suspend fun sync(): Boolean = syncMutex.withLock {
        _status.value = ProveedorSyncStatus.Syncing

        // La cola nunca se interrumpe a medias: una fila cancelada en SYNCING quedaría huérfana.
        withContext(NonCancellable) { syncManager.syncNow() }

        val error = repository.refreshAll()
            ?: runCatching { notificaciones.refresh() }.exceptionOrNull()

        _status.value = if (error == null) {
            ProveedorSyncStatus.AlDia
        } else {
            ProveedorSyncStatus.Fallo(error.message ?: "El servidor no respondió")
        }
        error == null
    }

    private companion object {
        val INTERVALO: Duration = 5.minutes
        val REINTENTO_INICIAL: Duration = 15.seconds
    }
}
