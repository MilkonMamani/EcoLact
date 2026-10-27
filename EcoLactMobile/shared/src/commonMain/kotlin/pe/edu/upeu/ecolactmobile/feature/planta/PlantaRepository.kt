package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import pe.edu.upeu.ecolactmobile.core.ui.components.ImagenAdjunta
import pe.edu.upeu.ecolactmobile.core.util.fechaHoraEventoIso
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId
import pe.edu.upeu.ecolactmobile.feature.trabajador.MENSAJE_ASISTENCIA_NO_VERIFICADA

/** Resultado de una escritura que puede quedar en el teléfono hasta tener conexión. */
sealed class OperacionResult {
    data class Confirmado(val serverId: Long, val codigo: String? = null) : OperacionResult()
    data class GuardadoOffline(val localId: String) : OperacionResult()
    data class Rechazado(val mensaje: String) : OperacionResult()
}

sealed class CapturaBoletaResult {
    /** El servidor leyó la boleta (o no pudo, y queda para asociar a mano): toca revisar. */
    data class Leida(val borradorId: String) : CapturaBoletaResult()

    /** Sin conexión: la foto queda guardada (PENDIENTE_OCR) y se leerá sola al volver internet. */
    data class GuardadaOffline(val borradorId: String) : CapturaBoletaResult()
    data class Error(val mensaje: String) : CapturaBoletaResult()
}

/** Producción iniciada en el teléfono que el servidor todavía no registró (o rechazó: [conflicto]). */
data class LotePendiente(val localId: String, val productoNombre: String, val cantidad: Double, val fechaLocal: String, val conflicto: String?)

/** Finalización de un lote real guardada en el teléfono. */
data class FinalizacionPendiente(val localId: String, val loteId: Long, val conflicto: String?)

/**
 * Datos del encargado de planta, offline-first: las pantallas observan SQLite (CacheRepository y
 * la tabla de borradores de compra) y la red solo actualiza esas copias. Las escrituras van al
 * servidor si hay conexión o a `sync_queue` si no, con el id local como `Idempotency-Key`.
 * Recetas y precios son de solo lectura: este repositorio no tiene forma de cambiarlos.
 */
