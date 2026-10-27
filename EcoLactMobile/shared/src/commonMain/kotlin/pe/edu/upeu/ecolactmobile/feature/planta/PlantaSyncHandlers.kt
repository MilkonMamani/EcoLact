package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult

/** Mensaje del conflicto de una producción iniciada sin conexión que el servidor ya no pudo aceptar. */
const val CONFLICTO_PRODUCCION_OFFLINE = "No se pudo iniciar la producción porque el inventario cambió mientras estabas sin conexión."

/** Producción iniciada sin conexión. [productoNombre]/[cantidad] solo sirven para mostrarla en la lista. */
@Serializable
data class PlantaIniciarProduccionPayload(
    val productos: List<LineaProduccion>,
    val productoNombre: String,
    val cantidad: Double,
    val fechaLocal: String,
)

/**
 * El servidor revalida todo al recibirla (receta activa × cantidad contra el stock REAL): si el
 * inventario cambió y ya no alcanza, responde 422 y la operación queda como conflicto. Nunca se
 * crea el lote "a la fuerza".
 */
class PlantaIniciarProduccionSyncHandler(private val api: PlantaApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PLANTA_INICIAR_PRODUCCION

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<PlantaIniciarProduccionPayload>(payloadJson)
        return try {
            SyncPushResult.Success(api.iniciarProduccion(ProduccionRequest(payload.productos, fecha_hora_evento = payload.fechaLocal), idempotencyKey = id).id)
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(CONFLICTO_PRODUCCION_OFFLINE)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.NotFound) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}

/** Solo se encola sobre un lote que ya existe en el servidor. `obtenido` va por id de detalle. */
@Serializable
data class PlantaFinalizarProduccionPayload(
    val lote_id: Long,
    val obtenido: Map<String, Double>,
    val fecha_hora_evento: String? = null,
)

class PlantaFinalizarProduccionSyncHandler(private val api: PlantaApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<PlantaFinalizarProduccionPayload>(payloadJson)
        return try {
            SyncPushResult.Success(api.finalizarProduccion(payload.lote_id, FinalizarProduccionRequest(payload.obtenido, fecha_hora_evento = payload.fecha_hora_evento), idempotencyKey = id).id)
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.NotFound) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}

@Serializable
data class PlantaBorradorPayload(val borradorId: String, val fechaHoraEvento: String? = null)

/**
 * Foto de boleta tomada sin conexión: al volver internet se sube y el servidor PROPONE las líneas.
 * El borrador queda REQUIERE_REVISION; la compra nunca se registra sola.
 */
class PlantaSubirBoletaSyncHandler(private val api: PlantaApi, private val store: PlantaCompraStore) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PLANTA_SUBIR_BOLETA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<PlantaBorradorPayload>(payloadJson)
        val borrador = store.obtener(payload.borradorId) ?: return SyncPushResult.Success(0)
        val foto = borrador.foto
        if (foto == null || borrador.imagenBoleta != null) return SyncPushResult.Success(0)

        return try {
            val ocr = api.leerBoleta(foto, "boleta-${borrador.id}.jpg", borrador.fotoMime ?: "image/jpeg", borrador.creadoEn)
            store.marcarOcrProcesado(borrador.id, ocr.imagen_boleta, BorradorCompra.desdeOcr(ocr), ocr.mensaje.takeUnless { ocr.success })
            SyncPushResult.Success(0)
        } catch (e: ApiException.Validation) {
            // Foto rechazada (formato/tamaño): la persona puede seguir asociando los insumos a mano.
            store.marcarOcrProcesado(borrador.id, null, borrador.datos, e.message)
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}

/**
 * Compra confirmada sin conexión. Se arma al momento de enviar desde el borrador (así, si la foto
 * se subió antes en la cola, viaja su ruta). 422 = el servidor no la aceptó: el borrador vuelve a
 * "Por revisar" con el motivo, sin tocar el stock.
 */
class PlantaRegistrarCompraSyncHandler(private val api: PlantaApi, private val store: PlantaCompraStore) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PLANTA_REGISTRAR_COMPRA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<PlantaBorradorPayload>(payloadJson)
        val borrador = store.obtener(payload.borradorId) ?: return SyncPushResult.Rejected("La compra ya no está en el teléfono.")

        return try {
            val compra = api.registrarCompra(borrador.datos.toRequest(borrador.imagenBoleta, payload.fechaHoraEvento), idempotencyKey = borrador.id)
            store.eliminar(borrador.id)
            SyncPushResult.Success(compra.id)
        } catch (e: ApiException.Validation) {
            store.guardar(borrador.id, borrador.datos, EstadoBorrador.REQUIERE_REVISION, e.message)
            SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.Forbidden) {
            store.guardar(borrador.id, borrador.datos, EstadoBorrador.REQUIERE_REVISION, e.message)
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}
