package pe.edu.upeu.ecolactmobile.feature.planta

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

sealed class IniciarProduccionResult {
    data class Confirmado(val lote: LoteCreado) : IniciarProduccionResult()
    data class GuardadoOffline(val localId: String) : IniciarProduccionResult()
    data class Rechazado(val mensaje: String) : IniciarProduccionResult()
}

sealed class RegistrarResultadoOpResult {
    data class Confirmado(val lote: LoteDetalleInfo) : RegistrarResultadoOpResult()
    data class GuardadoOffline(val localId: String) : RegistrarResultadoOpResult()
    data class Rechazado(val mensaje: String) : RegistrarResultadoOpResult()
}

data class LotePendienteView(val localId: String, val payload: PlantaIniciarProduccionPayload, val status: String, val errorMessage: String? = null)
data class ResultadoPendienteView(val localId: String, val loteId: Long, val status: String, val errorMessage: String? = null)

class PlantaRepository(
    private val api: PlantaApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val connectivityObserver: ConnectivityObserver,
) {
    suspend fun dashboard(): CacheOutcome<PlantaDashboard> = cache.fetchCached("planta.dashboard") { api.dashboard() }

    suspend fun produccion(estado: String? = null): CacheOutcome<ProduccionListResponse> =
        cache.fetchCached("planta.produccion.${estado.orEmpty()}") { api.produccion(estado) }

    suspend fun lecheDisponible(): CacheOutcome<LecheDisponibleResponse> =
        cache.fetchCached("planta.lecheDisponible") { api.lecheDisponible() }

    suspend fun loteDetalle(loteId: Long): CacheOutcome<LoteDetalleResponse> =
        cache.fetchCached("planta.lote.$loteId") { api.loteDetalle(loteId) }

    suspend fun insumos(nivel: String? = null): CacheOutcome<InsumosListResponse> =
        cache.fetchCached("planta.insumos.${nivel.orEmpty()}") { api.insumos(nivel = nivel) }

    suspend fun insumoDetalle(id: Long): CacheOutcome<InsumoDetalleResponse> =
        cache.fetchCached("planta.insumo.$id") { api.insumoDetalle(id) }

    suspend fun productos(buscar: String? = null): CacheOutcome<ProductosListResponse> =
        cache.fetchCached("planta.productos.${buscar.orEmpty()}") { api.productos(buscar) }

    suspend fun productoDetalle(id: Long): CacheOutcome<ProductoDetalleResponse> =
        cache.fetchCached("planta.producto.$id") { api.productoDetalle(id) }

    /** Registrar/ajustar insumos es online-only en esta versión (no es el flujo central de producción que sí pide offline el mockup). */
    suspend fun crearInsumo(request: CrearInsumoRequest): Result<InsumoFila> = runCatching { api.crearInsumo(request) }

    /** Con Idempotency-Key propia (generada por intento) aunque sea online-only: un timeout ambiguo en esta llamada no debe poder duplicar el movimiento de stock si el usuario reintenta. */
    suspend fun registrarMovimiento(insumoId: Long, request: RegistrarMovimientoRequest): Result<InsumoFila> =
        runCatching { api.registrarMovimiento(insumoId, request, idempotencyKey = generateLocalId()) }

    // ---- Iniciar producción (offline-capaz) ----

    suspend fun iniciarProduccion(request: IniciarProduccionRequest): IniciarProduccionResult {
        val localId = generateLocalId()
        if (!connectivityObserver.isOnline.value) {
            return IniciarProduccionResult.GuardadoOffline(enqueueIniciar(localId, request))
        }
        return try {
            IniciarProduccionResult.Confirmado(api.iniciarProduccion(request, idempotencyKey = localId))
        } catch (e: ApiException.Validation) {
            IniciarProduccionResult.Rechazado(e.message)
        } catch (e: ApiException.Forbidden) {
            IniciarProduccionResult.Rechazado(e.message)
        } catch (e: Exception) {
            IniciarProduccionResult.GuardadoOffline(enqueueIniciar(localId, request))
        }
    }

    private fun enqueueIniciar(localId: String, request: IniciarProduccionRequest): String {
        val payload = PlantaIniciarProduccionPayload(request.litros_utilizados, request.fecha_hora_inicio, request.observacion)
        syncQueue.enqueueWithId(localId, SyncOperationTypes.PLANTA_INICIAR_PRODUCCION, ApiJson.encodeToString(payload))
        return localId
    }

    fun lotesPendientesDeSincronizar(): List<LotePendienteView> =
        syncQueue.byOperationType(SyncOperationTypes.PLANTA_INICIAR_PRODUCCION)
            .filter { it.status != "SYNCED" }
            .map { LotePendienteView(it.id, ApiJson.decodeFromString(it.payload_json), it.status, it.error_message) }

    // ---- Registrar resultado (offline-capaz, pero solo sobre un lote con id real de servidor) ----

    suspend fun registrarResultado(loteId: Long, request: RegistrarResultadoRequest): RegistrarResultadoOpResult {
        val localId = generateLocalId()
        if (!connectivityObserver.isOnline.value) {
            return RegistrarResultadoOpResult.GuardadoOffline(enqueueResultado(localId, loteId, request))
        }
        return try {
            RegistrarResultadoOpResult.Confirmado(api.registrarResultado(loteId, request, idempotencyKey = localId))
        } catch (e: ApiException.Validation) {
            RegistrarResultadoOpResult.Rechazado(e.message)
        } catch (e: ApiException.Forbidden) {
            RegistrarResultadoOpResult.Rechazado(e.message)
        } catch (e: Exception) {
            RegistrarResultadoOpResult.GuardadoOffline(enqueueResultado(localId, loteId, request))
        }
    }

    private fun enqueueResultado(localId: String, loteId: Long, request: RegistrarResultadoRequest): String {
        val payload = PlantaFinalizarProduccionPayload(loteId, request.producto_id, request.cantidad_producida)
        syncQueue.enqueueWithId(localId, SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION, ApiJson.encodeToString(payload))
        return localId
    }

    fun resultadosPendientesDeSincronizar(): List<ResultadoPendienteView> =
        syncQueue.byOperationType(SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION)
            .filter { it.status != "SYNCED" }
            .map {
                val payload = ApiJson.decodeFromString<PlantaFinalizarProduccionPayload>(it.payload_json)
                ResultadoPendienteView(it.id, payload.lote_id, it.status, it.error_message)
            }
}
