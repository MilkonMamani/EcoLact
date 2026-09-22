package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.serialization.Serializable
import kotlinx.serialization.decodeFromString
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult

/** CORREGIDO (Etapa 3/4): sin `codigo` (lo genera el servidor) ni `entregas_utilizadas` (el acopio no se separa por entrega, ver PlantaModels.kt). */
@Serializable
data class PlantaIniciarProduccionPayload(
    val litros_utilizados: Double,
    val fecha_hora_inicio: String? = null,
    val observacion: String? = null,
)

class PlantaIniciarProduccionSyncHandler(private val api: PlantaApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PLANTA_INICIAR_PRODUCCION

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<PlantaIniciarProduccionPayload>(payloadJson)
        return try {
            val lote = api.iniciarProduccion(
                IniciarProduccionRequest(payload.litros_utilizados, payload.fecha_hora_inicio, payload.observacion),
                idempotencyKey = id,
            )
            SyncPushResult.Success(lote.id)
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}

/** CORREGIDO (Etapa 3/4): sin `insumos_utilizados` — el backend los calcula solo (ver PlantaModels.kt). */
@Serializable
data class PlantaFinalizarProduccionPayload(
    val lote_id: Long,
    val producto_id: Long,
    val cantidad_producida: Double,
)

/** Solo se encola contra un `lote_id` que ya es real en el servidor — ver nota en SyncOperationTypes. */
class PlantaFinalizarProduccionSyncHandler(private val api: PlantaApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<PlantaFinalizarProduccionPayload>(payloadJson)
        return try {
            val resultado = api.registrarResultado(
                payload.lote_id,
                RegistrarResultadoRequest(producto_id = payload.producto_id, cantidad_producida = payload.cantidad_producida),
                idempotencyKey = id,
            )
            SyncPushResult.Success(resultado.id)
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}
