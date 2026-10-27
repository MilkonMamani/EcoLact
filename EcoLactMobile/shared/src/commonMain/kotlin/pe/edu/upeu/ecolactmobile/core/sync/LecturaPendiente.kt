package pe.edu.upeu.ecolactmobile.core.sync

import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.network.ApiJson

/**
 * Payload de las operaciones "marcar como leído" hechas sin conexión (notificaciones,
 * comunicados). Lleva el usuario para no mezclar la cola de dos cuentas en el mismo teléfono.
 */
@Serializable
data class LecturaPendiente(val id: Long, val user_id: Long)

/** Ids que el usuario ya leyó localmente pero el servidor todavía no sabe (PENDING o SYNCING). */
fun SyncQueueRepository.lecturasPendientes(operationType: String, userId: Long): Set<Long> =
    byOperationType(operationType)
        .filter { it.status == SyncStatus.PENDING.name || it.status == SyncStatus.SYNCING.name }
        .mapNotNull { runCatching { ApiJson.decodeFromString<LecturaPendiente>(it.payload_json) }.getOrNull() }
        .filter { it.user_id == userId }
        .map { it.id }
        .toSet()

/** Encola la lectura salvo que ya esté pendiente (tocar dos veces offline no duplica la operación). */
fun SyncQueueRepository.encolarLectura(operationType: String, id: Long, userId: Long) {
    if (id in lecturasPendientes(operationType, userId)) return
    enqueue(operationType, ApiJson.encodeToString(LecturaPendiente(id, userId)))
}
