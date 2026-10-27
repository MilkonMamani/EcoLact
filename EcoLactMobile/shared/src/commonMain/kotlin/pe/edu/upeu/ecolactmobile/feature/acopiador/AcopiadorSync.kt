package pe.edu.upeu.ecolactmobile.feature.acopiador

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
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import kotlin.time.Duration
import kotlin.time.Duration.Companion.minutes
import kotlin.time.Duration.Companion.seconds

/** Todas las escrituras offline del acopiador; todas son idempotentes en el servidor. */
val OPERACIONES_ACOPIADOR = OPERACIONES_ENTREGA + setOf(
    SyncOperationTypes.ACOPIADOR_NO_ENTREGO,
    SyncOperationTypes.ACOPIADOR_INICIAR_RECORRIDO,
    SyncOperationTypes.ACOPIADOR_LEER_COMUNICADO,
)

/** Aviso breve mientras se envían registros del acopiador; null si no había nada suyo por enviar. */
fun mensajeEnvioAcopiador(tiposPendientes: List<String>): String? = when {
    tiposPendientes.any { it in OPERACIONES_ENTREGA } -> "Enviando entregas…"
    SyncOperationTypes.ACOPIADOR_NO_ENTREGO in tiposPendientes || SyncOperationTypes.ACOPIADOR_INICIAR_RECORRIDO in tiposPendientes -> "Enviando recorrido…"
    else -> null
}

/**
 * Sincronización automática del acopiador mientras su shell está en pantalla. Sin botón:
 * al haber conexión (al entrar o al volver la señal) envía la cola en orden
 * ([SyncManager.syncNow]: sin duplicados gracias al `Idempotency-Key`) y luego descarga a SQLite
 * proveedores, entregas, recorridos, seguimiento y comunicados. Las pantallas observan SQLite,
 * así que "Pendiente" pasa a "Sincronizada" sola. Mientras siga en línea repite cada [INTERVALO].
 */
class AcopiadorSync(
    private val repository: AcopiadorRepository,
    private val notificaciones: NotificacionesRepository,
    private val syncManager: SyncManager,
    private val syncQueue: SyncQueueRepository,
    connectivityObserver: ConnectivityObserver,
) : ScreenModel() {
    val isOnline: StateFlow<Boolean> = connectivityObserver.isOnline

    private val _aviso = MutableStateFlow<String?>(null)
    val aviso: StateFlow<String?> = _aviso.asStateFlow()

    private val syncMutex = Mutex()

    init {
        // Si la app se cerró en pleno envío, la fila quedó en SYNCING: se reenvía (es idempotente).
        syncQueue.all()
            .filter { it.status == SyncStatus.SYNCING.name && it.operation_type in OPERACIONES_ACOPIADOR }
            .forEach { syncQueue.markPending(it.id) }

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

    /** Tras guardar algo en línea o al volver de segundo plano: no esperar al próximo ciclo. */
    fun sincronizarAhora() {
        if (isOnline.value) scope.launch { sync() }
    }

    private suspend fun sync(): Boolean = syncMutex.withLock {
        val envio = mensajeEnvioAcopiador(syncQueue.pending().map { it.operation_type })
        envio?.let { _aviso.value = it }

        // La cola nunca se interrumpe a medias: una fila cancelada en SYNCING quedaría huérfana.
        withContext(NonCancellable) { syncManager.syncNow() }

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
