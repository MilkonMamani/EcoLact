package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.serialization.Serializable
import kotlinx.serialization.decodeFromString
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult

/** Payload guardado en `sync_queue` para un análisis confirmado sin conexión. La imagen (si la hay) va en base64 dentro del mismo JSON. */
@Serializable
data class CalidadAnalisisSyncPayload(
    val entrega_id: Long,
    val valores: Map<String, Double>,
    val observacion: String? = null,
    val imagen_base64: String? = null,
    val imagen_filename: String? = null,
)

/**
 * Ejecuta guardarAnalisis (POST) y finalizarAnalisis (PATCH) como una sola operación lógica
 * (calidad.txt sección 23): si el POST tiene éxito pero el PATCH falla por red, la fila queda
 * PENDING y el próximo intento hace POST de nuevo (el backend acepta reenviar valores mientras
 * el análisis siga en borrador) y luego el PATCH — no hay estado intermedio expuesto al usuario.
 */
class CalidadAnalisisSyncHandler(private val api: CalidadApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS

    /** Sin Idempotency-Key: `guardarAnalisis`/`finalizarAnalisis` no están en la lista de endpoints que el backend protege (ver PROMPT_INICIAL.md sección 3) — [id] no se usa aquí. */
    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<CalidadAnalisisSyncPayload>(payloadJson)
        return try {
            val imagenBytes = payload.imagen_base64?.let { Base64Util.decode(it) }
            val borrador = api.guardarAnalisis(
                entregaId = payload.entrega_id,
                valores = payload.valores,
                observacion = payload.observacion,
                imagenBytes = imagenBytes,
                imagenFilename = payload.imagen_filename,
            )
            val confirmado = api.finalizarAnalisis(borrador.id)
            SyncPushResult.Success(confirmado.id)
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}

/** Payload guardado en `sync_queue` para una entrega por muestreo registrada sin conexión. */
@Serializable
data class CalidadEntregaMuestreadaSyncPayload(
    val metodo_identificacion: String,
    val identificador: String,
    val litros: Double,
    val observacion: String? = null,
)

class CalidadEntregaMuestreadaSyncHandler(private val api: CalidadApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.CALIDAD_REGISTRAR_ENTREGA_MUESTREADA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<CalidadEntregaMuestreadaSyncPayload>(payloadJson)
        return try {
            val entrega = api.registrarEntregaMuestreada(
                CalidadEntregaMuestreadaRequest(payload.metodo_identificacion, payload.identificador, payload.litros, payload.observacion),
                idempotencyKey = id,
            )
            SyncPushResult.Success(entrega.id)
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}
