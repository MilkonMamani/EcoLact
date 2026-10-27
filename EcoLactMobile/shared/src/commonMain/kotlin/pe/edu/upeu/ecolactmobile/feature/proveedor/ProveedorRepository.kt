package pe.edu.upeu.ecolactmobile.feature.proveedor

import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.flow.Flow
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.LecturaPendiente
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.sync.encolarLectura
import pe.edu.upeu.ecolactmobile.core.sync.lecturasPendientes
import pe.edu.upeu.ecolactmobile.core.util.nowIso

/**
 * Fuente de datos offline-first del proveedor, patrón "API → SQLite → UI":
 * - `observeX()` lee la copia local (tabla `cache_entry`, una fila por endpoint) y vuelve a
 *   emitir sola cada vez que esa copia cambia. Las pantallas solo observan esto.
 * - `refreshX()` consulta el backend y reemplaza la copia local. Lo llaman la sincronización
 *   automática ([ProveedorSync]) y los detalles al abrirse; nunca la pantalla directamente.
 *
 * El proveedor es un rol de consulta: la única "escritura" es marcar un comunicado como leído,
 * que sin conexión se encola en `sync_queue` ([ProveedorLeerComunicadoSyncHandler]).
 *
 * Las claves llevan el id del usuario: si dos proveedores usan el mismo teléfono, uno nunca ve la
 * copia guardada del otro.
 */
