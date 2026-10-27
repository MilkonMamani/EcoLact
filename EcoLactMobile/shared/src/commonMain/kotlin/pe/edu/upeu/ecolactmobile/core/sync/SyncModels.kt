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
     * Acopiador en un recorrido: acopio en una parada (crea la entrega con el id local como
     * `token_idempotencia`), "no entregó" (idempotente en el servidor) e iniciar el recorrido
     * (idempotente). Se crean sin conexión con la hora real del evento.
     */
    const val ACOPIADOR_ACOPIO_PARADA = "ACOPIADOR_ACOPIO_PARADA"
    const val ACOPIADOR_NO_ENTREGO = "ACOPIADOR_NO_ENTREGO"
    const val ACOPIADOR_INICIAR_RECORRIDO = "ACOPIADOR_INICIAR_RECORRIDO"

    /** Acopiador: abrir un comunicado sin conexión (el GET del detalle lo marca leído al volver internet). */
    const val ACOPIADOR_LEER_COMUNICADO = "ACOPIADOR_LEER_COMUNICADO"

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
     * sobre un lote que YA tiene id real de servidor. Al sincronizar un inicio offline, el
     * servidor recalcula la receta con el stock real: si ya no alcanza responde 422 y la fila
     * queda en NEEDS_REVIEW como conflicto (nunca se crea el lote ni stock negativo).
     */
    const val PLANTA_INICIAR_PRODUCCION = "PLANTA_INICIAR_PRODUCCION"
    const val PLANTA_FINALIZAR_PRODUCCION = "PLANTA_FINALIZAR_PRODUCCION"

    /**
     * Planta: subir la foto de una boleta tomada sin conexión para que el servidor la lea (OCR).
     * Solo PROPONE líneas: el borrador pasa a REQUIERE_REVISION, nunca a una compra registrada.
     */
    const val PLANTA_SUBIR_BOLETA = "PLANTA_SUBIR_BOLETA"

    /**
     * Planta: compra de insumos ya revisada y confirmada por la persona en el teléfono. El id de
     * la fila (= id del borrador) viaja como `Idempotency-Key`: reintentar nunca la duplica.
     */
    const val PLANTA_REGISTRAR_COMPRA = "PLANTA_REGISTRAR_COMPRA"

    /**
     * Etapa 2/3: registro de entrega por muestreo directo del analista (ver
     * CalidadEntregaMuestreadaRequest) — la materialización real de "el analista también puede
     * acopiar". Es una operación de escritura independiente de CALIDAD_REGISTRAR_ANALISIS: crea
     * la entrega primero, recién después existe un `entrega_id` sobre el que analizar.
     */
    const val CALIDAD_REGISTRAR_ENTREGA_MUESTREADA = "CALIDAD_REGISTRAR_ENTREGA_MUESTREADA"

    /**
     * Calidad: seleccionar (sin conexión) una entrega ya registrada como muestra. Si al
     * sincronizar otro analista ya la eligió, el servidor responde 409 y la fila queda en
     * NEEDS_REVIEW con "Esta entrega ya fue seleccionada para análisis." — nunca se duplica.
     */
    const val CALIDAD_SELECCIONAR_MUESTRA = "CALIDAD_SELECCIONAR_MUESTRA"

    /**
     * Calidad: subir la foto del ticket tomada sin conexión para que el servidor la lea (OCR).
     * Solo PROPONE valores: la captura pasa a REQUIERE_REVISION, nunca a confirmada.
     */
    const val CALIDAD_SUBIR_TICKET = "CALIDAD_SUBIR_TICKET"

    /** Marcar una notificación como leída sin conexión: se envía el PATCH al volver internet (todos los roles). */
    const val NOTIFICACION_MARCAR_LEIDA = "NOTIFICACION_MARCAR_LEIDA"

    /**
     * Proveedor: abrir un comunicado sin conexión. El backend lo marca leído al pedir su detalle
     * (GET con efecto secundario), así que la operación pendiente es repetir ese GET al reconectar.
     */
    const val PROVEEDOR_LEER_COMUNICADO = "PROVEEDOR_LEER_COMUNICADO"
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

    /** Leave another account's operations untouched until its owner signs in. */
    fun canPush(payloadJson: String): Boolean = true

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
