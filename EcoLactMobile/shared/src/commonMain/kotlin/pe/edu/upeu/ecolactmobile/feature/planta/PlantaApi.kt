package pe.edu.upeu.ecolactmobile.feature.planta

import io.ktor.client.HttpClient
import io.ktor.client.request.forms.MultiPartFormDataContent
import io.ktor.client.request.forms.formData
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.parameter
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.Headers
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.contentType
import io.ktor.http.isSuccess
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.network.toApiException
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

/**
 * Api\Planta del backend. Solo lectura para recetas y precios (los configura el ADMINISTRADOR);
 * las escrituras que la app puede reintentar desde la cola offline (iniciar/finalizar producción,
 * registrar compra) envían `Idempotency-Key` con el id local de la operación.
 */
class PlantaApi(private val httpClient: HttpClient) {

    suspend fun perfil(): PlantaPerfil = httpClient.get("planta/perfil").toEnvelopeData()

    suspend fun produccion(estado: String): ProduccionListResponse =
        httpClient.get("planta/produccion") {
            parameter("estado", estado)
            parameter("per_page", 100)
        }.toEnvelopeData()

    suspend fun loteDetalle(loteId: Long): LoteDetalleResponse =
        httpClient.get("planta/produccion/$loteId").toEnvelopeData()

    /** Mismo cálculo que "iniciar", sin descontar nada. */
    suspend fun requerimientos(request: ProduccionRequest): RequerimientosResponse =
        httpClient.post("planta/produccion/requerimientos") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.toEnvelopeData()

    /** 422 si falta stock o el producto no tiene receta activa: el servidor siempre revalida. */
    suspend fun iniciarProduccion(request: ProduccionRequest, idempotencyKey: String): LoteCreado =
        httpClient.post("planta/produccion") {
            contentType(ContentType.Application.Json)
            header("Idempotency-Key", idempotencyKey)
            setBody(request)
        }.toEnvelopeData()

    /** La respuesta va directo en `data` (sin anidar bajo `lote`). */
    suspend fun finalizarProduccion(loteId: Long, request: FinalizarProduccionRequest, idempotencyKey: String): LoteDetalle =
        httpClient.post("planta/produccion/$loteId/finalizar") {
            contentType(ContentType.Application.Json)
            header("Idempotency-Key", idempotencyKey)
            setBody(request)
        }.toEnvelopeData()

    suspend fun insumos(): InsumosListResponse = httpClient.get("planta/insumos").toEnvelopeData()

    suspend fun insumoDetalle(id: Long): InsumoDetalleResponse = httpClient.get("planta/insumos/$id").toEnvelopeData()

    suspend fun productos(): ProductosListResponse = httpClient.get("planta/productos").toEnvelopeData()

    suspend fun productoDetalle(id: Long): ProductoDetalleResponse = httpClient.get("planta/productos/$id").toEnvelopeData()

    suspend fun recetas(): RecetasListResponse = httpClient.get("planta/recetas").toEnvelopeData()

    suspend fun catalogoCompra(): List<CatalogoInsumo> = httpClient.get("planta/compras-insumo/catalogo").toEnvelopeData()

    suspend fun compras(): List<CompraReciente> = httpClient.get("planta/compras-insumo").toEnvelopeData()

    /**
     * OCR de la boleta en el servidor: solo PROPONE líneas. Una boleta ilegible responde 422 con
     * `success=false` y la foto ya guardada (`imagen_boleta`): no es un error para la persona, que
     * sigue asociando los insumos a mano.
     */
    suspend fun leerBoleta(bytes: ByteArray, filename: String, mime: String, fechaHoraEvento: String? = null): BoletaOcrResponse {
        val response = httpClient.post("planta/compras-insumo/boleta") {
            setBody(
                MultiPartFormDataContent(
                    formData {
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
            val parsed = runCatching { ApiJson.decodeFromString<BoletaOcrResponse>(response.bodyAsText()) }.getOrNull()
            if (parsed != null && (parsed.success || parsed.imagen_boleta != null)) return parsed
        }
        throw response.toApiException()
    }

    /** CONFIRMAR COMPRA: el único momento en que la compra aumenta el stock. */
    suspend fun registrarCompra(request: CompraRequest, idempotencyKey: String): CompraRegistrada =
        httpClient.post("planta/compras-insumo") {
            contentType(ContentType.Application.Json)
            header("Idempotency-Key", idempotencyKey)
            setBody(request)
        }.toEnvelopeData()
}
