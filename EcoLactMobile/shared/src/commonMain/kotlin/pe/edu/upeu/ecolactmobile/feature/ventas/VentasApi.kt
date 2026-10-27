package pe.edu.upeu.ecolactmobile.feature.ventas

import io.ktor.client.HttpClient
import io.ktor.client.request.*
import io.ktor.http.*
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiAsistencia

class VentasApi(private val client: HttpClient) {
    suspend fun catalogo(): CatalogoVentas = client.get("ventas/catalogo").toEnvelopeData()
    suspend fun compradores(): List<ClienteVenta> = client.get("ventas/compradores").toEnvelopeData()
    suspend fun historial(): List<Venta> = client.get("ventas/movil").toEnvelopeData()
    suspend fun perfil(): MiAsistencia = client.get("ventas/mi-asistencia").toEnvelopeData()
    suspend fun comunicados(): List<ComunicadoVentas> = client.get("ventas/comunicados").toEnvelopeData()
    suspend fun cotizar(request: VentaRequest): CotizacionVenta = client.post("ventas/cotizar") {
        contentType(ContentType.Application.Json); setBody(request)
    }.toEnvelopeData()
    suspend fun registrar(request: VentaRequest, localId: String): Venta = client.post("ventas/ventas") {
        contentType(ContentType.Application.Json); header("Idempotency-Key", localId); setBody(request)
    }.toEnvelopeData()
    suspend fun comprador(request: NuevoComprador): ClienteVenta = client.post("ventas/compradores") {
        contentType(ContentType.Application.Json); setBody(request)
    }.toEnvelopeData()
}
