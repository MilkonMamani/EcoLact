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
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.Headers
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.contentType
import io.ktor.http.isSuccess
import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.network.toApiException
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData
import pe.edu.upeu.ecolactmobile.feature.trabajador.EventoRequest
import pe.edu.upeu.ecolactmobile.feature.trabajador.MiAsistencia

@Serializable
private data class SorteoRequest(val excluir: List<Long> = emptyList(), val fecha: String? = null)

class CalidadApi(private val httpClient: HttpClient) {

    /** Su QR de asistencia (lo escanea el administrador) e identidad de trabajador. Solo lectura. */
    suspend fun miAsistencia(): MiAsistencia = httpClient.get("calidad/mi-asistencia").toEnvelopeData()

    suspend fun dashboard(periodo: String? = null): CalidadDashboard =
        httpClient.get("calidad/dashboard") {
            periodo?.let { parameter("periodo", it) }
        }.toEnvelopeData()

    /**
     * Registro de entrega por muestreo directo (ver CalidadModels.kt) — la materialización real
     * de "el analista también puede acopiar". [idempotencyKey]: ver nota en
     * PlantaApi.iniciarProduccion.
     */
    // ---- Muestreo (flujo principal) ----

    /** Muestras pendientes (SELECCIONADA/EN_ANALISIS) + KPIs de Inicio. */
    suspend fun muestras(): MuestrasResponse = httpClient.get("calidad/muestreos").toEnvelopeData()

    /** Entregas que todavía se pueden seleccionar. [fecha]: "hoy" (por defecto), "ayer" o "semana". */
    suspend fun elegibles(q: String? = null, fecha: String? = null): ElegiblesResponse =
        httpClient.get("calidad/muestreos/elegibles") {
            q?.takeIf { it.isNotBlank() }?.let { parameter("q", it) }
            fecha?.let { parameter("fecha", it) }
        }.toEnvelopeData()

    /** El SERVIDOR sortea una entrega elegible (no guarda nada). 422 si no hay ninguna. */
    suspend fun sortearMuestra(excluir: List<Long>, fecha: String? = null): PropuestaAleatoria =
        httpClient.post("calidad/muestreos/aleatorio") {
            contentType(ContentType.Application.Json)
            setBody(SorteoRequest(excluir, fecha))
        }.toEnvelopeData()

    /** 409 ([pe.edu.upeu.ecolactmobile.core.network.ApiException.Conflict]) si otro analista ya la eligió. */
    suspend fun seleccionarMuestra(request: SeleccionarMuestraRequest, idempotencyKey: String? = null): MuestraCalidad =
        httpClient.post("calidad/muestreos") {
            contentType(ContentType.Application.Json)
            idempotencyKey?.let { header("Idempotency-Key", it) }
            setBody(request)
        }.toEnvelopeData()

    /**
     * OCR del ticket en el servidor: solo PROPONE valores. Un ticket ilegible responde 422 con
     * `success=false` y la foto guardada (`ticket_path`): eso no es un error para el analista,
     * que sigue ingresando a mano.
     */
    suspend fun escanearTicket(entregaId: Long, bytes: ByteArray, filename: String, mime: String, fechaHoraEvento: String? = null): TicketOcrResponse {
        val response = httpClient.post("calidad/analisis-calidad/escanear-ticket") {
            setBody(
                MultiPartFormDataContent(
                    formData {
                        append("entrega_id", entregaId.toString())
                        // Hora real de la foto: el servidor evalúa la asistencia de ese día.
                        fechaHoraEvento?.let { append("fecha_hora_evento", it) }
                        append(
                            "imagen",
                            bytes,
                            Headers.build {
                                append(HttpHeaders.ContentType, mime)
                                append(HttpHeaders.ContentDisposition, "filename=\"$filename\"")
                            },
                        )
                    },
                ),
            )
        }
        if (response.status.isSuccess() || response.status == HttpStatusCode.UnprocessableEntity) {
            val parsed = runCatching { ApiJson.decodeFromString<TicketOcrResponse>(response.bodyAsText()) }.getOrNull()
            if (parsed != null && (parsed.success || parsed.ticket_path != null)) return parsed
        }
        throw response.toApiException()
    }

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
        origenes: Map<String, String> = emptyMap(),
        ticketPath: String? = null,
        fechaAnalisis: String? = null,
        fechaCapturaLocal: String? = null,
    ): AnalisisBorradorInfo {
        val parts = formData {
            append("entrega_id", entregaId.toString())
            valores.forEach { (codigo, valor) -> append("valores[$codigo]", valor.toString()) }
            origenes.forEach { (codigo, origen) -> append("origenes[$codigo]", origen) }
            observacion?.let { append("observacion", it) }
            // Hora real del análisis/foto en el teléfono, distinta de la hora de sincronización.
            fechaAnalisis?.let { append("fecha_analisis", it) }
            fechaCapturaLocal?.let { append("fecha_captura_local", it) }
            if (imagenBytes == null) ticketPath?.let { append("ticket_path", it) }
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

    /**
     * Una vez confirmado, un nuevo `guardarAnalisis` sobre la misma entrega devuelve 422 (inmutable).
     * [fechaHoraEvento]: hora real del análisis, para que el servidor evalúe la asistencia de ese día.
     */
    suspend fun finalizarAnalisis(analisisId: Long, fechaHoraEvento: String? = null): AnalisisBorradorInfo =
        httpClient.patch("calidad/analisis-calidad/$analisisId/finalizar") {
            contentType(ContentType.Application.Json)
            setBody(EventoRequest(fechaHoraEvento))
        }.toEnvelopeData()

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
