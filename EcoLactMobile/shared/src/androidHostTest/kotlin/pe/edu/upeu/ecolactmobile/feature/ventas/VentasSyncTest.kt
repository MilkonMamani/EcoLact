package pe.edu.upeu.ecolactmobile.feature.ventas

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.*
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.*
import io.ktor.http.content.OutgoingContent
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.runBlocking
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.*
import pe.edu.upeu.ecolactmobile.feature.trabajador.*
import kotlin.test.*

class VentasSyncTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val db = EcoLactDatabase(driver)
    private val cache = CacheRepository(db)
    private val queue = SyncQueueRepository(db)
    private val online = MutableStateFlow(false)
    private var status = HttpStatusCode.Created
    private var response = """{"data":{"id":19,"producto_id":1,"cantidad":5,"precio_unitario":5,"importe_total":25,"tipo_cliente":"PUBLICO","forma_pago":"CONTADO","fecha":"2026-10-27T15:15:00Z"}}"""
    private val keys = mutableListOf<String?>()
    private var body = ""
    private val http = HttpClient(MockEngine { req ->
        keys += req.headers["Idempotency-Key"]
        body = (req.body as OutgoingContent.ByteArrayContent).bytes().decodeToString()
        respond(response, status, headersOf(HttpHeaders.ContentType, "application/json"))
    }) { install(ContentNegotiation) { json(ApiJson) } }
    private val api = VentasApi(http)
    private val gate = AsistenciaGate(AsistenciaApi(http), cache, 7, online) { "2026-10-27" }
    private val repo = VentasRepository(api, cache, queue, 7, { online.value }, gate)
    private val producto = ProductoVenta(1, "Queso Paria", 10.0, rangos = listOf(RangoVenta("PUBLICO", 0.0, null, 5.0, "PUBLICO")))
    private val request = VentaRequest(1, 5.0, fecha_hora_evento = "2026-10-27T15:15:00Z")
    private val quote = CotizacionVenta(5.0, "PUBLICO", 25.0)

    @AfterTest fun close() { gate.onDispose(); http.close(); driver.close() }
    private fun presente(fecha: String = "2026-10-27") { cache.put("asistencia.7", EstadoAsistenciaHoy(fecha, puede_operar = true)) }

    @Test fun efectivoOfflinePersisteYSeReconciliaSinDuplicar() = runBlocking {
        presente()
        val local = repo.registrar(request, producto, null, quote, "Lucía")
        assertEquals(1, queue.pending().size)
        val handler = VentasSyncHandler(api, cache) { 7 }
        val row = queue.pending().single()
        val result = handler.push(row.id, row.payload_json)
        assertEquals(SyncPushResult.Success(19), result)
        assertEquals(1, repo.ventas.first().size, "No duplicates while the queue acknowledgement is still being persisted")
        queue.markSynced(row.id, 19)
        // Simulate a complete HTTP history refresh that does not contain localId.
        val completa = cache.get<List<Venta>>("ventas.7.historial")!!.value.single().copy(localId = null)
        cache.put("ventas.7.historial", listOf(completa))
        val ventas = repo.ventas.first()
        assertEquals(1, ventas.size)
        assertEquals("V-0019", ventas.single().codigo)
        assertEquals(local.localId, ventas.single().localId)
        assertEquals("2026-10-27T15:15:00Z", ventas.single().fecha)
        assertEquals(listOf(local.localId), keys)
        assertTrue(body.contains("2026-10-27T15:15:00Z"))
        assertTrue(body.contains("precio_mostrado"))
    }

    @Test fun asistenciaAnteriorNoAutorizaYDescuentoOfflineNoSeEncola() = runBlocking {
        presente("2026-10-26")
        assertFailsWith<IllegalArgumentException> { repo.registrar(request, producto, null, quote, "Lucía") }
        presente()
        assertFailsWith<IllegalArgumentException> { repo.registrar(request.copy(forma_pago = "DESCUENTO_PLANILLA", cliente = "PROVEEDOR", proveedor_id = 3), producto, null, quote, "Lucía") }
        assertTrue(queue.all().isEmpty())
    }

    @Test fun reservasLocalesImpidenVenderMasDelStockConocido() = runBlocking {
        presente()
        repo.registrar(request, producto, null, quote, "Lucía")
        repo.registrar(request, producto, null, quote, "Lucía")
        assertFailsWith<IllegalArgumentException> { repo.registrar(request, producto, null, quote, "Lucía") }
        assertEquals(2, queue.pending().size)
    }

    @Test fun stockRechazadoQuedaVisibleYNoSeReintentaComoExito() = runBlocking {
        presente()
        repo.registrar(request, producto, null, quote, "Lucía")
        status = HttpStatusCode.UnprocessableEntity
        response = """{"message":"No hay stock suficiente del producto solicitado."}"""
        val row = queue.pending().single()
        val result = VentasSyncHandler(api, cache) { 7 }.push(row.id, row.payload_json)
        assertIs<SyncPushResult.Rejected>(result)
        queue.markNeedsReview(row.id, result.message)
        assertTrue(queue.pending().isEmpty())
        assertEquals("Error", repo.ventas.first().single().estadoVisible)
        assertTrue(repo.ventas.first().single().error!!.contains("stock cambió"))
    }

    @Test fun cuentaDiferenteNoEnviaNiConsumeLaOperacion() = runBlocking {
        presente()
        repo.registrar(request, producto, null, quote, "Lucía")
        val row = queue.pending().single()
        val handler = VentasSyncHandler(api, cache) { 8 }
        assertFalse(handler.canPush(row.payload_json))
        assertIs<SyncPushResult.TransientFailure>(handler.push(row.id, row.payload_json))
        assertTrue(keys.isEmpty())
    }

    @Test fun timeoutReutilizaLaMismaClaveYFecha() = runBlocking {
        presente()
        repo.registrar(request, producto, null, quote, "Lucía")
        val row = queue.pending().single()
        val handler = VentasSyncHandler(api, cache) { 7 }
        status = HttpStatusCode.InternalServerError
        assertIs<SyncPushResult.TransientFailure>(handler.push(row.id, row.payload_json))
        status = HttpStatusCode.Created
        assertIs<SyncPushResult.Success>(handler.push(row.id, row.payload_json))
        assertEquals<List<String?>>(listOf(row.id, row.id), keys)
    }
}
