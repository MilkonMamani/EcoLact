package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.serialization.decodeFromString
import kotlinx.serialization.encodeToString
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.fetchCached
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.ui.components.ImagenAdjunta
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId
import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class BorradorLocal(val entregaId: Long, val valores: Map<String, Double>, val observacion: String?)

sealed class ConfirmarAnalisisResult {
    data class Confirmado(val analisis: AnalisisBorradorInfo) : ConfirmarAnalisisResult()
    data class GuardadoOffline(val localId: String) : ConfirmarAnalisisResult()
    data class Rechazado(val mensaje: String) : ConfirmarAnalisisResult()
}

sealed class RegistrarEntregaMuestreadaResult {
    data class Confirmada(val entrega: CalidadEntregaMuestreada) : RegistrarEntregaMuestreadaResult()
    data class GuardadaOffline(val localId: String) : RegistrarEntregaMuestreadaResult()
    data class Rechazada(val mensaje: String) : RegistrarEntregaMuestreadaResult()
}

data class AnalisisSyncPendiente(val localId: String, val entregaId: Long, val status: String, val errorMessage: String? = null)

class CalidadRepository(
    private val api: CalidadApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val connectivityObserver: ConnectivityObserver,
    database: EcoLactDatabase,
) {
    private val borradorQueries = database.calidadBorradorQueries

    suspend fun dashboard(): CacheOutcome<CalidadDashboard> = cache.fetchCached("calidad.dashboard") { api.dashboard() }

    suspend fun pendientes(q: String? = null): CacheOutcome<PendientesListResponse> =
        cache.fetchCached("calidad.pendientes.${q.orEmpty()}") { api.pendientes(q = q) }

    suspend fun historial(resultado: String? = null): CacheOutcome<HistorialListResponse> =
        cache.fetchCached("calidad.historial.${resultado.orEmpty()}") { api.historial(resultado = resultado) }

    suspend fun analisisPrevio(entregaId: Long): CacheOutcome<AnalisisPrevioResponse> =
        cache.fetchCached("calidad.analisisPrevio.$entregaId") { api.analisisPrevio(entregaId) }

    suspend fun historialDetalle(analisisId: Long): CacheOutcome<AnalisisDetalleResponse> =
        cache.fetchCached("calidad.historialDetalle.$analisisId") { api.historialDetalle(analisisId) }

    suspend fun proveedores(q: String? = null): CacheOutcome<CalidadProveedoresListResponse> =
        cache.fetchCached("calidad.proveedores.${q.orEmpty()}") { api.proveedores(q = q) }

    suspend fun proveedorDetalle(id: Long): CacheOutcome<CalidadProveedorDetalleResponse> =
        cache.fetchCached("calidad.proveedor.$id") { api.proveedorDetalle(id) }

    suspend fun comunicados(): CacheOutcome<List<CalidadComunicado>> =
        cache.fetchCached("calidad.comunicados") { api.comunicados() }

    suspend fun comunicadoDetalle(id: Long): CacheOutcome<CalidadComunicado> =
        cache.fetchCached("calidad.comunicado.$id") { api.comunicadoDetalle(id) }

    /** Crear comunicado requiere conexión — no es una operación offline documentada para este rol. */
    suspend fun crearComunicado(request: CrearComunicadoRequest): Result<CalidadComunicado> =
        runCatching { api.crearComunicado(request) }

    /**
     * Registrar entrega por muestreo directo (ver CalidadModels.kt) — mismo patrón que
     * AcopiadorRepository.registrarEntrega: intenta en línea primero para mostrar errores de
     * validación al toque, encola con el mismo id (futura Idempotency-Key) si falla por red.
     */
    suspend fun registrarEntregaMuestreada(request: CalidadEntregaMuestreadaRequest): RegistrarEntregaMuestreadaResult {
        val localId = generateLocalId()
        if (!connectivityObserver.isOnline.value) {
            return RegistrarEntregaMuestreadaResult.GuardadaOffline(enqueueEntregaMuestreada(localId, request))
        }
        return try {
            RegistrarEntregaMuestreadaResult.Confirmada(api.registrarEntregaMuestreada(request, idempotencyKey = localId))
        } catch (e: ApiException.Validation) {
            RegistrarEntregaMuestreadaResult.Rechazada(e.message)
        } catch (e: ApiException.Forbidden) {
            RegistrarEntregaMuestreadaResult.Rechazada(e.message)
        } catch (e: Exception) {
            RegistrarEntregaMuestreadaResult.GuardadaOffline(enqueueEntregaMuestreada(localId, request))
        }
    }

    private fun enqueueEntregaMuestreada(localId: String, request: CalidadEntregaMuestreadaRequest): String {
        val payload = CalidadEntregaMuestreadaSyncPayload(request.metodo_identificacion, request.identificador, request.litros, request.observacion)
        syncQueue.enqueueWithId(localId, SyncOperationTypes.CALIDAD_REGISTRAR_ENTREGA_MUESTREADA, ApiJson.encodeToString(payload))
        return localId
    }

    // ---- Borrador local (edición previa a confirmar; no toca la cola de sincronización) ----

    fun guardarBorradorLocal(entregaId: Long, valores: Map<String, Double>, observacion: String?) {
        borradorQueries.upsertBorrador(entregaId, ApiJson.encodeToString(valores), observacion, nowIso())
    }

    fun obtenerBorradorLocal(entregaId: Long): BorradorLocal? {
        val row = borradorQueries.selectBorrador(entregaId).executeAsOneOrNull() ?: return null
        return BorradorLocal(entregaId, ApiJson.decodeFromString(row.valores_json), row.observacion)
    }

    fun eliminarBorradorLocal(entregaId: Long) {
        borradorQueries.deleteBorrador(entregaId)
    }

    /**
     * Confirmar análisis: si hay conexión, intenta de inmediato (POST + PATCH) para mostrar
     * errores de validación al toque; si falla por red o ya se sabe que está offline, encola la
     * operación completa (con la imagen en base64 si la hay) para que el `SyncManager` la envíe
     * después. En cualquier caso, borra el borrador local: ya pasó de "editable" a "confirmado".
     */
    suspend fun confirmarAnalisis(
        entregaId: Long,
        valores: Map<String, Double>,
        observacion: String?,
        imagen: ImagenAdjunta?,
    ): ConfirmarAnalisisResult {
        eliminarBorradorLocal(entregaId)

        if (!connectivityObserver.isOnline.value) {
            return ConfirmarAnalisisResult.GuardadoOffline(enqueue(entregaId, valores, observacion, imagen))
        }
        return try {
            val borrador = api.guardarAnalisis(entregaId, valores, observacion, imagen?.bytes, imagen?.filename)
            val confirmado = api.finalizarAnalisis(borrador.id)
            ConfirmarAnalisisResult.Confirmado(confirmado)
        } catch (e: ApiException.Validation) {
            ConfirmarAnalisisResult.Rechazado(e.message)
        } catch (e: ApiException.Forbidden) {
            ConfirmarAnalisisResult.Rechazado(e.message)
        } catch (e: Exception) {
            ConfirmarAnalisisResult.GuardadoOffline(enqueue(entregaId, valores, observacion, imagen))
        }
    }

    private fun enqueue(entregaId: Long, valores: Map<String, Double>, observacion: String?, imagen: ImagenAdjunta?): String {
        val payload = CalidadAnalisisSyncPayload(
            entrega_id = entregaId,
            valores = valores,
            observacion = observacion,
            imagen_base64 = imagen?.let { Base64Util.encode(it.bytes) },
            imagen_filename = imagen?.filename,
        )
        return syncQueue.enqueue(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS, ApiJson.encodeToString(payload))
    }

    /** Para mostrar en Pendientes qué entregas ya tienen un análisis confirmado localmente, esperando sincronizar. */
    fun analisisPendientesDeSincronizar(): List<AnalisisSyncPendiente> =
        syncQueue.byOperationType(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS)
            .filter { it.status != "SYNCED" }
            .map {
                val payload = ApiJson.decodeFromString<CalidadAnalisisSyncPayload>(it.payload_json)
                AnalisisSyncPendiente(it.id, payload.entrega_id, it.status, it.error_message)
            }
}
