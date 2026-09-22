package pe.edu.upeu.ecolactmobile.feature.proveedor

import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.fetchCached

/**
 * Rol 100% de lectura (proveedor.md sección 8): no hay cola de sincronización de escritura,
 * solo "mostrar la última copia sincronizada con su fecha/hora" cuando no hay conexión.
 */
class ProveedorRepository(
    private val api: ProveedorApi,
    private val cache: CacheRepository,
) {
    suspend fun dashboard(): CacheOutcome<ProveedorDashboard> =
        cache.fetchCached("proveedor.dashboard") { api.dashboard() }

    suspend fun entregas(estado: String? = null): CacheOutcome<EntregasListResponse> =
        cache.fetchCached("proveedor.entregas.${estado.orEmpty()}") { api.entregas(estado) }

    suspend fun entregaDetalle(id: Long): CacheOutcome<EntregaDetalleResponse> =
        cache.fetchCached("proveedor.entrega.$id") { api.entregaDetalle(id) }

    suspend fun liquidaciones(estado: String? = null): CacheOutcome<LiquidacionesListResponse> =
        cache.fetchCached("proveedor.liquidaciones.${estado.orEmpty()}") { api.liquidaciones(estado) }

    suspend fun liquidacionDetalle(id: Long): CacheOutcome<LiquidacionDetalleResponse> =
        cache.fetchCached("proveedor.liquidacion.$id") { api.liquidacionDetalle(id) }

    suspend fun pagos(): CacheOutcome<List<PagoResumen>> =
        cache.fetchCached("proveedor.pagos") { api.pagos() }

    suspend fun analisis(resultado: String? = null): CacheOutcome<AnalisisListResponse> =
        cache.fetchCached("proveedor.analisis.${resultado.orEmpty()}") { api.analisis(resultado = resultado) }

    suspend fun analisisDetalle(id: Long): CacheOutcome<AnalisisDetalleResponse> =
        cache.fetchCached("proveedor.analisisDetalle.$id") { api.analisisDetalle(id) }

    suspend fun compras(): CacheOutcome<ComprasListResponse> =
        cache.fetchCached("proveedor.compras") { api.compras() }

    suspend fun compraDetalle(id: Long): CacheOutcome<CompraDetalleResponse> =
        cache.fetchCached("proveedor.compra.$id") { api.compraDetalle(id) }

    suspend fun miQr(): CacheOutcome<MiQrResponse> =
        cache.fetchCached("proveedor.qr") { api.miQr() }

    suspend fun comunicados(): CacheOutcome<ComunicadosListResponse> =
        cache.fetchCached("proveedor.comunicados") { api.comunicados() }

    /** Abrir el detalle marca el comunicado como leído automáticamente (efecto secundario del GET, ver proveedor.md 6). */
    suspend fun comunicadoDetalle(id: Long): CacheOutcome<ComunicadoDetalleResponse> =
        cache.fetchCached("proveedor.comunicado.$id") { api.comunicadoDetalle(id) }

    suspend fun perfil(): CacheOutcome<ProveedorPerfil> =
        cache.fetchCached("proveedor.perfil") { api.perfil() }
}
