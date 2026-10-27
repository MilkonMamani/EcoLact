package pe.edu.upeu.ecolactmobile.feature.acopiador

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.datetime.DateTimeUnit
import kotlinx.datetime.minus
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.database.Sync_queue
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId
import pe.edu.upeu.ecolactmobile.core.util.hoy
import pe.edu.upeu.ecolactmobile.core.util.fechaHoraEventoIso
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import kotlin.time.Clock
import pe.edu.upeu.ecolactmobile.feature.trabajador.MENSAJE_ASISTENCIA_NO_VERIFICADA
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiAsistencia
import kotlin.time.Duration.Companion.minutes
import kotlin.time.ExperimentalTime
import kotlin.time.Instant

sealed class RegistrarEntregaResult {
    /** El servidor la confirmó en el momento: "Sincronizada". */
    data class Confirmada(val clave: String) : RegistrarEntregaResult()

    /** Sin conexión (o la red falló): guardada en el teléfono, "Pendiente". Se envía sola. */
    data class GuardadaOffline(val clave: String) : RegistrarEntregaResult()

    /** El servidor la rechazó (422/403): se corrige en el momento, no se guarda. */
    data class Rechazada(val mensaje: String) : RegistrarEntregaResult()
}

sealed class AccionResult {
    data object Hecha : AccionResult()
    data object GuardadaOffline : AccionResult()
    data class Error(val mensaje: String) : AccionResult()
}

/**
 * Repositorio del acopiador. Todo lo que ve la UI sale de SQLite (`cache_entry` + `sync_queue`);
 * la red solo actualiza esa copia ([refreshAll], llamado por [AcopiadorSync]). Las escrituras se
 * guardan siempre con la hora REAL del evento y un id local que viaja como `Idempotency-Key`:
 * en línea se confirman al instante; sin conexión quedan en la cola y [AcopiadorSync] las envía
 * sola al volver internet. La UI nunca decide "si hay red → API, si no → error".
 *
 * Las claves de la copia local incluyen el usuario: otro acopiador en el mismo teléfono no ve
 * los datos guardados del anterior.
 */
