package pe.edu.upeu.ecolactmobile.feature.ventas

import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.*
import pe.edu.upeu.ecolactmobile.core.util.generateLocalId
import pe.edu.upeu.ecolactmobile.feature.trabajador.*

const val VENTAS_REGISTRAR = "VENTAS_REGISTRAR"

class VentasRepository(
    private val api: VentasApi, private val cache: CacheRepository,
    private val queue: SyncQueueRepository, private val userId: Long,
    private val online: () -> Boolean, private val asistencia: AsistenciaGate,
) {
    private val prefix = "ventas.$userId"
    private val mutex = Mutex()
    val catalogo = cache.observe<CatalogoVentas>("$prefix.catalogo")
    val compradores = cache.observe<List<ClienteVenta>>("$prefix.compradores")
    val perfil = cache.observe<MiAsistencia>("$prefix.perfil")
    val comunicados = cache.observe<List<ComunicadoVentas>>("$prefix.comunicados")
    val ventas = combine(cache.observe<List<Venta>>("$prefix.historial"), queue.observeAll()) { remote, rows ->
        val guardadas = remote?.value.orEmpty().map { venta ->
            venta.copy(localId = rows.firstOrNull { it.operation_type == VENTAS_REGISTRAR && it.server_id == venta.id }?.id ?: venta.localId)
        }
        val locales = rows.filter { it.operation_type == VENTAS_REGISTRAR }.mapNotNull { row ->
            val payload = runCatching { ApiJson.decodeFromString<VentaPendiente>(row.payload_json) }.getOrNull()
            if (payload?.userId != userId || guardadas.any { it.localId == row.id || (row.server_id != null && it.id == row.server_id) }) null
            else payload.venta.copy(id = row.server_id ?: 0, error = if (row.status == "NEEDS_REVIEW") row.error_message else null)
        }
        (guardadas + locales).sortedByDescending { it.fecha }
    }

    suspend fun refresh() {
        if (!online()) return
        // Each successful resource is persisted even when a later resource cannot be fetched.
        cache.put("$prefix.catalogo", api.catalogo())
        cache.put("$prefix.compradores", api.compradores())
        cache.put("$prefix.historial", api.historial())
        cache.put("$prefix.perfil", api.perfil())
        cache.put("$prefix.comunicados", api.comunicados())
    }

    suspend fun cotizar(request: VentaRequest, producto: ProductoVenta): CotizacionVenta {
        require(request.cantidad.isFinite() && request.cantidad > 0) { "Ingresa una cantidad válida." }
        require(request.cantidad <= stockDisponible(producto)) { "No hay stock conocido suficiente." }
        if (online()) return api.cotizar(request)
        require(request.forma_pago == "CONTADO") { "Necesitas conexión para validar el saldo disponible del proveedor." }
        return cotizacionGuardada(producto, request.cliente, request.cantidad)
            ?: error("No hay un precio guardado para esta cantidad. Conéctate para consultar el precio vigente.")
    }

    private fun stockDisponible(producto: ProductoVenta): Double {
        val reservado = queue.byOperationType(VENTAS_REGISTRAR).filter { it.status == "PENDING" || it.status == "SYNCING" }
            .mapNotNull { runCatching { ApiJson.decodeFromString<VentaPendiente>(it.payload_json) }.getOrNull() }
            .filter { it.userId == userId && it.request.producto_id == producto.id }.sumOf { it.request.cantidad }
        return producto.stock - reservado
    }

    suspend fun registrar(request: VentaRequest, producto: ProductoVenta, cliente: ClienteVenta?, cotizacion: CotizacionVenta, vendedor: String): Venta = mutex.withLock {
        require(asistencia.autorizacionAhora().puedeOperar) { ApiException.MENSAJE_ASISTENCIA_REQUERIDA }
        require(request.cantidad <= stockDisponible(producto)) { "No hay stock conocido suficiente." }
        require(online() || request.forma_pago == "CONTADO") { "Necesitas conexión para validar el saldo disponible del proveedor." }
        val localId = generateLocalId()
        val body = request.copy(precio_mostrado = cotizacion.precio_unitario)
        val venta = Venta(localId = localId, producto_id = producto.id, producto_nombre = producto.nombre,
            cliente = cliente?.nombre ?: "Público general", documento = cliente?.documento,
            proveedor_id = body.proveedor_id, comprador_id = body.comprador_id,
            cantidad = body.cantidad, precio_unitario = cotizacion.precio_unitario, importe_total = cotizacion.importe_total,
            tipo_cliente = cotizacion.tipo_cliente, forma_pago = body.forma_pago, fecha = body.fecha_hora_evento, vendedor = vendedor)
        // Persist before sending: process death or a lost response reuses exactly the same key.
        queue.enqueueWithId(localId, VENTAS_REGISTRAR, ApiJson.encodeToString(VentaPendiente(userId, body, venta)))
        venta
    }

    /** Consultar no requiere asistencia; sí conexión, porque el PDF lo genera Laravel. */
    suspend fun boletaPdf(venta: Venta): ByteArray {
        require(venta.boletaDisponible) { MENSAJE_BOLETA_PENDIENTE }
        require(online()) { "Necesitas conexión para obtener el PDF de la boleta." }
        return api.boletaPdf(venta.id)
    }

    suspend fun registrarComprador(datos: NuevoComprador) {
        require(asistencia.autorizacionAhora().puedeOperar) { ApiException.MENSAJE_ASISTENCIA_REQUERIDA }
        require(online()) { "Necesitas conexión para registrar un comprador." }
        api.comprador(datos)
        cache.put("$prefix.compradores", api.compradores())
    }
}

class VentasSyncHandler(
    private val api: VentasApi, private val cache: CacheRepository,
    private val currentUserId: () -> Long?,
) : SyncOperationHandler {
    override val operationType = VENTAS_REGISTRAR
    override fun canPush(payloadJson: String): Boolean =
        runCatching { ApiJson.decodeFromString<VentaPendiente>(payloadJson).userId == currentUserId() }.getOrDefault(false)
    override suspend fun push(id: String, payloadJson: String): SyncPushResult {
        val payload = ApiJson.decodeFromString<VentaPendiente>(payloadJson)
        if (currentUserId() != payload.userId) return SyncPushResult.TransientFailure("Inicia sesión con la cuenta que registró esta venta.")
        return try {
            val venta = api.registrar(payload.request, id)
            val key = "ventas.${payload.userId}.historial"
            val guardadas = cache.get<List<Venta>>(key)?.value.orEmpty()
            val completa = payload.venta.copy(id = venta.id, precio_unitario = venta.precio_unitario,
                importe_total = venta.importe_total, tipo_cliente = venta.tipo_cliente, fecha = venta.fecha, codigoServidor = venta.codigoServidor)
            cache.put(key, listOf(completa) + guardadas.filterNot { it.id == venta.id })
            SyncPushResult.Success(venta.id)
        } catch (e: CancellationException) { throw e
        } catch (e: ApiException.Validation) {
            SyncPushResult.Rejected(if (e.message.contains("stock", true)) "No se pudo sincronizar la venta porque el stock cambió mientras estabas sin conexión." else e.message)
        } catch (e: ApiException.Forbidden) { SyncPushResult.Rejected(e.message)
        } catch (e: ApiException.NotFound) { SyncPushResult.Rejected(e.message)
        } catch (e: Exception) { SyncPushResult.TransientFailure(e.message ?: "No se pudo enviar la venta.") }
    }
}
