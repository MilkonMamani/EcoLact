package pe.edu.upeu.ecolactmobile.feature.proveedor

import io.ktor.client.HttpClient
import io.ktor.client.request.get
import io.ktor.client.request.parameter
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

/** Todos los endpoints documentados en proveedor.md. Rol 100% de lectura. */
class ProveedorApi(private val httpClient: HttpClient) {

    suspend fun dashboard(periodo: String? = null, desde: String? = null, hasta: String? = null): ProveedorDashboard =
        httpClient.get("proveedor/dashboard") {
            periodo?.let { parameter("periodo", it) }
            desde?.let { parameter("desde", it) }
            hasta?.let { parameter("hasta", it) }
        }.toEnvelopeData()

    suspend fun entregas(estado: String? = null): EntregasListResponse =
        httpClient.get("proveedor/entregas") {
            estado?.let { parameter("estado", it) }
        }.toEnvelopeData()

    suspend fun entregaDetalle(id: Long): EntregaDetalleResponse =
        httpClient.get("proveedor/entregas/$id").toEnvelopeData()

    suspend fun liquidaciones(estado: String? = null): LiquidacionesListResponse =
        httpClient.get("proveedor/liquidaciones") {
            estado?.let { parameter("estado", it) }
        }.toEnvelopeData()

    suspend fun liquidacionDetalle(id: Long): LiquidacionDetalleResponse =
        httpClient.get("proveedor/liquidaciones/$id").toEnvelopeData()

    suspend fun pagos(): List<PagoResumen> =
        httpClient.get("proveedor/pagos").toEnvelopeData()

    suspend fun pagoDetalle(id: Long): PagoResumen =
        httpClient.get("proveedor/pagos/$id").toEnvelopeData()

    suspend fun analisis(resultado: String? = null, estado: String? = null): AnalisisListResponse =
        httpClient.get("proveedor/analisis") {
            resultado?.let { parameter("resultado", it) }
            estado?.let { parameter("estado", it) }
        }.toEnvelopeData()

    suspend fun analisisDetalle(id: Long): AnalisisDetalleResponse =
        httpClient.get("proveedor/analisis/$id").toEnvelopeData()

    suspend fun compras(productoId: Long? = null): ComprasListResponse =
        httpClient.get("proveedor/compras") {
            productoId?.let { parameter("producto_id", it) }
        }.toEnvelopeData()

    suspend fun compraDetalle(id: Long): CompraDetalleResponse =
        httpClient.get("proveedor/compras/$id").toEnvelopeData()

    suspend fun miQr(): MiQrResponse =
        httpClient.get("proveedor/qr").toEnvelopeData()

    suspend fun comunicados(): ComunicadosListResponse =
        httpClient.get("proveedor/comunicados").toEnvelopeData()

    suspend fun comunicadoDetalle(id: Long): ComunicadoDetalleResponse =
        httpClient.get("proveedor/comunicados/$id").toEnvelopeData()

    suspend fun perfil(): ProveedorPerfil =
        httpClient.get("proveedor/perfil").toEnvelopeData()
}
