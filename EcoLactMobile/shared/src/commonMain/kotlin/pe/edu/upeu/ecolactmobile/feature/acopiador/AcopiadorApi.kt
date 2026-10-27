package pe.edu.upeu.ecolactmobile.feature.acopiador

import io.ktor.client.HttpClient
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.parameter
import io.ktor.client.request.patch
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData
import pe.edu.upeu.ecolactmobile.feature.trabajador.EventoRequest
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiAsistencia

/**
 * Endpoints `/api/v1/acopiador/…` (mismos casos de uso que la Web). Escrituras: registrar
 * entrega, acopio / "no entregó" en una parada de recorrido, iniciar / finalizar recorrido.
 */
class AcopiadorApi(private val httpClient: HttpClient) {

    suspend fun dashboard(): AcopiadorDashboard = httpClient.get("acopiador/dashboard").toEnvelopeData()

    /**
     * [idempotencyKey]: id local de la operación. El backend lo guarda como token de la entrega,
     * así un reintento (respuesta perdida, cola offline) devuelve la misma entrega en vez de duplicarla.
     */
    suspend fun registrarEntrega(request: AcopiadorEntregaRequest, idempotencyKey: String): AcopiadorEntrega =
        httpClient.post("acopiador/entregas") {
            contentType(ContentType.Application.Json)
            header("Idempotency-Key", idempotencyKey)
            setBody(request)
        }.toEnvelopeData()

    /** [desde] (Y-m-d) acota el historial que se guarda en el teléfono. */
    suspend fun entregas(desde: String? = null): AcopiadorEntregasListResponse =
        httpClient.get("acopiador/entregas") {
            desde?.let { parameter("desde", it) }
        }.toEnvelopeData()

    suspend fun seguimientoCalidad(): SeguimientoListResponse =
        httpClient.get("acopiador/seguimiento-calidad").toEnvelopeData()

    suspend fun seguimientoDetalle(entregaId: Long): SeguimientoDetalleResponse =
        httpClient.get("acopiador/seguimiento-calidad/$entregaId").toEnvelopeData()

    /** Ya viene acotado a la zona del acopiador autenticado (incluye `qr_token`). */
    suspend fun proveedores(): AcopiadorProveedoresListResponse =
        httpClient.get("acopiador/proveedores").toEnvelopeData()

    suspend fun proveedorDetalle(id: Long): AcopiadorProveedorDetalleResponse =
        httpClient.get("acopiador/proveedores/$id").toEnvelopeData()

    suspend fun comunicados(): List<AcopiadorComunicado> =
        httpClient.get("acopiador/comunicados").toEnvelopeData()

    /** El backend lo marca leído al pedir el detalle. */
    suspend fun comunicadoDetalle(id: Long): AcopiadorComunicado =
        httpClient.get("acopiador/comunicados/$id").toEnvelopeData()

    suspend fun recorridos(): List<RecorridoResumen> =
        httpClient.get("acopiador/recorridos").toEnvelopeData()

    suspend fun recorrido(id: Long): RecorridoDetalle =
        httpClient.get("acopiador/recorridos/$id").toEnvelopeData()

    /** Idempotente en el backend: un recorrido ya iniciado se devuelve tal cual. */
    suspend fun iniciarRecorrido(id: Long, fechaHoraEvento: String? = null): RecorridoDetalle =
        httpClient.patch("acopiador/recorridos/$id/iniciar") {
            contentType(ContentType.Application.Json)
            setBody(EventoRequest(fechaHoraEvento))
        }.toEnvelopeData()

    suspend fun finalizarRecorrido(id: Long): RecorridoDetalle =
        httpClient.patch("acopiador/recorridos/$id/finalizar").toEnvelopeData()

    suspend fun registrarAcopioParada(recorridoId: Long, paradaId: Long, request: RegistrarAcopioParadaRequest): AcopioParadaRespuesta =
        httpClient.post("acopiador/recorridos/$recorridoId/paradas/$paradaId/acopio") {
            contentType(ContentType.Application.Json)
            header("Idempotency-Key", request.token_idempotencia)
            setBody(request)
        }.toEnvelopeData()

    /** Idempotente en el backend: marcar otra vez una parada ya "no entregó" no cambia nada. */
    suspend fun noEntrego(recorridoId: Long, paradaId: Long, motivo: String, fechaHoraEvento: String? = null): NoEntregoRespuesta =
        httpClient.post("acopiador/recorridos/$recorridoId/paradas/$paradaId/no-entrego") {
            contentType(ContentType.Application.Json)
            setBody(NoEntregoRequest(motivo, fechaHoraEvento))
        }.toEnvelopeData()

    suspend fun miAsistencia(): MiAsistencia = httpClient.get("acopiador/mi-asistencia").toEnvelopeData()
}
