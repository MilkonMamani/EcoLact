package pe.edu.upeu.ecolactmobile.feature.planta

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.HttpRequestData
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.ui.components.ImagenAdjunta
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertIs
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

/**
 * Planta sin conexión contra la SQLite real de la app (JDBC en memoria) y un servidor simulado:
 * QR guardado, producción con conflicto de stock al sincronizar, y boleta tomada offline que pasa
 * por OCR → revisión → confirmación (nunca stock automático).
 */
class PlantaOfflineTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val database = EcoLactDatabase(driver)
    private val cache = CacheRepository(database)
    private val cola = SyncQueueRepository(database)
    private val compras = PlantaCompraStore(database)
    private val peticiones = mutableListOf<HttpRequestData>()

    /** Respuestas del "servidor" por petición; por defecto 500. */
    private var servidor: (HttpRequestData) -> Pair<HttpStatusCode, String> = { HttpStatusCode.InternalServerError to "{}" }

    private val http = HttpClient(
        MockEngine { req ->
            peticiones += req
            val (status, cuerpo) = servidor(req)
            respond(cuerpo, status, headersOf(HttpHeaders.ContentType, "application/json"))
        },
    ) {
        expectSuccess = false
        install(ContentNegotiation) { json(ApiJson) }
    }
    private val api = PlantaApi(http)
    private var online = false
    private val repo = PlantaRepository(api, cache, cola, compras, isOnline = { online }, userId = 5)

    private val queso = RecetaFila(
        id = 1,
        productoId = 10,
        producto = "Queso Fresco 1 kg",
        productoNombreBase = "Queso Fresco",
        presentacion = "1 kg",
        por_unidad = listOf(InsumoPorUnidad(3, "Sal", "g", 3.0)),
    )

    @AfterTest
    fun cerrar() {
        http.close()
        driver.close()
    }

    @Test
    fun mi_qr_sigue_disponible_sin_conexion() = runBlocking {
        cache.put("planta.5.perfil", PlantaPerfil(user_id = 5, nombres = "Carlos", apellidos = "Pérez", dni = "41234567", qr_token = "QR-TOKEN-5"))

        val perfil = repo.observePerfil().first()!!.value

        assertEquals("QR-TOKEN-5", perfil.qr_token)
        assertEquals("41234567", perfil.dni)
        assertTrue(peticiones.isEmpty(), "el QR se dibuja desde SQLite, sin pedir nada a la API")
    }

    @Test
    fun produccion_offline_que_el_servidor_rechaza_queda_como_conflicto_sin_crear_lote() = runBlocking {
        val r = repo.iniciarProduccion(queso, 500.0)
        assertIs<OperacionResult.GuardadoOffline>(r)
        val fila = cola.byOperationType(SyncOperationTypes.PLANTA_INICIAR_PRODUCCION).single()

        // Vuelve internet, pero el inventario cambió: el servidor revalida y responde 422.
        servidor = { HttpStatusCode.UnprocessableEntity to """{"message":"No hay suficiente Sal.","motivos":["No hay suficiente Sal."]}""" }
        val push = PlantaIniciarProduccionSyncHandler(api).push(fila.id, fila.payload_json)

        assertIs<SyncPushResult.Rejected>(push)
        assertEquals(CONFLICTO_PRODUCCION_OFFLINE, push.message)
        assertEquals(fila.id, peticiones.single().headers["Idempotency-Key"], "el reintento usa siempre el id local como clave")

        cola.markNeedsReview(fila.id, push.message)
        val pendiente = repo.observeLotesPendientes().first().single()
        assertEquals(CONFLICTO_PRODUCCION_OFFLINE, pendiente.conflicto)
    }

    @Test
    fun boleta_offline_pendiente_ocr_luego_revision_y_solo_la_persona_confirma() = runBlocking {
        val r = repo.capturarBoleta(ImagenAdjunta(byteArrayOf(1, 2, 3), "boleta.jpg", "image/jpeg"))
        assertIs<CapturaBoletaResult.GuardadaOffline>(r)
        assertEquals(EstadoBorrador.PENDIENTE_OCR, compras.obtener(r.borradorId)!!.estado)
        val filaOcr = cola.byOperationType(SyncOperationTypes.PLANTA_SUBIR_BOLETA).single()

        // Vuelve internet: la foto se sube sola y el servidor PROPONE "2 sacos de sal".
        online = true
        servidor = {
            HttpStatusCode.OK to """
                {"success":true,"lineas":[{"texto":"2 SACOS SAL","cantidad":2,"presentacion_detectada":"Saco","insumo_id":3,"presentacion_id":9}],
                 "cabecera":{"total":200.0},"imagen_boleta":"compras-insumo/boletas/boleta-abcdefghijklmnopqrstuvwx.jpg"}
            """.trimIndent()
        }
        assertIs<SyncPushResult.Success>(PlantaSubirBoletaSyncHandler(api, compras).push(filaOcr.id, filaOcr.payload_json))

        val revisar = compras.obtener(r.borradorId)!!
        assertEquals(EstadoBorrador.REQUIERE_REVISION, revisar.estado)
        assertEquals(2.0, revisar.datos.lineas.single().cantidad)
        assertNotNull(revisar.imagenBoleta)
        assertTrue(cola.byOperationType(SyncOperationTypes.PLANTA_REGISTRAR_COMPRA).isEmpty(), "el OCR nunca registra la compra")

        // La persona revisa y confirma: recién ahí se registra, con el id del borrador como clave.
        servidor = { req ->
            if (req.url.encodedPath.endsWith("compras-insumo")) HttpStatusCode.Created to """{"data":{"id":31,"referencia":"COMPRA-00031"}}""" else HttpStatusCode.OK to """{"data":{"kpis":{},"filas":[]}}"""
        }
        val confirmada = repo.confirmarCompra(r.borradorId, revisar.datos)

        assertIs<OperacionResult.Confirmado>(confirmada)
        assertEquals(31L, confirmada.serverId)
        val registro = peticiones.first { it.url.encodedPath.endsWith("compras-insumo") }
        assertEquals(r.borradorId, registro.headers["Idempotency-Key"])
        assertNull(compras.obtener(r.borradorId), "registrada en el servidor: el borrador se borra")
    }

    @Test
    fun compra_confirmada_sin_conexion_queda_pendiente_de_envio_una_sola_vez() = runBlocking {
        val id = repo.crearBorradorManual(insumoId = 3)
        val datos = BorradorCompra(lineas = listOf(LineaBorrador(insumoId = 3, presentacionId = 9, cantidad = 2.0)))

        assertIs<OperacionResult.GuardadoOffline>(repo.confirmarCompra(id, datos))
        // Reintentar la confirmación no duplica la operación en la cola.
        repo.confirmarCompra(id, datos)

        assertEquals(1, cola.byOperationType(SyncOperationTypes.PLANTA_REGISTRAR_COMPRA).size)
        assertEquals(EstadoBorrador.PENDIENTE_SYNC, compras.obtener(id)!!.estado)
    }
}
