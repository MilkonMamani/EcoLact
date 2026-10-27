package pe.edu.upeu.ecolactmobile.feature.notificaciones

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
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

/**
 * Notificaciones offline-first: la lista vive en SQLite (por usuario) y la UI la observa.
 * Marcar como leída actualiza la copia local al instante; el PATCH al servidor se envía en el
 * momento si hay red, o queda en la cola de sincronización hasta que vuelva.
 */
class NotificacionesRepository(
    private val api: NotificacionesApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val userId: Long,
    private val isOnline: () -> Boolean,
) {
    private val key = "notificaciones.$userId"

    fun observe(): Flow<CachedValue<List<NotificacionItem>>?> = cache.observe(key)

    fun observeNoLeidas(): Flow<Int> = observe().map { cached -> cached?.value?.count { !it.leido } ?: 0 }

    /** Descarga la lista; lo que se leyó offline y aún no llegó al servidor se mantiene como leído. */
    suspend fun refresh() {
        val pendientes = syncQueue.lecturasPendientes(SyncOperationTypes.NOTIFICACION_MARCAR_LEIDA, userId)
        val lista = api.listar().map { if (it.id in pendientes) it.copy(leido = true) else it }
        cache.put(key, lista)
    }

    suspend fun marcarLeida(id: Long) {
        val actual = runCatching { cache.get<List<NotificacionItem>>(key) }.getOrNull()?.value ?: return
        if (actual.none { it.id == id && !it.leido }) return
        cache.put(key, actual.map { if (it.id == id) it.copy(leido = true) else it })

        val enviada = isOnline() && runCatching { api.marcarLeida(id) }.isSuccess
        if (!enviada) syncQueue.encolarLectura(SyncOperationTypes.NOTIFICACION_MARCAR_LEIDA, id, userId)
    }
}

/** Envía al reconectar las notificaciones marcadas como leídas sin conexión. */
class NotificacionLeidaSyncHandler(private val api: NotificacionesApi) : SyncOperationHandler {
    override val operationType = SyncOperationTypes.NOTIFICACION_MARCAR_LEIDA

    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val lectura = ApiJson.decodeFromString<LecturaPendiente>(payloadJson)
        return try {
            api.marcarLeida(lectura.id)
            SyncPushResult.Success(lectura.id)
        } catch (e: ApiException.NotFound) {
            // La notificación ya no existe en el servidor: no queda nada por marcar.
            SyncPushResult.Success(lectura.id)
        } catch (e: ApiException.Forbidden) {
            SyncPushResult.Rejected(e.message)
        } catch (e: Exception) {
            SyncPushResult.TransientFailure(e.message ?: "Error de red")
        }
    }
}
