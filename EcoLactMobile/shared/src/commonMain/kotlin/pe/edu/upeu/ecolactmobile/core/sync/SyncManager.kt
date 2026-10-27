package pe.edu.upeu.ecolactmobile.core.sync

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import pe.edu.upeu.ecolactmobile.core.network.ApiException

/**
 * Motor de sincronización genérico: recorre la cola PENDING en orden de creación y la despacha
 * al [SyncOperationHandler] registrado para cada `operation_type`. Ver sección 3 del prompt
 * inicial (backend) para las reglas de negocio detrás de este comportamiento:
 * - nunca se reintenta una fila que ya tiene server_id;
 * - un 422/403 va a NEEDS_REVIEW y no se reintenta solo, nunca en loop;
 * - para los endpoints que ya soportan `Idempotency-Key` (entregas, producción, movimiento de
 *   insumo — ver cada handler), el caso "la petición llegó pero se perdió la respuesta" está
 *   resuelto del lado del backend: un reintento con la misma clave nunca duplica. Para cualquier
 *   otro tipo de operación, sigue sin garantía.
 * - como el backend todavía no distingue 422 (permanente) de 500 (transitorio) para errores de
 *   negocio, [MAX_INTENTOS_TRANSITORIOS] acota los reintentos automáticos antes de pasar a
 *   NEEDS_REVIEW, para no reintentar un error permanente en loop infinito.
 */
class SyncManager(
    private val queueRepository: SyncQueueRepository,
    private val connectivityObserver: ConnectivityObserver,
) {
    private val handlers = mutableMapOf<String, SyncOperationHandler>()
    private val syncMutex = Mutex()

    companion object {
        /**
         * El backend todavía no distingue 422 (error de negocio permanente) de 500 (falla
         * transitoria) para las reglas de dominio (ver PROMPT_INICIAL.md sección 3, gap conocido
         * de DomainException sin mapear a HTTP) — hoy ambos casos llegan aquí como
         * TransientFailure. Reintentar un error permanente en loop infinito sería igual de malo
         * que no reintentar uno transitorio, así que se acota: tras este número de intentos, se
         * pasa a NEEDS_REVIEW igual que un 422 real, en vez de seguir PENDING para siempre.
         */
        const val MAX_INTENTOS_TRANSITORIOS = 5

        const val MENSAJE_SYNC_SIN_ASISTENCIA = "No se pudo sincronizar esta operación porque no existe una asistencia válida para ese momento."
    }

    fun register(handler: SyncOperationHandler) {
        handlers[handler.operationType] = handler
    }

    fun startAutoSync(scope: CoroutineScope) {
        scope.launch {
            // StateFlow ya no emite valores consecutivos iguales, así que cada emisión tras la
            // primera (que se descarta con drop(1)) es necesariamente una transición real.
            connectivityObserver.isOnline
                .drop(1)
                .collect { online ->
                    if (online) syncNow()
                }
        }
    }

    /** Idempotente frente a llamadas concurrentes: si ya hay una sincronización en curso, esta espera y no duplica el barrido. */
    suspend fun syncNow(): SyncRunSummary = syncMutex.withLock {
        var succeeded = 0
        var rejected = 0
        var stillPending = 0

        for (row in queueRepository.pending()) {
            val handler = handlers[row.operation_type]
            if (handler == null || !handler.canPush(row.payload_json)) {
                // No debería pasar en producción: significa que se encoló un tipo de operación
                // sin registrar su handler. La dejamos PENDING en vez de perderla.
                stillPending++
                continue
            }

            queueRepository.markSyncing(row.id)

            when (val result = handler.push(row.id, row.payload_json)) {
                is SyncPushResult.Success -> {
                    queueRepository.markSynced(row.id, result.serverId)
                    handler.onSynced(row.id, result.serverId)
                    succeeded++
                }
                is SyncPushResult.Rejected -> {
                    // Rechazo por asistencia (evaluada a la hora del EVENTO): la operación no se
                    // borra; queda para revisión con un mensaje claro.
                    val mensaje = if (result.message == ApiException.MENSAJE_ASISTENCIA_REQUERIDA) MENSAJE_SYNC_SIN_ASISTENCIA else result.message
                    queueRepository.markNeedsReview(row.id, mensaje)
                    rejected++
                }
                is SyncPushResult.TransientFailure -> {
                    // markSyncing ya incrementó attempt_count antes de este push; row.attempt_count
                    // todavía refleja el valor previo a este intento.
                    if (row.attempt_count + 1 >= MAX_INTENTOS_TRANSITORIOS) {
                        queueRepository.markNeedsReview(row.id, "${result.message} (se agotaron los $MAX_INTENTOS_TRANSITORIOS reintentos automáticos)")
                        rejected++
                    } else {
                        queueRepository.markPending(row.id)
                        stillPending++
                    }
                }
            }
        }

        SyncRunSummary(succeeded = succeeded, rejected = rejected, stillPending = stillPending)
    }
}

data class SyncRunSummary(
    val succeeded: Int,
    val rejected: Int,
    val stillPending: Int,
)
