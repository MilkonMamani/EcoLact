package pe.edu.upeu.ecolactmobile.feature.planta

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
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import kotlin.time.Duration
import kotlin.time.Duration.Companion.minutes
import kotlin.time.Duration.Companion.seconds

/** Aviso breve mientras se envía lo hecho sin conexión; null si no había nada de planta por enviar. */
fun mensajeEnvioPlanta(tiposPendientes: List<String>): String? = when {
    SyncOperationTypes.PLANTA_INICIAR_PRODUCCION in tiposPendientes -> "Enviando producción…"
    SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION in tiposPendientes -> "Enviando producción…"
    SyncOperationTypes.PLANTA_SUBIR_BOLETA in tiposPendientes -> "Procesando OCR…"
    SyncOperationTypes.PLANTA_REGISTRAR_COMPRA in tiposPendientes -> "Enviando compras…"
    else -> null
}

/**
 * Sincronización automática del encargado mientras su shell está en pantalla (igual que
 * CalidadSync): con conexión envía la cola en orden ([SyncManager.syncNow]) y descarga a SQLite
 * insumos, productos con sus precios, recetas, lotes y compras. Sin botón "Sincronizar".
 */
class PlantaSync(
    private val repository: PlantaRepository,
    private val notificaciones: NotificacionesRepository,
    private val syncManager: SyncManager,
    private val syncQueue: SyncQueueRepository,
    connectivityObserver: ConnectivityObserver,
) : ScreenModel() {
    val isOnline: StateFlow<Boolean> = connectivityObserver.isOnline

    private val _aviso = MutableStateFlow<String?>(null)

    /** Texto temporal bajo la barra superior; null = nada que mostrar. */
    val aviso: StateFlow<String?> = _aviso.asStateFlow()

    private val syncMutex = Mutex()

    init {
        scope.launch {
            isOnline.collectLatest { online ->
                if (!online) {
                    _aviso.value = null
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
        val envio = mensajeEnvioPlanta(syncQueue.pending().map { it.operation_type })
        envio?.let { _aviso.value = it }

        // La cola nunca se interrumpe a medias: una fila cancelada en SYNCING quedaría huérfana.
        withContext(NonCancellable) { syncManager.syncNow() }

        if (envio != null) _aviso.value = "Actualizando datos…"
        val error = repository.refreshAll() ?: runCatching { notificaciones.refresh() }.exceptionOrNull()

        if (envio != null) {
            _aviso.value = if (error == null) "Sincronización completada" else null
            scope.launch {
                delay(AVISO_VISIBLE)
                if (_aviso.value == "Sincronización completada") _aviso.value = null
            }
        }
        error == null
    }

    private companion object {
        val INTERVALO: Duration = 5.minutes
        val REINTENTO_INICIAL: Duration = 15.seconds
        val AVISO_VISIBLE: Duration = 3.seconds
    }
}
