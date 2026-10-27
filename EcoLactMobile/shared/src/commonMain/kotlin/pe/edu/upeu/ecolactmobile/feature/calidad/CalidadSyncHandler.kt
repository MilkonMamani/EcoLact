package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.serialization.Serializable
import kotlinx.serialization.decodeFromString
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult

/** Errores de negocio (no se reintentan solos) frente a fallas de red (se reintentan). */
private inline fun empujar(block: () -> SyncPushResult): SyncPushResult = try {
    block()
} catch (e: ApiException.Conflict) {
    SyncPushResult.Rejected(e.message)
} catch (e: ApiException.Validation) {
    SyncPushResult.Rejected(e.message)
} catch (e: ApiException.Forbidden) {
    SyncPushResult.Rejected(e.message)
} catch (e: ApiException.NotFound) {
    SyncPushResult.Rejected(e.message)
} catch (e: Exception) {
    SyncPushResult.TransientFailure(e.message ?: "Error de red")
}

/**
 * Payload guardado en `sync_queue` para un análisis confirmado sin conexión. La foto va en base64
 * dentro del mismo JSON (si el OCR ya la subió, basta con [ticket_path]). [fecha_analisis] y
 * [fecha_captura_local] son la hora REAL en el teléfono, distinta de la de sincronización.
 */
@Serializable
data class CalidadAnalisisSyncPayload(
    val entrega_id: Long,
    val valores: Map<String, Double>,
    val observacion: String? = null,
    val imagen_base64: String? = null,
    val imagen_filename: String? = null,
    val origenes: Map<String, String> = emptyMap(),
    val ticket_path: String? = null,
    val fecha_analisis: String? = null,
    val fecha_captura_local: String? = null,
)

/**
 * Ejecuta guardarAnalisis (POST) y finalizarAnalisis (PATCH) como una sola operación lógica
 * (calidad.txt sección 23): si el POST tiene éxito pero el PATCH falla por red, la fila queda
 * PENDING y el próximo intento hace POST de nuevo (el backend acepta reenviar valores mientras
 * el análisis siga en borrador) y luego el PATCH — no hay estado intermedio expuesto al usuario.
 */
class CalidadAnalisisSyncHandler(
    private val api: CalidadApi,
    private val capturas: CalidadCapturaStore? = null,
) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS

    /** Sin Idempotency-Key: `guardarAnalisis`/`finalizarAnalisis` no están en la lista de endpoints que el backend protege (ver PROMPT_INICIAL.md sección 3) — [id] no se usa aquí. */
    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<CalidadAnalisisSyncPayload>(payloadJson)
        return empujar {
            val imagenBytes = payload.imagen_base64?.let { Base64Util.decode(it) }
            val borrador = api.guardarAnalisis(
                entregaId = payload.entrega_id,
                valores = payload.valores,
                observacion = payload.observacion,
                imagenBytes = imagenBytes,
                imagenFilename = payload.imagen_filename,
                origenes = payload.origenes,
                ticketPath = payload.ticket_path,
                fechaAnalisis = payload.fecha_analisis,
                fechaCapturaLocal = payload.fecha_captura_local,
            )
            val confirmado = api.finalizarAnalisis(borrador.id, payload.fecha_analisis)
            capturas?.eliminar(payload.entrega_id)
            SyncPushResult.Success(confirmado.id)
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
    val fecha_hora_evento: String? = null,
)

class CalidadEntregaMuestreadaSyncHandler(private val api: CalidadApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.CALIDAD_REGISTRAR_ENTREGA_MUESTREADA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<CalidadEntregaMuestreadaSyncPayload>(payloadJson)
        return empujar {
            val entrega = api.registrarEntregaMuestreada(
                CalidadEntregaMuestreadaRequest(payload.metodo_identificacion, payload.identificador, payload.litros, payload.observacion, payload.fecha_hora_evento),
                idempotencyKey = id,
            )
            SyncPushResult.Success(entrega.id)
        }
    }
}

/**
 * Selección de muestra hecha sin conexión. [entrega] es la foto de la fila que vio el analista,
 * para seguir mostrándola en "Muestras pendientes" mientras no se sincroniza.
 */
@Serializable
data class CalidadSeleccionMuestraSyncPayload(
    val request: SeleccionarMuestraRequest,
    val entrega: EntregaElegible,
)

/**
 * Envía la selección. Si otro analista ya eligió esa entrega el servidor responde 409: la fila
 * queda NEEDS_REVIEW con "Esta entrega ya fue seleccionada para análisis." y nunca se duplica.
 */
class CalidadSeleccionarMuestraSyncHandler(private val api: CalidadApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<CalidadSeleccionMuestraSyncPayload>(payloadJson)
        return empujar { SyncPushResult.Success(api.seleccionarMuestra(payload.request, idempotencyKey = id).id) }
    }
}

@Serializable
data class CalidadSubirTicketSyncPayload(val entrega_id: Long)

/**
 * Sube la foto del ticket tomada sin conexión para que el servidor la lea (OCR). El resultado
 * solo se PROPONE: la captura pasa de PENDIENTE_OCR a REQUIERE_REVISION y el analista debe
 * revisarla y confirmarla a mano. Si la foto ya no está (se confirmó a mano) no hay nada que hacer.
 */
class CalidadSubirTicketSyncHandler(
    private val api: CalidadApi,
    private val capturas: CalidadCapturaStore,
) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.CALIDAD_SUBIR_TICKET

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<CalidadSubirTicketSyncPayload>(payloadJson)
        val captura = capturas.obtener(payload.entrega_id)
        val foto = captura?.foto
        if (captura == null || captura.estado != EstadoCaptura.PENDIENTE_OCR || foto == null) {
            return SyncPushResult.Success(payload.entrega_id)
        }
        val resultado = empujar {
            val ocr = api.escanearTicket(payload.entrega_id, foto, nombreFoto(captura.fotoMime), captura.fotoMime ?: "image/jpeg", captura.fechaCapturaLocal)
            capturas.marcarOcrProcesado(payload.entrega_id, ocr.ticket_path, ocr.valores.associate { it.codigo to it.valor }, ocr.mensaje)
            SyncPushResult.Success(payload.entrega_id)
        }
        if (resultado is SyncPushResult.Rejected) {
            // Sin lectura automática: el analista ingresa los valores a mano (la foto se conserva).
            capturas.marcarOcrProcesado(payload.entrega_id, null, emptyMap(), "No se pudo leer el ticket automáticamente. ${resultado.message}")
        }
        return resultado
    }
}

internal fun nombreFoto(mime: String?): String = if (mime?.contains("png") == true) "ticket.png" else "ticket.jpg"
