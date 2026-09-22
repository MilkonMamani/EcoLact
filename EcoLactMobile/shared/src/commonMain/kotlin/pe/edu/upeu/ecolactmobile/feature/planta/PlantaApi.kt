package pe.edu.upeu.ecolactmobile.feature.planta

import io.ktor.client.HttpClient
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.parameter
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

class PlantaApi(private val httpClient: HttpClient) {

    suspend fun dashboard(): PlantaDashboard =
        httpClient.get("planta/dashboard").toEnvelopeData()

    suspend fun produccion(estado: String? = null): ProduccionListResponse =
        httpClient.get("planta/produccion") {
            estado?.let { parameter("estado", it) }
        }.toEnvelopeData()

    /** El acopio no separa la leche por entrega: este es el acumulado disponible, no una lista para elegir. */
    suspend fun lecheDisponible(): LecheDisponibleResponse =
        httpClient.get("planta/produccion/leche-disponible").toEnvelopeData()

    /**
     * [idempotencyKey]: UUID generado una sola vez por operación local (el `id` de la fila en
     * `sync_queue`), reutilizado en cada reintento — ver PROMPT_INICIAL.md sección 3 del backend.
     * Null cuando se llama fuera del flujo offline-capaz (no debería pasar en producción, pero no
     * se fuerza no-nulo para no acoplar esta Api a la cola de sincronización).
     */
    suspend fun iniciarProduccion(request: IniciarProduccionRequest, idempotencyKey: String? = null): LoteCreado =
        httpClient.post("planta/produccion") {
            contentType(ContentType.Application.Json)
            idempotencyKey?.let { header("Idempotency-Key", it) }
            setBody(request)
        }.toEnvelopeData()

    suspend fun loteDetalle(loteId: Long): LoteDetalleResponse =
        httpClient.get("planta/produccion/$loteId").toEnvelopeData()

    /** ⚠️ A diferencia del GET, esta respuesta NO viene anidada bajo `lote` — el objeto va directo en `data`. */
    suspend fun registrarResultado(loteId: Long, request: RegistrarResultadoRequest, idempotencyKey: String? = null): LoteDetalleInfo =
        httpClient.post("planta/produccion/$loteId/resultado") {
            contentType(ContentType.Application.Json)
            idempotencyKey?.let { header("Idempotency-Key", it) }
            setBody(request)
        }.toEnvelopeData()

    suspend fun insumos(nivel: String? = null, buscar: String? = null): InsumosListResponse =
        httpClient.get("planta/insumos") {
            nivel?.let { parameter("nivel", it) }
            buscar?.let { parameter("buscar", it) }
        }.toEnvelopeData()

    suspend fun insumoDetalle(id: Long): InsumoDetalleResponse =
        httpClient.get("planta/insumos/$id").toEnvelopeData()

    suspend fun movimientosInsumo(insumoId: Long? = null): MovimientosListResponse =
        httpClient.get("planta/insumos/movimientos") {
            insumoId?.let { parameter("insumo_id", it) }
        }.toEnvelopeData()

    suspend fun crearInsumo(request: CrearInsumoRequest): InsumoFila =
        httpClient.post("planta/insumos") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.toEnvelopeData()

    /** Ajuste manual (ENTRADA/SALIDA) — no el consumo automático de producción. 422 si el stock quedaría negativo. */
    suspend fun registrarMovimiento(insumoId: Long, request: RegistrarMovimientoRequest, idempotencyKey: String? = null): InsumoFila =
        httpClient.post("planta/insumos/$insumoId/movimiento") {
            contentType(ContentType.Application.Json)
            idempotencyKey?.let { header("Idempotency-Key", it) }
            setBody(request)
        }.toEnvelopeData()

    suspend fun productos(buscar: String? = null): ProductosListResponse =
        httpClient.get("planta/productos") {
            buscar?.let { parameter("buscar", it) }
        }.toEnvelopeData()

    suspend fun productoDetalle(id: Long): ProductoDetalleResponse =
        httpClient.get("planta/productos/$id").toEnvelopeData()
}
