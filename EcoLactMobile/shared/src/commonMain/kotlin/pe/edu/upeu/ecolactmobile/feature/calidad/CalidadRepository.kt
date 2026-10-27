package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.map
import kotlinx.serialization.decodeFromString
import kotlinx.serialization.encodeToString
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.cache.NoLocalDataException
import pe.edu.upeu.ecolactmobile.core.cache.fetchCached
import pe.edu.upeu.ecolactmobile.core.cache.fetchOfflineFirst
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.database.Sync_queue
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import pe.edu.upeu.ecolactmobile.core.ui.components.ImagenAdjunta
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId
import pe.edu.upeu.ecolactmobile.core.util.nowIso
import pe.edu.upeu.ecolactmobile.feature.trabajador.MENSAJE_ASISTENCIA_NO_VERIFICADA
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiAsistencia

data class BorradorLocal(val entregaId: Long, val valores: Map<String, Double>, val observacion: String?)

sealed class ConfirmarAnalisisResult {
    data class Confirmado(val analisis: AnalisisBorradorInfo) : ConfirmarAnalisisResult()
    data class GuardadoOffline(val localId: String, val conFoto: Boolean) : ConfirmarAnalisisResult()
    data class Rechazado(val mensaje: String) : ConfirmarAnalisisResult()
}

sealed class RegistrarEntregaMuestreadaResult {
    data class Confirmada(val entrega: CalidadEntregaMuestreada) : RegistrarEntregaMuestreadaResult()
    data class GuardadaOffline(val localId: String) : RegistrarEntregaMuestreadaResult()
    data class Rechazada(val mensaje: String) : RegistrarEntregaMuestreadaResult()
}

sealed class SeleccionResult {
    data object Confirmada : SeleccionResult()
    data object GuardadaOffline : SeleccionResult()

    /** Otro analista ya la eligió: no se duplica. */
    data class Conflicto(val mensaje: String) : SeleccionResult()
    data class Rechazada(val mensaje: String) : SeleccionResult()
}

sealed class CapturaResult {
    /** El servidor leyó el ticket: pasar a revisar los datos detectados. */
    data object Leida : CapturaResult()

    /** Sin conexión: foto guardada, el OCR se hará al volver internet. */
    data object GuardadaOffline : CapturaResult()
    data class Error(val mensaje: String) : CapturaResult()
}

/** Selección que el servidor rechazó al sincronizar (p. ej. ya la eligió otro analista). */
data class ConflictoSeleccion(val colaId: String, val proveedorNombre: String, val mensaje: String)

/** Lo que muestran Inicio y "Muestras pendientes". */
data class PanelMuestras(
    val pendientes: List<MuestraPendiente>,
    val analizadasHoy: Int,
    val conflictos: List<ConflictoSeleccion>,
    val analista: AnalistaNombre?,
)

/** Análisis confirmado en el teléfono que todavía no llegó al servidor. */
data class AnalisisLocal(val colaId: String, val entregaId: Long, val fechaAnalisis: String?, val requiereRevision: String?)

data class HistorialPanel(val filas: List<HistorialFila>, val porEnviar: List<AnalisisLocal>)

/**
 * Repositorio del analista: todo lo que se muestra sale de SQLite (cache_entry, calidad_captura,
 * sync_queue) y la red solo actualiza esa copia. Las escrituras sin conexión se guardan con la
 * hora REAL del evento y van a la cola de sincronización.
 */