class PlantaRepository(
    private val api: PlantaApi,
    private val cache: CacheRepository,
    private val syncQueue: SyncQueueRepository,
    private val compras: PlantaCompraStore,
    private val isOnline: () -> Boolean,
    /** Solo para la clave del perfil/QR: la caché no se borra al cerrar sesión y el teléfono puede ser compartido. */
    private val userId: Long = 0,
    /** Asistencia confirmada por el servidor para HOY (AsistenciaGate): sin ella no se encola ninguna operación. */
    private val puedeOperarSinConexion: () -> Boolean = { true },
) {
    private val kPerfil = "planta.$userId.perfil"

    // ---- Lectura (SQLite) ----

    fun observePerfil(): Flow<CachedValue<PlantaPerfil>?> = cache.observe(kPerfil)
    fun observeInsumos(): Flow<CachedValue<InsumosListResponse>?> = cache.observe(K_INSUMOS)
    fun observeProduccion(estado: String): Flow<CachedValue<ProduccionListResponse>?> = cache.observe(kProduccion(estado))
    fun observeProductos(): Flow<CachedValue<ProductosListResponse>?> = cache.observe(K_PRODUCTOS)
    fun observeRecetas(): Flow<CachedValue<RecetasListResponse>?> = cache.observe(K_RECETAS)
    fun observeCatalogo(): Flow<CachedValue<List<CatalogoInsumo>>?> = cache.observe(K_CATALOGO)
    fun observeCompras(): Flow<CachedValue<List<CompraReciente>>?> = cache.observe(K_COMPRAS)
    fun observeInsumoDetalle(id: Long): Flow<CachedValue<InsumoDetalleResponse>?> = cache.observe(kInsumo(id))
    fun observeProductoDetalle(id: Long): Flow<CachedValue<ProductoDetalleResponse>?> = cache.observe(kProducto(id))
    fun observeLote(id: Long): Flow<CachedValue<LoteDetalle>?> = cache.observe(kLote(id))

    fun insumosGuardados(): List<InsumoFila> = runCatching { cache.get<InsumosListResponse>(K_INSUMOS)?.value?.filas }.getOrNull().orEmpty()
    fun productosGuardados(): List<ProductoFila> = runCatching { cache.get<ProductosListResponse>(K_PRODUCTOS)?.value?.filas }.getOrNull().orEmpty()

    // ---- Descarga (red → SQLite) ----

    suspend fun refreshPerfil() = cache.put(kPerfil, api.perfil())
    suspend fun refreshInsumos() = cache.put(K_INSUMOS, api.insumos())
    suspend fun refreshProduccion(estado: String) = cache.put(kProduccion(estado), api.produccion(estado))
    suspend fun refreshProductos() = cache.put(K_PRODUCTOS, api.productos())
    suspend fun refreshRecetas() = cache.put(K_RECETAS, api.recetas())
    suspend fun refreshCatalogo() = cache.put(K_CATALOGO, api.catalogoCompra())
    suspend fun refreshCompras() = cache.put(K_COMPRAS, api.compras())
    suspend fun refreshInsumoDetalle(id: Long) = cache.put(kInsumo(id), api.insumoDetalle(id))
    suspend fun refreshProductoDetalle(id: Long) = cache.put(kProducto(id), api.productoDetalle(id))
    suspend fun refreshLote(id: Long) = cache.put(kLote(id), api.loteDetalle(id).lote)

    /**
     * Todo lo que la app necesita para trabajar sin conexión: listas y los detalles de insumos,
     * productos (con sus precios) y lotes en proceso. Devuelve el primer error, sin cortar el resto.
     */
    suspend fun refreshAll(): Throwable? {
        var error: Throwable? = null
        suspend fun paso(bloque: suspend () -> Unit) {
            try {
                bloque()
            } catch (e: Exception) {
                if (error == null) error = e
            }
        }
        paso { refreshPerfil() }
        paso { refreshInsumos() }
        paso { refreshProductos() }
        paso { refreshRecetas() }
        paso { refreshProduccion(EN_PROCESO) }
        paso { refreshProduccion(FINALIZADO) }
        paso { refreshCatalogo() }
        paso { refreshCompras() }
        insumosGuardados().forEach { paso { refreshInsumoDetalle(it.id) } }
        productosGuardados().forEach { paso { refreshProductoDetalle(it.id) } }
        runCatching { cache.get<ProduccionListResponse>(kProduccion(EN_PROCESO))?.value?.filas }.getOrNull().orEmpty()
            .forEach { paso { refreshLote(it.id) } }
        return error
    }

    // ---- Producción ----

    /** Requerimientos calculados por el servidor (receta activa × cantidad contra el stock real). */
    suspend fun requerimientos(productoId: Long, cantidad: Double): RequerimientosResponse =
        api.requerimientos(ProduccionRequest(listOf(LineaProduccion(productoId, cantidad))))

    suspend fun iniciarProduccion(receta: RecetaFila, cantidad: Double): OperacionResult {
        val localId = generateLocalId()
        val request = ProduccionRequest(listOf(LineaProduccion(receta.productoId, cantidad)), fecha_hora_evento = fechaHoraEventoIso())
        if (!isOnline()) return encolarInicio(localId, receta, cantidad, request)
        return try {
            val lote = api.iniciarProduccion(request, idempotencyKey = localId)
            runCatching { refreshLote(lote.id) }
            runCatching { refreshProduccion(EN_PROCESO) }
            runCatching { refreshInsumos() }
            OperacionResult.Confirmado(lote.id, lote.codigo)
        } catch (e: ApiException) {
            when (e) {
                is ApiException.Validation, is ApiException.Forbidden, is ApiException.NotFound, is ApiException.Conflict -> OperacionResult.Rechazado(e.message ?: "No se pudo completar la operación.")
                else -> encolarInicio(localId, receta, cantidad, request)
            }
        } catch (e: Exception) {
            encolarInicio(localId, receta, cantidad, request)
        }
    }

    private fun encolarInicio(localId: String, receta: RecetaFila, cantidad: Double, request: ProduccionRequest): OperacionResult {
        if (!puedeOperarSinConexion()) return OperacionResult.Rechazado(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        val nombre = listOfNotNull(receta.nombreProducto, receta.presentacion).joinToString(" · ")
        val payload = PlantaIniciarProduccionPayload(request.productos, nombre, cantidad, request.fecha_hora_evento ?: fechaHoraEventoIso())
        syncQueue.enqueueWithId(localId, SyncOperationTypes.PLANTA_INICIAR_PRODUCCION, ApiJson.encodeToString(payload))
        return OperacionResult.GuardadoOffline(localId)
    }

    fun observeLotesPendientes(): Flow<List<LotePendiente>> = syncQueue.observeAll().map { filas ->
        filas.filter { it.operation_type == SyncOperationTypes.PLANTA_INICIAR_PRODUCCION && it.status != SyncStatus.SYNCED.name }
            .mapNotNull { fila ->
                runCatching { ApiJson.decodeFromString<PlantaIniciarProduccionPayload>(fila.payload_json) }.getOrNull()?.let {
                    LotePendiente(fila.id, it.productoNombre, it.cantidad, it.fechaLocal, fila.error_message.takeIf { fila.status == SyncStatus.NEEDS_REVIEW.name })
                }
            }
    }

    /** [obtenido]: cantidad realmente obtenida por id de detalle de producción. */
    suspend fun finalizarProduccion(loteId: Long, obtenido: Map<Long, Double>): OperacionResult {
        val localId = generateLocalId()
        val request = FinalizarProduccionRequest(obtenido.mapKeys { it.key.toString() }, fecha_hora_evento = fechaHoraEventoIso())
        if (!isOnline()) return encolarFinalizacion(localId, loteId, request)
        return try {
            val lote = api.finalizarProduccion(loteId, request, idempotencyKey = localId)
            cache.put(kLote(loteId), lote)
            runCatching { refreshProduccion(EN_PROCESO) }
            runCatching { refreshProduccion(FINALIZADO) }
            runCatching { refreshProductos() }
            OperacionResult.Confirmado(lote.id, lote.codigo)
        } catch (e: ApiException) {
            when (e) {
                is ApiException.Validation, is ApiException.Forbidden, is ApiException.NotFound, is ApiException.Conflict -> OperacionResult.Rechazado(e.message ?: "No se pudo completar la operación.")
                else -> encolarFinalizacion(localId, loteId, request)
            }
        } catch (e: Exception) {
            encolarFinalizacion(localId, loteId, request)
        }
    }

    private fun encolarFinalizacion(localId: String, loteId: Long, request: FinalizarProduccionRequest): OperacionResult {
        if (!puedeOperarSinConexion()) return OperacionResult.Rechazado(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        val payload = PlantaFinalizarProduccionPayload(loteId, request.obtenido, request.fecha_hora_evento)
        syncQueue.enqueueWithId(localId, SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION, ApiJson.encodeToString(payload))
        return OperacionResult.GuardadoOffline(localId)
    }

    fun observeFinalizacionesPendientes(): Flow<Map<Long, FinalizacionPendiente>> = syncQueue.observeAll().map { filas ->
        filas.filter { it.operation_type == SyncOperationTypes.PLANTA_FINALIZAR_PRODUCCION && it.status != SyncStatus.SYNCED.name }
            .mapNotNull { fila ->
                runCatching { ApiJson.decodeFromString<PlantaFinalizarProduccionPayload>(fila.payload_json) }.getOrNull()?.let {
                    FinalizacionPendiente(fila.id, it.lote_id, fila.error_message.takeIf { fila.status == SyncStatus.NEEDS_REVIEW.name })
                }
            }
            .associateBy { it.loteId }
    }

    /** Quita de la cola una operación que el servidor rechazó (el aviso de conflicto ya se leyó). */
    fun descartarRechazada(localId: String) {
        if (syncQueue.all().any { it.id == localId && it.status == SyncStatus.NEEDS_REVIEW.name }) syncQueue.delete(localId)
    }

    // ---- Compras de insumos ----

    fun observeBorradores(): Flow<List<BorradorResumen>> = compras.observeResumen()
    fun observeBorrador(id: String): Flow<BorradorLocal?> = compras.observe(id)

    /** Registro manual: borrador vacío (o con el insumo ya elegido desde su detalle) para revisar. */
    fun crearBorradorManual(insumoId: Long? = null): String {
        val id = generateLocalId()
        val lineas = listOf(LineaBorrador(insumoId = insumoId))
        compras.crear(id, OrigenCompra.MANUAL, EstadoBorrador.REQUIERE_REVISION, null, null, BorradorCompra(lineas = lineas))
        return id
    }

    /**
     * Foto de la boleta: con conexión el servidor la lee al instante y PROPONE las líneas; sin
     * conexión queda PENDIENTE_OCR y la cola la sube sola al volver internet. Nunca registra stock.
     */
    suspend fun capturarBoleta(imagen: ImagenAdjunta): CapturaBoletaResult {
        if (!isOnline() && !puedeOperarSinConexion()) return CapturaBoletaResult.Error(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        val id = generateLocalId()
        val fechaCaptura = fechaHoraEventoIso()
        compras.crear(id, OrigenCompra.BOLETA, EstadoBorrador.PENDIENTE_OCR, imagen.bytes, imagen.mimeType, BorradorCompra())
        if (!isOnline()) return encolarOcr(id)
        return try {
            val ocr = api.leerBoleta(imagen.bytes, imagen.filename, imagen.mimeType, fechaCaptura)
            compras.marcarOcrProcesado(id, ocr.imagen_boleta, BorradorCompra.desdeOcr(ocr), ocr.mensaje.takeUnless { ocr.success })
            CapturaBoletaResult.Leida(id)
        } catch (e: ApiException.Validation) {
            compras.eliminar(id)
            CapturaBoletaResult.Error(e.message)
        } catch (e: ApiException.Forbidden) {
            compras.eliminar(id)
            CapturaBoletaResult.Error(e.message)
        } catch (e: Exception) {
            if (!puedeOperarSinConexion()) {
                compras.eliminar(id)
                return CapturaBoletaResult.Error(MENSAJE_ASISTENCIA_NO_VERIFICADA)
            }
            encolarOcr(id)
        }
    }

    private fun encolarOcr(borradorId: String): CapturaBoletaResult {
        syncQueue.enqueueWithId("ocr-$borradorId", SyncOperationTypes.PLANTA_SUBIR_BOLETA, ApiJson.encodeToString(PlantaBorradorPayload(borradorId)))
        return CapturaBoletaResult.GuardadaOffline(borradorId)
    }

    /** Guarda lo que la persona corrigió, sin confirmar (no toca el stock). */
    fun guardarBorrador(id: String, datos: BorradorCompra) {
        val actual = compras.obtener(id) ?: return
        if (actual.estado == EstadoBorrador.PENDIENTE_SYNC) return
        compras.guardar(id, datos, actual.estado, actual.mensaje)
    }

    /**
     * CONFIRMAR COMPRA (decisión de la persona tras revisar). Con conexión el servidor la registra y
     * suma el stock; sin conexión queda PENDIENTE_SYNC en la cola. El id del borrador es la
     * `Idempotency-Key`: un reintento nunca registra la compra dos veces.
     */
    suspend fun confirmarCompra(id: String, datos: BorradorCompra): OperacionResult {
        val borrador = compras.obtener(id) ?: return OperacionResult.Rechazado("La compra ya no está en el teléfono.")
        if (!datos.listaParaConfirmar) return OperacionResult.Rechazado("Completa el insumo, la presentación y la cantidad de cada línea.")

        if (!isOnline() && !puedeOperarSinConexion()) return OperacionResult.Rechazado(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        val fechaEvento = fechaHoraEventoIso()
        compras.guardar(id, datos, EstadoBorrador.PENDIENTE_SYNC)
        // Un rechazo anterior de esta misma compra deja su fila en NEEDS_REVIEW: se reemplaza.
        syncQueue.delete(id)

        if (!isOnline()) return encolarCompra(id, fechaEvento)
        return try {
            // Foto tomada sin conexión que la cola todavía no subió: viaja ahora como respaldo de la compra.
            val imagen = borrador.imagenBoleta ?: borrador.foto?.let { foto ->
                runCatching { api.leerBoleta(foto, "boleta-$id.jpg", borrador.fotoMime ?: "image/jpeg", borrador.creadoEn).imagen_boleta }.getOrNull()
            }
            val compra = api.registrarCompra(datos.toRequest(imagen, fechaEvento), idempotencyKey = id)
            compras.eliminar(id)
            syncQueue.delete("ocr-$id")
            runCatching { refreshInsumos() }
            runCatching { refreshCompras() }
            datos.lineas.mapNotNull { it.insumoId }.distinct().forEach { runCatching { refreshInsumoDetalle(it) } }
            OperacionResult.Confirmado(compra.id, compra.referencia)
        } catch (e: ApiException) {
            when (e) {
                is ApiException.Validation, is ApiException.Forbidden, is ApiException.Conflict -> {
                    compras.guardar(id, datos, EstadoBorrador.REQUIERE_REVISION, e.message)
                    OperacionResult.Rechazado(e.message ?: "No se pudo registrar la compra.")
                }
                else -> encolarCompra(id, fechaEvento)
            }
        } catch (e: Exception) {
            encolarCompra(id, fechaEvento)
        }
    }

    private fun encolarCompra(borradorId: String, fechaHoraEvento: String): OperacionResult {
        if (!puedeOperarSinConexion()) {
            compras.guardar(borradorId, compras.obtener(borradorId)?.datos ?: BorradorCompra(), EstadoBorrador.REQUIERE_REVISION, MENSAJE_ASISTENCIA_NO_VERIFICADA)
            return OperacionResult.Rechazado(MENSAJE_ASISTENCIA_NO_VERIFICADA)
        }
        syncQueue.enqueueWithId(borradorId, SyncOperationTypes.PLANTA_REGISTRAR_COMPRA, ApiJson.encodeToString(PlantaBorradorPayload(borradorId, fechaHoraEvento)))
        return OperacionResult.GuardadoOffline(borradorId)
    }

    /** Descarta un borrador que todavía no se confirmó (nunca una compra ya en la cola). */
    fun descartarBorrador(id: String) {
        val borrador = compras.obtener(id) ?: return
        if (borrador.estado == EstadoBorrador.PENDIENTE_SYNC) return
        compras.eliminar(id)
        syncQueue.delete("ocr-$id")
        syncQueue.delete(id)
    }

    companion object {
        const val EN_PROCESO = "EN_PROCESO"
        const val FINALIZADO = "FINALIZADO"

        private const val K_INSUMOS = "planta.insumos"
        private const val K_PRODUCTOS = "planta.productos"
        private const val K_RECETAS = "planta.recetas"
        private const val K_CATALOGO = "planta.compras.catalogo"
        private const val K_COMPRAS = "planta.compras"
        private fun kProduccion(estado: String) = "planta.produccion.$estado"
        private fun kInsumo(id: Long) = "planta.insumo.$id"
        private fun kProducto(id: Long) = "planta.producto.$id"
        private fun kLote(id: Long) = "planta.lote.$id"
    }
}
