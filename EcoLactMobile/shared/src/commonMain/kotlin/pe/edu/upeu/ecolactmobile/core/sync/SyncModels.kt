package pe.edu.upeu.ecolactmobile.core.sync

/** Estado de una operación en la cola de sincronización local. */
enum class SyncStatus {
    PENDING,
    SYNCING,
    SYNCED,
    NEEDS_REVIEW,
}

/**
 * Un tipo de operación offline soportada. Cada rol con escritura offline (ver cada .md, sección
 * "Offline") registra un [SyncOperationHandler] para el tipo que le corresponde. De momento solo
 * ACOPIADOR tiene una operación de escritura offline definida (registrar entrega); los demás roles
 * se añaden a medida que se construyen sus pantallas.
 */
object SyncOperationTypes {
    const val ACOPIADOR_REGISTRAR_ENTREGA = "ACOPIADOR_REGISTRAR_ENTREGA"

    /**
     * Guarda (POST) y, si corresponde, confirma (PATCH finalizar) un análisis en una sola
     * operación lógica — ver calidad.txt sección 23: no queremos que se sincronice el análisis
     * pero no sus parámetros, o que quede confirmado localmente sin llegar nunca a confirmarse
     * en el servidor.
     */
    const val CALIDAD_REGISTRAR_ANALISIS = "CALIDAD_REGISTRAR_ANALISIS"

    /**
     * Encargado de Planta: iniciar y finalizar un lote son DOS llamadas reales al backend,
     * separadas potencialmente por horas (a diferencia de Calidad, donde todo pasa en una sola
     * sesión). No se combinan en una operación atómica: "finalizar" solo se habilita en la UI
     * sobre un lote que YA tiene id real de servidor — ver la limitación de encargado-planta.md
     * sección 7 (conflicto de stock, sin resolver) para por qué no se intentó encadenar ambas
     * escrituras offline.
     */
    const val PLANTA_INICIAR_PRODUCCION = "PLANTA_INICIAR_PRODUCCION"
    const val PLANTA_FINALIZAR_PRODUCCION = "PLANTA_FINALIZAR_PRODUCCION"

    /**
     * Etapa 2/3: registro de entrega por muestreo directo del analista (ver
     * CalidadEntregaMuestreadaRequest) — la materialización real de "el analista también puede
     * acopiar". Es una operación de escritura independiente de CALIDAD_REGISTRAR_ANALISIS: crea
     * la entrega primero, recién después existe un `entrega_id` sobre el que analizar.
     */
    const val CALIDAD_REGISTRAR_ENTREGA_MUESTREADA = "CALIDAD_REGISTRAR_ENTREGA_MUESTREADA"
}

sealed class SyncPushResult {
    /** El backend confirmó y devolvió un id — la operación queda SYNCED y ya no se reintenta jamás. */
    data class Success(val serverId: Long) : SyncPushResult()

    /** 422/403: error de validación o negocio. Nunca se reintenta sola; pasa a NEEDS_REVIEW. */
    data class Rejected(val message: String) : SyncPushResult()

    /** Timeout/sin conexión/5xx transitorio: se deja PENDING para reintentar en el próximo ciclo. */
    data class TransientFailure(val message: String) : SyncPushResult()
}

/** Implementado por cada feature con escritura offline para saber cómo enviar su payload al backend. */
interface SyncOperationHandler {
    val operationType: String

    /**
     * [id]: el id local de la fila en `sync_queue` (ver SyncQueueRepository.enqueue). Se reenvía
     * como cabecera `Idempotency-Key` en las escrituras críticas que el backend ya soporta (ver
     * PROMPT_INICIAL.md del backend, sección 3) — es estable entre reintentos de la MISMA
     * operación, así que un reintento tras perder la respuesta original nunca duplica en el
     * servidor.
     */
    suspend fun push(id: String, payloadJson: String): SyncPushResult

    /** Se llama tras [SyncPushResult.Success] para que el feature actualice su propia copia local (ej. guardar el id de servidor en su tabla de entregas). */
    suspend fun onSynced(localId: String, serverId: Long) {}
}