class CalidadRepository(
    private val api: CalidadApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val capturas: CalidadCapturaStore,
    private val isOnline: () -> Boolean,
    database: EcoLactDatabase,
    /** Solo para la clave del QR: la caché no se borra al cerrar sesión y el teléfono puede ser compartido. */
    private val userId: Long = 0,
    /** Asistencia confirmada por el servidor para HOY (AsistenciaGate): sin ella no se encola ninguna operación. */
    private val puedeOperarSinConexion: () -> Boolean = { true },
) {
    private val borradorQueries = database.calidadBorradorQueries

    // ---- Muestras ----

    fun observePanel(): Flow<CachedValue<PanelMuestras>?> =
        combine(cache.observe<MuestrasResponse>(KEY_MUESTRAS), syncQueue.observeAll(), capturas.observeEstados()) { cached, cola, estados ->
            cached ?: return@combine null
            val seleccionesLocales = cola.pendientesDe(SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA)
                .mapNotNull { fila -> decodificar<CalidadSeleccionMuestraSyncPayload>(fila)?.let { SeleccionLocal(it.request, it.entrega) } }
            val analisisLocales = cola.pendientesDe(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS)
                .mapNotNull { fila -> decodificar<CalidadAnalisisSyncPayload>(fila)?.entrega_id }
                .toSet()
            val conflictos = cola
                .filter { it.operation_type == SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA && it.status == SyncStatus.NEEDS_REVIEW.name }
                .mapNotNull { fila ->
                    decodificar<CalidadSeleccionMuestraSyncPayload>(fila)?.let {
                        ConflictoSeleccion(fila.id, it.entrega.proveedorNombre, fila.error_message ?: "No se pudo seleccionar la muestra.")
                    }
                }
            val panel = PanelMuestras(
                pendientes = combinarPendientes(cached.value.filas, seleccionesLocales, analisisLocales, estados),
                analizadasHoy = cached.value.kpis.analizadas_hoy,
                conflictos = conflictos,
                analista = cached.value.analista,
            )
            CachedValue(panel, cached.cachedAtIso)
        }

    fun observeMuestra(entregaId: Long): Flow<CachedValue<MuestraPendiente?>?> =
        observePanel().map { panel -> panel?.let { CachedValue(it.value.pendientes.firstOrNull { m -> m.entregaId == entregaId }, it.cachedAtIso) } }

    /** Descarga las muestras y, para cada una, parámetros + reglas: así se puede analizar sin señal. */
    suspend fun refreshMuestras() {
        val muestras = api.muestras()
        cache.put(KEY_MUESTRAS, muestras)
        muestras.filas.filter { it.esPendiente() }.forEach { m ->
            runCatching { guardarPrevio(m.entregaId, api.analisisPrevio(m.entregaId)) }
        }
    }

    fun descartarConflicto(colaId: String) = syncQueue.delete(colaId)

    /**
     * Entregas que se pueden seleccionar (el servidor ya excluye anuladas, analizadas y muestreadas).
     * La búsqueda se filtra en el teléfono, así funciona sin conexión con la última copia.
     */
    suspend fun elegibles(fecha: String): CacheOutcome<List<EntregaElegible>> {
        val outcome = cache.fetchOfflineFirst(keyElegibles(fecha), isOnline()) { api.elegibles(fecha = fecha).filas }
        val yaSeleccionadas = idsSeleccionadas()
        return when (outcome) {
            is CacheOutcome.Fresh -> CacheOutcome.Fresh(outcome.data.filter { it.entregaId !in yaSeleccionadas })
            is CacheOutcome.Stale -> CacheOutcome.Stale(outcome.data.filter { it.entregaId !in yaSeleccionadas }, outcome.cachedAtIso)
        }
    }

    /** Lo ya elegido en el teléfono o el servidor, incluidas las que otro analista ganó (conflicto). */
    private fun idsSeleccionadas(): Set<Long> {
        val servidor = cache.get<MuestrasResponse>(KEY_MUESTRAS)?.value?.filas.orEmpty().map { it.entregaId }
        val locales = syncQueue.byOperationType(SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA)
            .mapNotNull { decodificar<CalidadSeleccionMuestraSyncPayload>(it)?.entrega?.entregaId }
        return (servidor + locales).toSet()
    }

    /** El sorteo SIEMPRE lo hace el servidor: sin conexión no hay muestra aleatoria. */
    suspend fun sortear(excluir: List<Long>): Result<PropuestaAleatoria> {
        if (!isOnline()) return Result.failure(NoLocalDataException(MENSAJE_ALEATORIA_OFFLINE))
        return runCatching { api.sortearMuestra(excluir) }
    }

    suspend fun seleccionar(
        entrega: EntregaElegible,
        metodo: MetodoSeleccion,
        tipoControl: TipoControl?,
        observacion: String?,
        propuestaToken: String?,
    ): SeleccionResult {
        val request = SeleccionarMuestraRequest(
            entrega_id = entrega.entregaId,
            metodo_seleccion = metodo.api,
            tipo_control = tipoControl?.api,
            propuesta_token = propuestaToken,
            fecha_seleccion = nowIso(),
            observacion = observacion?.trim()?.ifBlank { null },
        )
        val localId = generateLocalId()
        if (!isOnline()) return encolarSeleccion(localId, request, entrega)
        return try {
            api.seleccionarMuestra(request, idempotencyKey = localId)
            runCatching { refreshMuestras() }
            SeleccionResult.Confirmada
        } catch (e: ApiException.Conflict) {
            runCatching { refreshMuestras() }
            SeleccionResult.Conflicto(e.message)
        } catch (e: ApiException.Validation) {
            SeleccionResult.Rechazada(e.message)
        } catch (e: ApiException.Forbidden) {
            SeleccionResult.Rechazada(e.message)
        } catch (e: Exception) {
            encolarSeleccion(localId, request, entrega)
        }
    }

    private fun encolarSeleccion(localId: String, request: SeleccionarMuestraRequest, entrega: EntregaElegible): SeleccionResult {
        if (!puedeOperarSinConexion()) return SeleccionResult.Rechazada(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        syncQueue.enqueueWithId(localId, SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA, ApiJson.encodeToString(CalidadSeleccionMuestraSyncPayload(request, entrega)))
        return SeleccionResult.GuardadaOffline
    }

    // ---- Parámetros, reglas y captura del ticket ----

    /** Parámetros activos + reglas vigentes. Sin conexión usa la copia de la entrega o la última general. */
    suspend fun analisisPrevio(entregaId: Long): AnalisisPrevioResponse {
        if (isOnline()) {
            runCatching { api.analisisPrevio(entregaId) }.onSuccess {
                guardarPrevio(entregaId, it)
                return it
            }
        }
        return cache.get<AnalisisPrevioResponse>(keyPrevio(entregaId))?.value
            ?: cache.get<AnalisisPrevioResponse>(KEY_PARAMETROS)?.value?.copy(analisis = null)
            ?: throw NoLocalDataException()
    }

    private fun guardarPrevio(entregaId: Long, previo: AnalisisPrevioResponse) {
        cache.put(keyPrevio(entregaId), previo)
        if (previo.parametros.isNotEmpty()) cache.put(KEY_PARAMETROS, previo.copy(analisis = null))
    }

    fun observeCaptura(entregaId: Long): Flow<CapturaLocal?> = capturas.observe(entregaId)

    /**
     * Guarda la foto con su hora real. Con conexión el servidor la lee de inmediato; sin conexión
     * queda PENDIENTE_OCR y se encola CALIDAD_SUBIR_TICKET (nunca se confirma sola).
     */
    suspend fun capturarTicket(entregaId: Long, muestreoId: Long?, imagen: ImagenAdjunta, fechaCaptura: String = nowIso()): CapturaResult {
        if (!isOnline()) return guardarFotoOffline(entregaId, muestreoId, imagen, fechaCaptura)
        return try {
            val ocr = api.escanearTicket(entregaId, imagen.bytes, imagen.filename, imagen.mimeType, fechaCaptura)
            capturas.guardarLeida(entregaId, muestreoId, imagen.bytes, imagen.mimeType, ocr, fechaCaptura)
            CapturaResult.Leida
        } catch (e: ApiException.Validation) {
            CapturaResult.Error(e.message)
        } catch (e: ApiException.Forbidden) {
            CapturaResult.Error(e.message)
        } catch (e: Exception) {
            guardarFotoOffline(entregaId, muestreoId, imagen, fechaCaptura)
        }
    }

    private fun guardarFotoOffline(entregaId: Long, muestreoId: Long?, imagen: ImagenAdjunta, fechaCaptura: String): CapturaResult {
        if (!puedeOperarSinConexion()) return CapturaResult.Error(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        capturas.guardarPendienteOcr(entregaId, muestreoId, imagen.bytes, imagen.mimeType, fechaCaptura)
        cancelarSubidaTicket(entregaId)
        syncQueue.enqueue(SyncOperationTypes.CALIDAD_SUBIR_TICKET, ApiJson.encodeToString(CalidadSubirTicketSyncPayload(entregaId)))
        return CapturaResult.GuardadaOffline
    }

    private fun cancelarSubidaTicket(entregaId: Long) {
        syncQueue.byOperationType(SyncOperationTypes.CALIDAD_SUBIR_TICKET)
            .filter { it.status == SyncStatus.PENDING.name && decodificar<CalidadSubirTicketSyncPayload>(it)?.entrega_id == entregaId }
            .forEach { syncQueue.delete(it.id) }
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
     * Confirmar análisis: el servidor recalcula el resultado con sus reglas. Sin conexión (o si
     * la red falla) se guarda en la cola con la hora real del análisis y de la foto; la foto viaja
     * dentro de la operación si el OCR todavía no la subió.
     */
    suspend fun confirmarAnalisis(
        entregaId: Long,
        valores: Map<String, Double>,
        origenes: Map<String, String>,
        observacion: String?,
        fechaAnalisis: String = nowIso(),
    ): ConfirmarAnalisisResult {
        val captura = capturas.obtener(entregaId)
        val fotoPendiente = captura?.foto?.takeIf { captura.ticketPath == null }
        val payload = CalidadAnalisisSyncPayload(
            entrega_id = entregaId,
            valores = valores,
            observacion = observacion,
            imagen_base64 = fotoPendiente?.let { Base64Util.encode(it) },
            imagen_filename = fotoPendiente?.let { nombreFoto(captura.fotoMime) },
            origenes = origenes,
            ticket_path = captura?.ticketPath,
            fecha_analisis = fechaAnalisis,
            fecha_captura_local = captura?.fechaCapturaLocal,
        )

        if (!isOnline()) return encolarAnalisis(payload, conFoto = captura != null)
        return try {
            val borrador = api.guardarAnalisis(
                entregaId = entregaId,
                valores = valores,
                observacion = observacion,
                imagenBytes = fotoPendiente,
                imagenFilename = payload.imagen_filename,
                origenes = origenes,
                ticketPath = payload.ticket_path,
                fechaAnalisis = fechaAnalisis,
                fechaCapturaLocal = payload.fecha_captura_local,
            )
            val confirmado = api.finalizarAnalisis(borrador.id, fechaAnalisis)
            limpiarLocal(entregaId)
            runCatching { refreshMuestras() }
            runCatching { refreshHistorial() }
            ConfirmarAnalisisResult.Confirmado(confirmado)
        } catch (e: ApiException.Validation) {
            ConfirmarAnalisisResult.Rechazado(e.message)
        } catch (e: ApiException.Forbidden) {
            ConfirmarAnalisisResult.Rechazado(e.message)
        } catch (e: Exception) {
            encolarAnalisis(payload, conFoto = captura != null)
        }
    }

    private fun encolarAnalisis(payload: CalidadAnalisisSyncPayload, conFoto: Boolean): ConfirmarAnalisisResult {
        if (!puedeOperarSinConexion()) return ConfirmarAnalisisResult.Rechazado(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        val id = syncQueue.enqueue(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS, ApiJson.encodeToString(payload))
        // La foto ya viaja con el análisis: no hace falta leerla después.
        cancelarSubidaTicket(payload.entrega_id)
        eliminarBorradorLocal(payload.entrega_id)
        return ConfirmarAnalisisResult.GuardadoOffline(id, conFoto)
    }

    private fun limpiarLocal(entregaId: Long) {
        eliminarBorradorLocal(entregaId)
        cancelarSubidaTicket(entregaId)
        capturas.eliminar(entregaId)
    }

    // ---- Historial ----

    fun observeHistorial(): Flow<CachedValue<HistorialPanel>?> =
        combine(cache.observe<HistorialListResponse>(KEY_HISTORIAL), syncQueue.observeAll()) { cached, cola ->
            cached ?: return@combine null
            val locales = cola
                .filter { it.operation_type == SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS && it.status != SyncStatus.SYNCED.name }
                .mapNotNull { fila ->
                    decodificar<CalidadAnalisisSyncPayload>(fila)?.let {
                        AnalisisLocal(fila.id, it.entrega_id, it.fecha_analisis, fila.error_message.takeIf { fila.status == SyncStatus.NEEDS_REVIEW.name })
                    }
                }
            CachedValue(HistorialPanel(cached.value.filas, locales), cached.cachedAtIso)
        }

    suspend fun refreshHistorial() {
        cache.put(KEY_HISTORIAL, api.historial())
    }

    fun observeHistorialDetalle(analisisId: Long): Flow<CachedValue<AnalisisDetalleResponse>?> = cache.observe(keyDetalle(analisisId))

    suspend fun refreshHistorialDetalle(analisisId: Long) {
        cache.put(keyDetalle(analisisId), api.historialDetalle(analisisId))
    }

    /**
     * Sincronización automática: muestras, historial y las entregas elegibles de hoy (para poder
     * seleccionar manualmente sin conexión). Devuelve el primer error para que la sync reintente.
     */
    suspend fun refreshAll(): Throwable? =
        listOf(
            runCatching { refreshMuestras() },
            runCatching { refreshHistorial() },
            runCatching { cache.put(keyElegibles("hoy"), api.elegibles(fecha = "hoy").filas) },
            runCatching { refreshAsistencia() },
        ).firstNotNullOfOrNull { it.exceptionOrNull() }

    // ---- Mi QR (asistencia la registra el administrador) ----

    fun observeAsistencia(): Flow<CachedValue<MiAsistencia>?> = cache.observe("calidad.$userId.asistencia")

    suspend fun refreshAsistencia() = cache.put("calidad.$userId.asistencia", api.miAsistencia())

    // ---- Proveedores / comunicados / muestreo directo ----

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
     * Muestreo directo (ver CalidadModels.kt) — mismo patrón que
     * AcopiadorRepository.registrarEntrega: intenta en línea primero para mostrar errores de
     * validación al toque, encola con el mismo id (Idempotency-Key) si falla por red.
     */
    suspend fun registrarEntregaMuestreada(request: CalidadEntregaMuestreadaRequest): RegistrarEntregaMuestreadaResult {
        val localId = generateLocalId()
        if (!isOnline()) {
            if (!puedeOperarSinConexion()) return RegistrarEntregaMuestreadaResult.Rechazada(MENSAJE_ASISTENCIA_NO_VERIFICADA)
            return RegistrarEntregaMuestreadaResult.GuardadaOffline(enqueueEntregaMuestreada(localId, request))
        }
        return try {
            val entrega = api.registrarEntregaMuestreada(request, idempotencyKey = localId)
            runCatching { refreshMuestras() }
            RegistrarEntregaMuestreadaResult.Confirmada(entrega)
        } catch (e: ApiException.Validation) {
            RegistrarEntregaMuestreadaResult.Rechazada(e.message)
        } catch (e: ApiException.Forbidden) {
            RegistrarEntregaMuestreadaResult.Rechazada(e.message)
        } catch (e: Exception) {
            if (!puedeOperarSinConexion()) return RegistrarEntregaMuestreadaResult.Rechazada(MENSAJE_ASISTENCIA_NO_VERIFICADA)
            RegistrarEntregaMuestreadaResult.GuardadaOffline(enqueueEntregaMuestreada(localId, request))
        }
    }

    private fun enqueueEntregaMuestreada(localId: String, request: CalidadEntregaMuestreadaRequest): String {
        val payload = CalidadEntregaMuestreadaSyncPayload(request.metodo_identificacion, request.identificador, request.litros, request.observacion, request.fecha_hora_evento)
        syncQueue.enqueueWithId(localId, SyncOperationTypes.CALIDAD_REGISTRAR_ENTREGA_MUESTREADA, ApiJson.encodeToString(payload))
        return localId
    }

    companion object {
        /** El servidor evita duplicados al sortear: sin conexión no hay muestra aleatoria (la manual sí se encola). */
        const val MENSAJE_ALEATORIA_OFFLINE = "Se necesita conexión para elegir una muestra aleatoria."

        private const val KEY_MUESTRAS = "calidad.muestras"
        private const val KEY_HISTORIAL = "calidad.historial"
        private const val KEY_PARAMETROS = "calidad.parametrosReglas"
        private fun keyElegibles(fecha: String) = "calidad.elegibles.$fecha"
        private fun keyPrevio(entregaId: Long) = "calidad.analisisPrevio.$entregaId"
        private fun keyDetalle(analisisId: Long) = "calidad.historialDetalle.$analisisId"
    }
}

private fun List<Sync_queue>.pendientesDe(tipo: String): List<Sync_queue> =
    filter { it.operation_type == tipo && (it.status == SyncStatus.PENDING.name || it.status == SyncStatus.SYNCING.name) }

private inline fun <reified T> decodificar(fila: Sync_queue): T? = runCatching { ApiJson.decodeFromString<T>(fila.payload_json) }.getOrNull()