class ProveedorRepository(
    private val api: ProveedorApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val userId: Long,
    private val isOnline: () -> Boolean,
) {
    private fun key(suffix: String) = "proveedor.$userId.$suffix"

    private inline fun <reified T> observe(suffix: String): Flow<CachedValue<T>?> = cache.observe(key(suffix))

    private suspend inline fun <reified T> refresh(suffix: String, fetch: () -> T): T =
        fetch().also { cache.put(key(suffix), it) }

    fun observeDashboard(): Flow<CachedValue<ProveedorDashboard>?> = observe("dashboard")

    /** Los KPIs de Inicio son de la semana en curso (el backend sin `periodo` devuelve el histórico). */
    suspend fun refreshDashboard() {
        refresh("dashboard") { api.dashboard(periodo = "esta_semana") }
    }

    fun observeQr(): Flow<CachedValue<MiQrResponse>?> = observe("qr")
    suspend fun refreshQr() {
        refresh("qr") { api.miQr() }
    }

    fun observePerfil(): Flow<CachedValue<ProveedorPerfil>?> = observe("perfil")
    suspend fun refreshPerfil() {
        refresh("perfil") { api.perfil() }
    }

    /** Siempre la lista completa: los filtros (semana, estado) se aplican localmente y funcionan offline. */
    fun observeEntregas(): Flow<CachedValue<EntregasListResponse>?> = observe("entregas")
    suspend fun refreshEntregas(): EntregasListResponse = refresh("entregas") { api.entregas() }

    fun observeEntregaDetalle(id: Long): Flow<CachedValue<EntregaDetalleResponse>?> = observe("entrega.$id")
    suspend fun refreshEntregaDetalle(id: Long) {
        refresh("entrega.$id") { api.entregaDetalle(id) }
    }

    fun observeLiquidaciones(): Flow<CachedValue<LiquidacionesListResponse>?> = observe("liquidaciones")
    suspend fun refreshLiquidaciones(): LiquidacionesListResponse = refresh("liquidaciones") { api.liquidaciones() }

    fun observeLiquidacionDetalle(id: Long): Flow<CachedValue<LiquidacionDetalleResponse>?> = observe("liquidacion.$id")
    suspend fun refreshLiquidacionDetalle(id: Long) {
        refresh("liquidacion.$id") { api.liquidacionDetalle(id) }
    }

    fun observeAnalisis(): Flow<CachedValue<AnalisisListResponse>?> = observe("analisis")
    suspend fun refreshAnalisis(): AnalisisListResponse = refresh("analisis") { api.analisis() }

    fun observeAnalisisDetalle(id: Long): Flow<CachedValue<AnalisisDetalleResponse>?> = observe("analisisDetalle.$id")
    suspend fun refreshAnalisisDetalle(id: Long) {
        refresh("analisisDetalle.$id") { api.analisisDetalle(id) }
    }

    fun observeCompras(): Flow<CachedValue<ComprasListResponse>?> = observe("compras")
    suspend fun refreshCompras(): ComprasListResponse = refresh("compras") { api.compras() }

    fun observeCompraDetalle(id: Long): Flow<CachedValue<CompraDetalleResponse>?> = observe("compra.$id")
    suspend fun refreshCompraDetalle(id: Long) {
        refresh("compra.$id") { api.compraDetalle(id) }
    }

    fun observeComunicados(): Flow<CachedValue<ComunicadosListResponse>?> = observe("comunicados")

    /** Lo leído offline y aún no confirmado por el servidor se mantiene como leído. */
    suspend fun refreshComunicados() {
        val pendientes = syncQueue.lecturasPendientes(SyncOperationTypes.PROVEEDOR_LEER_COMUNICADO, userId)
        val lista = api.comunicados()
        cache.put(
            key("comunicados"),
            lista.copy(filas = lista.filas.map { if (it.id in pendientes) it.copy(leido = true) else it }),
        )
    }

    fun observeComunicadoDetalle(id: Long): Flow<CachedValue<ComunicadoDetalleResponse>?> = observe("comunicado.$id")

    /**
     * Abrir un comunicado lo marca como leído. El backend lo hace al pedir el detalle (GET con
     * efecto secundario); sin conexión se marca en la copia local y el GET queda en la cola.
     */
    suspend fun abrirComunicado(id: Long) {
        marcarComunicadoLeidoLocal(id)
        if (!isOnline()) {
            syncQueue.encolarLectura(SyncOperationTypes.PROVEEDOR_LEER_COMUNICADO, id, userId)
            return
        }
        try {
            refresh("comunicado.$id") { api.comunicadoDetalle(id) }
        } catch (e: Exception) {
            syncQueue.encolarLectura(SyncOperationTypes.PROVEEDOR_LEER_COMUNICADO, id, userId)
            throw e
        }
    }

    private fun marcarComunicadoLeidoLocal(id: Long) {
        val actual = runCatching { cache.get<ComunicadosListResponse>(key("comunicados")) }.getOrNull()?.value ?: return
        if (actual.filas.none { it.id == id && it.leido == false }) return
        cache.put(
            key("comunicados"),
            actual.copy(
                filas = actual.filas.map { if (it.id == id) it.copy(leido = true) else it },
                kpis = actual.kpis.copy(no_leidos = (actual.kpis.no_leidos - 1).coerceAtLeast(0)),
            ),
        )
    }

    fun observeUltimaSincronizacion(): Flow<CachedValue<String>?> = observe("ultimaSincronizacion")

    /**
     * Descarga y guarda localmente todo lo que el proveedor puede consultar, para que la app siga
     * siendo navegable aunque luego se pierda la señal en campo. Los detalles se limitan a los
     * [DETALLES_RECIENTES] más recientes de cada tipo para no saturar una señal débil (el resto se
     * descarga al abrirlos).
     *
     * @return null si todo terminó bien; si no, el primer error (lo ya descargado queda guardado).
     */
    suspend fun refreshAll(): Throwable? = coroutineScope {
        val resultados = listOf(
            async { runCatching { refreshDashboard() } },
            async { runCatching { refreshQr() } },
            async { runCatching { refreshPerfil() } },
            async {
                runCatching { refreshEntregas() }
                    .onSuccess { r -> r.filas.take(DETALLES_RECIENTES).forEach { runCatching { refreshEntregaDetalle(it.id) } } }
            },
            async {
                runCatching { refreshLiquidaciones() }
                    .onSuccess { r -> r.filas.take(DETALLES_RECIENTES).forEach { runCatching { refreshLiquidacionDetalle(it.id) } } }
            },
            async {
                runCatching { refreshAnalisis() }
                    .onSuccess { r -> r.filas.take(DETALLES_RECIENTES).forEach { runCatching { refreshAnalisisDetalle(it.id) } } }
            },
            async { runCatching { refreshCompras() } },
            async { runCatching { refreshComunicados() } },
        ).awaitAll()
        val error = resultados.firstNotNullOfOrNull { it.exceptionOrNull() }
        if (error == null) cache.put(key("ultimaSincronizacion"), nowIso())
        error
    }

    private companion object {
        const val DETALLES_RECIENTES = 5
    }
}

/** Repite al reconectar el GET que marca como leído un comunicado abierto sin conexión. */
class ProveedorLeerComunicadoSyncHandler(private val api: ProveedorApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.PROVEEDOR_LEER_COMUNICADO

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val lectura = ApiJson.decodeFromString<LecturaPendiente>(payloadJson)
        return try {
            api.comunicadoDetalle(lectura.id)
            SyncPushResult.Success(lectura.id)
        } catch (e: ApiException.NotFound) {
            // Comunicado retirado: no queda nada por marcar.
            SyncPushResult.Success(lectura.id)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}
