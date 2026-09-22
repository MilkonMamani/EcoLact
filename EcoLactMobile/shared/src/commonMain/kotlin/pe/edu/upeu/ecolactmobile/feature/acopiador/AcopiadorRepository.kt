package pe.edu.upeu.ecolactmobile.feature.acopiador

import kotlinx.serialization.decodeFromString
import kotlinx.serialization.encodeToString
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.fetchCached
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId

sealed class RegistrarEntregaResult {
    data class Confirmada(val entrega: AcopiadorEntrega) : RegistrarEntregaResult()
    data class GuardadaOffline(val localId: String) : RegistrarEntregaResult()
    data class Rechazada(val mensaje: String) : RegistrarEntregaResult()
}

data class PendingEntregaView(
    val localId: String,
    val request: AcopiadorEntregaRequest,
    val status: String,
    val createdAtIso: String,
    val errorMessage: String? = null,
)

class AcopiadorRepository(
    private val api: AcopiadorApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val connectivityObserver: ConnectivityObserver,
) {
    suspend fun dashboard(): CacheOutcome<AcopiadorDashboard> =
        cache.fetchCached("acopiador.dashboard") { api.dashboard() }

    suspend fun entregas(estado: String? = null): CacheOutcome<AcopiadorEntregasListResponse> =
        cache.fetchCached("acopiador.entregas.${estado.orEmpty()}") { api.entregas(estado = estado) }

    suspend fun entregaDetalle(id: Long): CacheOutcome<AcopiadorEntregaDetalleResponse> =
        cache.fetchCached("acopiador.entrega.$id") { api.entregaDetalle(id) }

    suspend fun seguimientoCalidad(): CacheOutcome<SeguimientoListResponse> =
        cache.fetchCached("acopiador.seguimiento") { api.seguimientoCalidad() }

    suspend fun seguimientoDetalle(entregaId: Long): CacheOutcome<SeguimientoDetalleResponse> =
        cache.fetchCached("acopiador.seguimientoDetalle.$entregaId") { api.seguimientoDetalle(entregaId) }

    suspend fun proveedores(buscar: String? = null): CacheOutcome<AcopiadorProveedoresListResponse> =
        cache.fetchCached("acopiador.proveedores.${buscar.orEmpty()}") { api.proveedores(buscar = buscar) }

    suspend fun proveedorDetalle(id: Long): CacheOutcome<AcopiadorProveedorDetalleResponse> =
        cache.fetchCached("acopiador.proveedor.$id") { api.proveedorDetalle(id) }

    suspend fun comunicados(): CacheOutcome<List<AcopiadorComunicado>> =
        cache.fetchCached("acopiador.comunicados") { api.comunicados() }

    suspend fun comunicadoDetalle(id: Long): CacheOutcome<AcopiadorComunicado> =
        cache.fetchCached("acopiador.comunicado.$id") { api.comunicadoDetalle(id) }

    /**
     * Único punto de escritura del rol. Si hay conexión, intenta confirmar de inmediato (para
     * poder mostrar errores de validación al toque, ej. "proveedor fuera de zona"); si la llamada
     * falla por red, o si ya se sabe que está offline, la entrega se guarda en la cola local.
     * Un 422/403 nunca se guarda como pendiente: se le muestra al acopiador de inmediato para que
     * corrija (ej. DNI equivocado), tal como pide acopiador.txt sección "Proveedor fuera de zona".
     *
     * El id local (futura `Idempotency-Key`) se genera ANTES del intento en línea, no solo al
     * encolar: así, si la petición llega al servidor pero la respuesta se pierde, el reintento
     * desde la cola reutiliza la misma clave y el backend no duplica la entrega.
     */
    suspend fun registrarEntrega(request: AcopiadorEntregaRequest): RegistrarEntregaResult {
        val localId = generateLocalId()
        if (!connectivityObserver.isOnline.value) {
            return RegistrarEntregaResult.GuardadaOffline(enqueue(localId, request))
        }
        return try {
            RegistrarEntregaResult.Confirmada(api.registrarEntrega(request, idempotencyKey = localId))
        } catch (e: ApiException.Validation) {
            RegistrarEntregaResult.Rechazada(e.message)
        } catch (e: ApiException.Forbidden) {
            RegistrarEntregaResult.Rechazada(e.message)
        } catch (e: Exception) {
            RegistrarEntregaResult.GuardadaOffline(enqueue(localId, request))
        }
    }

    private fun enqueue(localId: String, request: AcopiadorEntregaRequest): String {
        syncQueue.enqueueWithId(localId, SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA, ApiJson.encodeToString(request))
        return localId
    }

    /** Entregas guardadas localmente que aún no fueron confirmadas por el servidor (o quedaron en revisión). */
    fun entregasPendientes(): List<PendingEntregaView> =
        syncQueue.byOperationType(SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA)
            .filter { it.status != "SYNCED" }
            .map { row ->
                PendingEntregaView(
                    localId = row.id,
                    request = ApiJson.decodeFromString(row.payload_json),
                    status = row.status,
                    createdAtIso = row.created_at,
                    errorMessage = row.error_message,
                )
            }

    /** Solo permitido mientras la fila siga PENDING — ver acopiador.txt sección 7: una vez sincronizada, ya no se edita desde la app. */
    fun editarEntregaPendiente(localId: String, nuevaRequest: AcopiadorEntregaRequest) {
        syncQueue.updatePayload(localId, ApiJson.encodeToString(nuevaRequest))
    }

    /** Corrige una entrega que quedó NEEDS_REVIEW (ej. DNI equivocado) y la vuelve a encolar. */
    fun corregirYReintentar(localId: String, nuevaRequest: AcopiadorEntregaRequest) {
        syncQueue.retryFromReview(localId, ApiJson.encodeToString(nuevaRequest))
    }

    fun eliminarEntregaPendiente(localId: String) {
        syncQueue.delete(localId)
    }
}
