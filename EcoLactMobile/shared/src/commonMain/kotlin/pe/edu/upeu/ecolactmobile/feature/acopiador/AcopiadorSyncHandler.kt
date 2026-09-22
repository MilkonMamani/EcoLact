package pe.edu.upeu.ecolactmobile.feature.acopiador

import kotlinx.serialization.decodeFromString
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult

/**
 * Envía al backend una entrega que quedó pendiente en la cola (se creó offline, o el intento
 * en línea falló por red). [id] (la clave de la fila en `sync_queue`) se reenvía como
 * `Idempotency-Key`: el backend ya la soporta para este endpoint (ver
 * EnsureIdempotentRequest.php), así que un reintento tras perder la respuesta original no
 * duplica la entrega.
 */
class AcopiadorEntregaSyncHandler(private val api: AcopiadorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val request = ApiJson.decodeFromString<AcopiadorEntregaRequest>(payloadJson)
        return try {
            val entrega = api.registrarEntrega(request, idempotencyKey = id)
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
