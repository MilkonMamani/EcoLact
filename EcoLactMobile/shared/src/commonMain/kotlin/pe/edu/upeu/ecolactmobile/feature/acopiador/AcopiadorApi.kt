package pe.edu.upeu.ecolactmobile.feature.acopiador

import io.ktor.client.HttpClient
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.parameter
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

/** Todos los endpoints documentados en acopiador.md. Único endpoint de escritura: registrarEntrega. */
class AcopiadorApi(private val httpClient: HttpClient) {

    suspend fun dashboard(periodo: String? = null): AcopiadorDashboard =
        httpClient.get("acopiador/dashboard") {
            periodo?.let { parameter("periodo", it) }
        }.toEnvelopeData()

    /**
     * El backend resuelve identificación (QR/DNI) + registro en una sola llamada (no hay endpoint
     * separado de "validar proveedor"). [idempotencyKey]: ver nota en PlantaApi.iniciarProduccion.
     */
    suspend fun registrarEntrega(request: AcopiadorEntregaRequest, idempotencyKey: String? = null): AcopiadorEntrega =
        httpClient.post("acopiador/entregas") {
            contentType(ContentType.Application.Json)
            idempotencyKey?.let { header("Idempotency-Key", it) }
            setBody(request)
        }.toEnvelopeData()

    suspend fun entregas(proveedorId: Long? = null, estado: String? = null): AcopiadorEntregasListResponse =
        httpClient.get("acopiador/entregas") {
            proveedorId?.let { parameter("proveedor_id", it) }
            estado?.let { parameter("estado", it) }
        }.toEnvelopeData()

    suspend fun entregaDetalle(id: Long): AcopiadorEntregaDetalleResponse =
        httpClient.get("acopiador/entregas/$id").toEnvelopeData()

    suspend fun seguimientoCalidad(proveedorId: Long? = null, estadoAnalisis: String? = null, resultado: String? = null): SeguimientoListResponse =
        httpClient.get("acopiador/seguimiento-calidad") {
            proveedorId?.let { parameter("proveedor_id", it) }
            estadoAnalisis?.let { parameter("estado_analisis", it) }
            resultado?.let { parameter("resultado", it) }
        }.toEnvelopeData()

    suspend fun seguimientoDetalle(entregaId: Long): SeguimientoDetalleResponse =
        httpClient.get("acopiador/seguimiento-calidad/$entregaId").toEnvelopeData()

    /** Ya viene acotado a la zona del acopiador autenticado — no hace falta pedir otras zonas. */
    suspend fun proveedores(estado: String? = null, buscar: String? = null): AcopiadorProveedoresListResponse =
        httpClient.get("acopiador/proveedores") {
            estado?.let { parameter("estado", it) }
            buscar?.let { parameter("buscar", it) }
        }.toEnvelopeData()

    suspend fun proveedorDetalle(id: Long): AcopiadorProveedorDetalleResponse =
        httpClient.get("acopiador/proveedores/$id").toEnvelopeData()

    suspend fun comunicados(): List<AcopiadorComunicado> =
        httpClient.get("acopiador/comunicados").toEnvelopeData()

    suspend fun comunicadoDetalle(id: Long): AcopiadorComunicado =
        httpClient.get("acopiador/comunicados/$id").toEnvelopeData()
}