@OptIn(ExperimentalTime::class)
class AcopiadorRepository(
    private val api: AcopiadorApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val isOnline: () -> Boolean,
    private val userId: Long,
    /** Asistencia confirmada por el servidor para HOY (AsistenciaGate): sin ella no se encola ninguna operación. */
    private val puedeOperarSinConexion: () -> Boolean = { true },
) {
    private val kDashboard = "acopiador.$userId.dashboard"
    private val kEntregas = "acopiador.$userId.entregas"
    private val kProveedores = "acopiador.$userId.proveedores"
    private val kSeguimiento = "acopiador.$userId.seguimiento"
    private val kComunicados = "acopiador.$userId.comunicados"
    private val kRecorridos = "acopiador.$userId.recorridos"
    private val kAsistencia = "acopiador.$userId.asistencia"
    private fun kProveedor(id: Long) = "acopiador.$userId.proveedor.$id"
    private fun kSeguimientoDetalle(id: Long) = "acopiador.$userId.seguimiento.$id"

    // ---- Lectura (siempre desde SQLite) ----

    fun observePerfil(): Flow<AcopiadorPerfil?> =
        cache.observe<AcopiadorDashboard>(kDashboard).map { it?.value?.acopiador }.distinctUntilChanged()

    /** Historial único (servidor + teléfono), sin duplicados. Ver [combinarEntregas]. */
    fun observeEntregas(): Flow<CachedValue<List<EntregaVista>>?> =
        combine(cache.observe<AcopiadorEntregasListResponse>(kEntregas), syncQueue.observeAll()) { cached, cola ->
            val tieneLocales = cola.any { it.operation_type in OPERACIONES_ENTREGA }
            if (cached == null && !tieneLocales) return@combine null
            CachedValue(combinarEntregas(cached?.value?.filas.orEmpty(), cola), cached?.cachedAtIso.orEmpty())
        }

    fun observeEntrega(clave: String): Flow<CachedValue<EntregaVista?>?> =
        observeEntregas().map { lista -> lista?.let { CachedValue(it.value.firstOrNull { e -> e.clave == clave }, it.cachedAtIso) } }

    fun observeProveedores(): Flow<CachedValue<List<AcopiadorProveedorFila>>?> =
        cache.observe<AcopiadorProveedoresListResponse>(kProveedores).map { c -> c?.let { CachedValue(it.value.filas, it.cachedAtIso) } }

    fun observeProveedor(id: Long): Flow<CachedValue<AcopiadorProveedorFila?>?> =
        observeProveedores().map { c -> c?.let { CachedValue(it.value.firstOrNull { p -> p.id == id }, it.cachedAtIso) } }

    fun observeProveedorResumen(id: Long): Flow<AcopiadorProveedorResumen?> =
        cache.observe<AcopiadorProveedorDetalleResponse>(kProveedor(id)).map { it?.value?.resumen }

    fun observeSeguimiento(): Flow<CachedValue<List<AcopiadorEntrega>>?> =
        cache.observe<SeguimientoListResponse>(kSeguimiento).map { c -> c?.let { CachedValue(it.value.filas, it.cachedAtIso) } }

    fun observeSeguimientoDetalle(entregaId: Long): Flow<SeguimientoDetalleResponse?> =
        cache.observe<SeguimientoDetalleResponse>(kSeguimientoDetalle(entregaId)).map { it?.value }

    /** "Leído" se ve al instante aunque el servidor se entere al volver internet. */
    fun observeComunicados(): Flow<CachedValue<List<AcopiadorComunicado>>?> =
        combine(cache.observe<List<AcopiadorComunicado>>(kComunicados), syncQueue.observeAll()) { cached, cola ->
            cached ?: return@combine null
            val leidosLocal = cola.filter { it.operation_type == SyncOperationTypes.ACOPIADOR_LEER_COMUNICADO }
                .mapNotNull { runCatching { ApiJson.decodeFromString<ComunicadoRefPayload>(it.payload_json).comunicado_id }.getOrNull() }
                .toSet()
            CachedValue(cached.value.map { if (it.id in leidosLocal) it.copy(leido = true) else it }, cached.cachedAtIso)
        }

    fun observeComunicado(id: Long): Flow<CachedValue<AcopiadorComunicado?>?> =
        observeComunicados().map { c -> c?.let { CachedValue(it.value.firstOrNull { x -> x.id == id }, it.cachedAtIso) } }

    fun observeRecorridos(): Flow<CachedValue<List<RecorridoVista>>?> =
        combine(cache.observe<List<RecorridoDetalle>>(kRecorridos), syncQueue.observeAll()) { cached, cola ->
            cached?.let { c -> CachedValue(c.value.map { combinarRecorrido(it, cola) }, c.cachedAtIso) }
        }

    fun observeRecorrido(id: Long): Flow<CachedValue<RecorridoVista?>?> =
        observeRecorridos().map { c -> c?.let { CachedValue(it.value.firstOrNull { r -> r.detalle.id == id }, it.cachedAtIso) } }

    fun observeAsistencia(): Flow<CachedValue<MiAsistencia>?> = cache.observe(kAsistencia)

    // ---- Descarga a SQLite ----

    /** Descarga todo lo que el acopiador necesita para trabajar sin señal. Devuelve el primer error, si hubo. */
    suspend fun refreshAll(): Throwable? = listOf(
        runCatching { refreshDashboard() },
        runCatching { refreshProveedores() },
        runCatching { refreshEntregas() },
        runCatching { refreshRecorridos() },
        runCatching { refreshSeguimiento() },
        runCatching { refreshComunicados() },
        runCatching { refreshAsistencia() },
    ).firstNotNullOfOrNull { it.exceptionOrNull() }

    suspend fun refreshDashboard() = cache.put(kDashboard, api.dashboard())

    suspend fun refreshProveedores() = cache.put(kProveedores, api.proveedores())

    /**
     * Guarda los últimos [DIAS_HISTORIAL] días y retira de la cola las entregas ya confirmadas que
     * el servidor ya lista: así la fila local nunca convive con la del servidor.
     */
    suspend fun refreshEntregas() {
        val desde = hoy().minus(DIAS_HISTORIAL, DateTimeUnit.DAY).toString()
        val respuesta = api.entregas(desde = desde)
        cache.put(kEntregas, respuesta)
        val ids = respuesta.filas.map { it.id }.toSet()
        syncQueue.all()
            .filter { it.operation_type in OPERACIONES_ENTREGA && it.status == SyncStatus.SYNCED.name && it.server_id in ids }
            .forEach { syncQueue.delete(it.id) }
    }

    /** Recorridos abiertos o de los últimos días, con sus paradas: se pueden operar sin señal. */
    suspend fun refreshRecorridos() {
        val limite = hoy().minus(1, DateTimeUnit.DAY)
        val relevantes = api.recorridos().filter { r ->
            r.estado == "PENDIENTE" || r.estado == "EN_PROGRESO" || (parseFechaLocal(r.fecha_operativa)?.date?.let { it >= limite } ?: false)
        }
        cache.put(kRecorridos, relevantes.map { api.recorrido(it.id) })
        limpiarAccionesConfirmadas(listOf(SyncOperationTypes.ACOPIADOR_NO_ENTREGO, SyncOperationTypes.ACOPIADOR_INICIAR_RECORRIDO))
    }

    suspend fun refreshSeguimiento() = cache.put(kSeguimiento, api.seguimientoCalidad())

    suspend fun refreshSeguimientoDetalle(entregaId: Long) = cache.put(kSeguimientoDetalle(entregaId), api.seguimientoDetalle(entregaId))

    suspend fun refreshComunicados() {
        cache.put(kComunicados, api.comunicados())
        limpiarAccionesConfirmadas(listOf(SyncOperationTypes.ACOPIADOR_LEER_COMUNICADO))
    }

    suspend fun refreshAsistencia() = cache.put(kAsistencia, api.miAsistencia())

    suspend fun refreshProveedorDetalle(id: Long) = cache.put(kProveedor(id), api.proveedorDetalle(id))

    /** Una vez que la copia del servidor ya refleja la acción, la fila SYNCED de la cola sobra. */
    private fun limpiarAccionesConfirmadas(tipos: List<String>) {
        syncQueue.all().filter { it.operation_type in tipos && it.status == SyncStatus.SYNCED.name }.forEach { syncQueue.delete(it.id) }
    }

    // ---- Identificación del proveedor ----

    /**
     * Primero contra la lista de la zona guardada en el teléfono (funciona sin señal). Si no está
     * y hay conexión, se vuelve a descargar la lista (quizá es un proveedor nuevo) y se busca otra
     * vez. Nunca se acepta un proveedor que no esté en esa lista.
     */
    suspend fun identificar(metodo: MetodoIdentificacion, identificador: String): Identificacion {
        val zona = cache.get<AcopiadorDashboard>(kDashboard)?.value?.acopiador?.zona_id
        val local = identificarLocal(proveedoresGuardados(), metodo, identificador, zona)
        if (local !is Identificacion.NoEncontrado || !isOnline()) return local
        return if (runCatching { refreshProveedores() }.isSuccess) {
            when (val otra = identificarLocal(proveedoresGuardados(), metodo, identificador, zona)) {
                is Identificacion.NoEncontrado -> Identificacion.NoEncontrado(
                    if (metodo == MetodoIdentificacion.QR) "Este código QR no corresponde a un proveedor de tu zona." else "No hay un proveedor de tu zona con ese DNI.",
                )
                else -> otra
            }
        } else {
            local
        }
    }

    fun proveedorGuardado(id: Long): AcopiadorProveedorFila? = proveedoresGuardados().firstOrNull { it.id == id }

    private fun proveedoresGuardados(): List<AcopiadorProveedorFila> =
        runCatching { cache.get<AcopiadorProveedoresListResponse>(kProveedores)?.value?.filas }.getOrNull().orEmpty()

    // ---- Registrar entrega (directa o en una parada de recorrido) ----

    /**
     * Guarda la entrega con la hora real del acopio ([fechaHoraEvento], capturada al pulsar
     * "Guardar"; nunca se recalcula al sincronizar) y un id local que viaja como
     * `Idempotency-Key`. Con conexión se confirma al instante (un 422 se muestra en el momento,
     * p. ej. proveedor desactivado); sin conexión, o si la red falla, queda "Pendiente" y se envía
     * sola. En ambos casos queda registrada en el teléfono, así el historial la muestra de inmediato.
     */
    suspend fun registrarEntrega(
        proveedor: AcopiadorProveedorFila,
        metodo: MetodoIdentificacion,
        litros: Double,
        observacion: String?,
        parada: ParadaRef? = null,
        fechaHoraEvento: String = fechaHoraEventoIso(),
    ): RegistrarEntregaResult {
        val localId = generateLocalId()
        val request = AcopiadorEntregaRequest(
            metodo_identificacion = metodo.name,
            identificador = if (metodo == MetodoIdentificacion.QR) proveedor.qr_token ?: proveedor.dni else proveedor.dni,
            litros = litros,
            fecha_hora_evento = fechaHoraEvento,
            observacion = observacion?.trim()?.ifBlank { null },
        )
        val payload = EntregaLocalPayload(
            request = request,
            proveedor_id = proveedor.id,
            proveedor_nombre = proveedor.nombre_completo,
            proveedor_dni = proveedor.dni,
            zona_nombre = proveedor.zona_nombre,
            parada = parada,
        )
        val tipo = if (parada != null) SyncOperationTypes.ACOPIADOR_ACOPIO_PARADA else SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA

        if (!isOnline()) return encolarEntrega(localId, tipo, payload)
        return try {
            val serverId = enviarEntrega(localId, payload)
            syncQueue.enqueueWithId(localId, tipo, ApiJson.encodeToString(payload))
            syncQueue.markSynced(localId, serverId)
            RegistrarEntregaResult.Confirmada(claveLocal(localId))
        } catch (e: ApiException.Validation) {
            RegistrarEntregaResult.Rechazada(e.message)
        } catch (e: ApiException.Forbidden) {
            RegistrarEntregaResult.Rechazada(e.message)
        } catch (e: Exception) {
            encolarEntrega(localId, tipo, payload)
        }
    }

    private suspend fun enviarEntrega(localId: String, payload: EntregaLocalPayload): Long = enviarEntregaLocal(api, localId, payload)

    private fun encolarEntrega(localId: String, tipo: String, payload: EntregaLocalPayload): RegistrarEntregaResult {
        if (!puedeOperarSinConexion()) return RegistrarEntregaResult.Rechazada(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        syncQueue.enqueueWithId(localId, tipo, ApiJson.encodeToString(payload))
        return RegistrarEntregaResult.GuardadaOffline(claveLocal(localId))
    }

    /**
     * Corrige una entrega que todavía no está en el servidor (p. ej. rechazada). Los litros solo
     * se pueden cambiar dentro de [MINUTOS_EDICION_LITROS] desde la hora REAL del acopio, igual
     * que en el servidor (Entrega::MINUTOS_EDICION_LITROS): sincronizar más tarde no regala otros
     * 10 minutos. La hora del acopio nunca se modifica.
     */
    fun corregirEntrega(localId: String, litros: Double, observacion: String?, ahora: Instant = Clock.System.now()): AccionResult {
        val fila = filaCola(localId) ?: return AccionResult.Error("Esta entrega ya no está en el teléfono.")
        val payload = decodificarEntregaLocal(fila.payload_json) ?: return AccionResult.Error("Registro dañado en el teléfono.")
        if (litros != payload.request.litros && !litrosEditables(payload.request.fecha_hora_evento ?: fila.created_at, ahora)) {
            return AccionResult.Error("Ya pasaron más de $MINUTOS_EDICION_LITROS minutos desde la hora de acopio: los litros no se pueden corregir.")
        }
        val nuevo = ApiJson.encodeToString(payload.copy(request = payload.request.copy(litros = litros, observacion = observacion?.trim()?.ifBlank { null })))
        if (fila.status == SyncStatus.NEEDS_REVIEW.name) syncQueue.retryFromReview(localId, nuevo) else syncQueue.updatePayload(localId, nuevo)
        return AccionResult.Hecha
    }

    /** Solo a pedido explícito del acopiador, y solo para una entrega que el servidor rechazó. */
    fun descartarEntregaRechazada(localId: String) {
        if (filaCola(localId)?.status == SyncStatus.NEEDS_REVIEW.name) syncQueue.delete(localId)
    }

    private fun filaCola(localId: String): Sync_queue? = syncQueue.all().firstOrNull { it.id == localId }

    // ---- Recorrido ----

    suspend fun iniciarRecorrido(recorridoId: Long, fechaHoraEvento: String = fechaHoraEventoIso()): AccionResult =
        enLineaOEncolar(SyncOperationTypes.ACOPIADOR_INICIAR_RECORRIDO, ApiJson.encodeToString(RecorridoRefPayload(recorridoId, fechaHoraEvento))) {
            api.iniciarRecorrido(recorridoId, fechaHoraEvento)
            runCatching { refreshRecorridos() }
        }

    /**
     * "No entregó" con motivo. No se permite sobre una parada que ya tiene un acopio (también uno
     * guardado sin conexión).
     */
    suspend fun marcarNoEntrego(recorridoId: Long, paradaId: Long, motivo: String, fechaHoraEvento: String = fechaHoraEventoIso()): AccionResult {
        if (motivo.isBlank()) return AccionResult.Error("Indica el motivo.")
        val parada = cache.get<List<RecorridoDetalle>>(kRecorridos)?.value?.firstOrNull { it.id == recorridoId }
            ?.let { combinarRecorrido(it, syncQueue.all()) }?.paradas?.firstOrNull { it.parada.id == paradaId }
        if (parada?.estado == EstadoParada.RECOLECTADO) return AccionResult.Error("Esta parada ya tiene un acopio registrado.")
        val payload = NoEntregoPayload(recorridoId, paradaId, motivo.trim(), fechaHoraEvento)
        return enLineaOEncolar(SyncOperationTypes.ACOPIADOR_NO_ENTREGO, ApiJson.encodeToString(payload)) {
            api.noEntrego(recorridoId, paradaId, payload.motivo, payload.fecha_hora_evento)
            runCatching { refreshRecorridos() }
        }
    }

    /**
     * Cerrar el recorrido necesita conexión y que no queden registros suyos por enviar (si no,
     * el servidor rechazaría esos acopios por llegar a un recorrido ya cerrado).
     */
    suspend fun finalizarRecorrido(recorridoId: Long): AccionResult {
        if (!isOnline()) return AccionResult.Error("Para finalizar el recorrido necesitas conexión.")
        val pendientes = pendientesDeRecorrido(recorridoId)
        if (pendientes > 0) return AccionResult.Error("Todavía hay $pendientes registros por enviar. Se enviarán solos en unos segundos.")
        return try {
            api.finalizarRecorrido(recorridoId)
            runCatching { refreshRecorridos() }
            AccionResult.Hecha
        } catch (e: Exception) {
            AccionResult.Error(e.message ?: "No se pudo finalizar el recorrido.")
        }
    }

    private fun pendientesDeRecorrido(recorridoId: Long): Int {
        val detalle = cache.get<List<RecorridoDetalle>>(kRecorridos)?.value?.firstOrNull { it.id == recorridoId } ?: return 0
        return combinarRecorrido(detalle, syncQueue.all()).pendientesEnvio
    }

    private suspend fun enLineaOEncolar(tipo: String, payloadJson: String, enviar: suspend () -> Unit): AccionResult {
        val localId = generateLocalId()
        if (!isOnline()) {
            if (!puedeOperarSinConexion()) return AccionResult.Error(MENSAJE_ASISTENCIA_NO_VERIFICADA)
            syncQueue.enqueueWithId(localId, tipo, payloadJson)
            return AccionResult.GuardadaOffline
        }
        return try {
            enviar()
            AccionResult.Hecha
        } catch (e: ApiException.Validation) {
            AccionResult.Error(e.message)
        } catch (e: ApiException.Forbidden) {
            AccionResult.Error(e.message)
        } catch (e: Exception) {
            if (!puedeOperarSinConexion()) return AccionResult.Error(MENSAJE_ASISTENCIA_NO_VERIFICADA)
            syncQueue.enqueueWithId(localId, tipo, payloadJson)
            AccionResult.GuardadaOffline
        }
    }

    // ---- Comunicados ----

    suspend fun abrirComunicado(id: Long) {
        val leido = cache.get<List<AcopiadorComunicado>>(kComunicados)?.value?.firstOrNull { it.id == id }?.leido ?: false
        if (leido) return
        val enLinea = isOnline() && runCatching {
            val detalle = api.comunicadoDetalle(id)
            val lista = cache.get<List<AcopiadorComunicado>>(kComunicados)?.value.orEmpty()
            cache.put(kComunicados, lista.map { if (it.id == id) detalle.copy(leido = true) else it })
        }.isSuccess
        if (!enLinea) syncQueue.enqueue(SyncOperationTypes.ACOPIADOR_LEER_COMUNICADO, ApiJson.encodeToString(ComunicadoRefPayload(id)))
    }

    companion object {
        /** Días de historial guardados en el teléfono ("Todas" = este periodo). */
        const val DIAS_HISTORIAL = 90

        /** Igual que Entrega::MINUTOS_EDICION_LITROS en el backend. */
        const val MINUTOS_EDICION_LITROS = 10

        /** Los litros se corrigen solo dentro de los primeros minutos desde la hora REAL del acopio. */
        fun litrosEditables(fechaHoraEvento: String, ahora: Instant = Clock.System.now()): Boolean =
            runCatching { ahora <= Instant.parse(fechaHoraEvento) + MINUTOS_EDICION_LITROS.minutes }.getOrDefault(false)
    }
}

/** Envío real de una entrega encolada; compartido por el registro en línea y por la cola offline. */
internal suspend fun enviarEntregaLocal(api: AcopiadorApi, localId: String, payload: EntregaLocalPayload): Long {
    val parada = payload.parada
    return if (parada != null) {
        api.registrarAcopioParada(
            parada.recorrido_id,
            parada.parada_id,
            RegistrarAcopioParadaRequest(
                litros = payload.request.litros,
                fecha_hora_evento = payload.request.fecha_hora_evento,
                observacion = payload.request.observacion,
                token_idempotencia = localId,
            ),
        ).entrega_id
    } else {
        api.registrarEntrega(payload.request, idempotencyKey = localId).id
    }
}
