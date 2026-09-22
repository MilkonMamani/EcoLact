package pe.edu.upeu.ecolactmobile.feature.calidad

import io.ktor.client.HttpClient
import io.ktor.client.request.forms.MultiPartFormDataContent
import io.ktor.client.request.forms.formData
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.parameter
import io.ktor.client.request.patch
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.Headers
import io.ktor.http.HttpHeaders
import io.ktor.http.contentType
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

class CalidadApi(private val httpClient: HttpClient) {

    suspend fun dashboard(periodo: String? = null): CalidadDashboard =
        httpClient.get("calidad/dashboard") {
            periodo?.let { parameter("periodo", it) }
        }.toEnvelopeData()

    /**
     * Registro de entrega por muestreo directo (ver CalidadModels.kt) — la materialización real
     * de "el analista también puede acopiar". [idempotencyKey]: ver nota en
     * PlantaApi.iniciarProduccion.
     */
    suspend fun registrarEntregaMuestreada(request: CalidadEntregaMuestreadaRequest, idempotencyKey: String? = null): CalidadEntregaMuestreada =
        httpClient.post("calidad/entregas") {
            contentType(ContentType.Application.Json)
            idempotencyKey?.let { header("Idempotency-Key", it) }
            setBody(request)
        }.toEnvelopeData()

    suspend fun pendientes(q: String? = null, zonaId: Long? = null): PendientesListResponse =
        httpClient.get("calidad/analisis-calidad") {
            parameter("tab", "pendientes")
            q?.let { parameter("q", it) }
            zonaId?.let { parameter("zona_id", it) }
        }.toEnvelopeData()

    suspend fun historial(q: String? = null, zonaId: Long? = null, resultado: String? = null): HistorialListResponse =
        httpClient.get("calidad/analisis-calidad") {
            parameter("tab", "historial")
            q?.let { parameter("q", it) }
            zonaId?.let { parameter("zona_id", it) }
            resultado?.let { parameter("resultado", it) }
        }.toEnvelopeData()

    /** Detalle previo a analizar: null si la entrega no tiene ningún borrador todavía. */
    suspend fun analisisPrevio(entregaId: Long): AnalisisPrevioResponse =
        httpClient.get("calidad/entregas/$entregaId/analisis-calidad").toEnvelopeData()

    suspend fun historialDetalle(analisisId: Long): AnalisisDetalleResponse =
        httpClient.get("calidad/analisis-calidad/$analisisId").toEnvelopeData()

    /**
     * Evidencia + Parámetros + Resultado son UN SOLO endpoint (calidad.md sección 4): se puede
     * llamar varias veces mientras el análisis siga en borrador. `valores` se indexa por CÓDIGO
     * del parámetro (ej. "PH"), no por su id.
     */
    suspend fun guardarAnalisis(
        entregaId: Long,
        valores: Map<String, Double>,
        observacion: String?,
        imagenBytes: ByteArray?,
        imagenFilename: String?,
    ): AnalisisBorradorInfo {
        val parts = formData {
            append("entrega_id", entregaId.toString())
            valores.forEach { (codigo, valor) -> append("valores[$codigo]", valor.toString()) }
            observacion?.let { append("observacion", it) }
            if (imagenBytes != null && imagenFilename != null) {
                append(
                    "imagen",
                    imagenBytes,
                    Headers.build { append(HttpHeaders.ContentDisposition, "filename=\"$imagenFilename\"") },
                )
            }
        }
        return httpClient.post("calidad/analisis-calidad") {
            setBody(MultiPartFormDataContent(parts))
        }.toEnvelopeData()
    }

    /** Sin body. Una vez confirmado, un nuevo `guardarAnalisis` sobre la misma entrega devuelve 422 (inmutable). */
    suspend fun finalizarAnalisis(analisisId: Long): AnalisisBorradorInfo =
        httpClient.patch("calidad/analisis-calidad/$analisisId/finalizar").toEnvelopeData()

    suspend fun proveedores(q: String? = null, zonaId: Long? = null): CalidadProveedoresListResponse =
        httpClient.get("calidad/proveedores") {
            q?.let { parameter("q", it) }
            zonaId?.let { parameter("zona_id", it) }
        }.toEnvelopeData()

    suspend fun proveedorDetalle(id: Long): CalidadProveedorDetalleResponse =
        httpClient.get("calidad/proveedores/$id").toEnvelopeData()

    suspend fun comunicados(): List<CalidadComunicado> =
        httpClient.get("calidad/comunicados").toEnvelopeData()

    suspend fun comunicadoDetalle(id: Long): CalidadComunicado =
        httpClient.get("calidad/comunicados/$id").toEnvelopeData()

    /** A diferencia de Acopiador/Proveedor, Calidad SÍ puede crear comunicados (ej. proponer capacitaciones). */
    suspend fun crearComunicado(request: CrearComunicadoRequest): CalidadComunicado =
        httpClient.post("calidad/comunicados") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.toEnvelopeData()
}
