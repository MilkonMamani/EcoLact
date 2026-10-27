package pe.edu.upeu.ecolactmobile.core.sync

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.database.Sync_queue
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId
import pe.edu.upeu.ecolactmobile.core.util.nowIso

class SyncQueueRepository(database: EcoLactDatabase) {
    private val queries = database.syncQueueQueries

    /** Encola una nueva operación offline y devuelve el id local generado (aún sin id de servidor). */
    fun enqueue(operationType: String, payloadJson: String): String {
        val id = generateLocalId()
        enqueueWithId(id, operationType, payloadJson)
        return id
    }

    /**
     * Igual que [enqueue], pero con un id elegido de antemano — para cuando un intento en línea
     * ya generó ese id y lo usó como `Idempotency-Key` antes de fallar por red: la fila que cae a
     * la cola debe reutilizar exactamente esa misma clave en sus reintentos, no una nueva.
     */
    fun enqueueWithId(id: String, operationType: String, payloadJson: String) {
        val now = nowIso()
        queries.insertOperation(id, operationType, payloadJson, now, now)
    }

    fun pending(): List<Sync_queue> = queries.selectPending().executeAsList()

    /** Toda la cola, re-emitida en cada cambio (para pantallas de estado de sincronización). */
    fun observeAll(): Flow<List<Sync_queue>> = queries.selectAll().asFlow().mapToList(Dispatchers.Default)
    fun needsReview(): List<Sync_queue> = queries.selectNeedsReview().executeAsList()
    fun all(): List<Sync_queue> = queries.selectAll().executeAsList()
    fun byOperationType(operationType: String): List<Sync_queue> =
        queries.selectByOperationType(operationType).executeAsList()

    fun pendingCount(): Long = queries.countPending().executeAsOne()

    fun markSyncing(id: String) = queries.markSyncing(nowIso(), id)

    /** Nunca reintentar un registro que ya tiene id de servidor (sección 3 del prompt inicial). */
    fun markSynced(id: String, serverId: Long) = queries.markSynced(serverId, nowIso(), id)

    fun markPending(id: String) = queries.markPending(nowIso(), id)

    /** Solo aplica mientras la fila siga PENDING (nunca sobre una que ya está SYNCING/SYNCED). */
    fun updatePayload(id: String, payloadJson: String) = queries.updatePayload(payloadJson, nowIso(), id)

    /** Corrige el payload de una fila NEEDS_REVIEW y la vuelve a poner en la cola (PENDING). */
    fun retryFromReview(id: String, payloadJson: String) = queries.retryFromReview(payloadJson, nowIso(), id)

    fun markNeedsReview(id: String, message: String) = queries.markNeedsReview(message, nowIso(), id)

    fun delete(id: String) = queries.deleteOperation(id)
}
