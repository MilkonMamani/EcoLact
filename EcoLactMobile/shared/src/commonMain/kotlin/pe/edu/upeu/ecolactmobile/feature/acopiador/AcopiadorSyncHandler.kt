package pe.edu.upeu.ecolactmobile.feature.acopiador

import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult

/**
 * Traduce el resultado de una llamada al backend al de la cola: 422/403/404 son definitivos
 * ("Error de sincronización", la fila queda guardada para revisión, nunca se borra sola);
 * red/5xx se reintentan en el próximo ciclo.
 */
private suspend fun enviar(bloque: suspend () -> Long): SyncPushResult = try {
    SyncPushResult.Success(bloque())
} catch (e: ApiException.Validation) {
    SyncPushResult.Rejected(e.message)
} catch (e: ApiException.Forbidden) {
    SyncPushResult.Rejected(e.message)
} catch (e: ApiException.NotFound) {
    SyncPushResult.Rejected("El registro ya no existe en el servidor.")
} catch (e: Exception) {
    SyncPushResult.TransientFailure(e.message ?: "Error de red")
}

/**
 * Entrega guardada sin conexión. [id] (clave de la fila en `sync_queue`) viaja como
 * `Idempotency-Key`: el backend la guarda como token de la entrega, así que si la respuesta se
 * pierde y se reintenta, devuelve la misma entrega (Pendiente → Sincronizada, sin duplicado).
 * La hora enviada es la del acopio, no la de sincronización.
 */
class AcopiadorEntregaSyncHandler(private val api: AcopiadorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = decodificarEntregaLocal(payloadJson) ?: return SyncPushResult.Rejected("Registro dañado en el teléfono.")
        return enviar { enviarEntregaLocal(api, id, payload) }
    }
}

/** Acopio en una parada de recorrido: mismo envío, con `token_idempotencia` = id local. */
class AcopiadorAcopioParadaSyncHandler(private val api: AcopiadorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.ACOPIADOR_ACOPIO_PARADA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = decodificarEntregaLocal(payloadJson)?.takeIf { it.parada != null }
            ?: return SyncPushResult.Rejected("Registro dañado en el teléfono.")
        return enviar { enviarEntregaLocal(api, id, payload) }
    }
}

/** "No entregó" (idempotente en el servidor: reenviarlo no cambia nada). */
class AcopiadorNoEntregoSyncHandler(private val api: AcopiadorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.ACOPIADOR_NO_ENTREGO

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val p = ApiJson.decodeFromString<NoEntregoPayload>(payloadJson)
        return enviar { api.noEntrego(p.recorrido_id, p.parada_id, p.motivo, p.fecha_hora_evento).parada.id }
    }
}

/** Iniciar recorrido (idempotente en el servidor). */
class AcopiadorIniciarRecorridoSyncHandler(private val api: AcopiadorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.ACOPIADOR_INICIAR_RECORRIDO

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val p = ApiJson.decodeFromString<RecorridoRefPayload>(payloadJson)
        return enviar { api.iniciarRecorrido(p.recorrido_id, p.fecha_hora_evento).id }
    }
}

/** Comunicado abierto sin conexión: el GET del detalle lo marca leído en el servidor. */
class AcopiadorLeerComunicadoSyncHandler(private val api: AcopiadorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.ACOPIADOR_LEER_COMUNICADO

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val p = ApiJson.decodeFromString<ComunicadoRefPayload>(payloadJson)
        return enviar { api.comunicadoDetalle(p.comunicado_id).id }
    }
}
