package pe.edu.upeu.ecolactmobile.feature.calidad

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.request.HttpRequestData
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.content.OutgoingContent
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withTimeout
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
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
import kotlin.test.assertTrue

/**
 * Regla de muestreo vista desde el teléfono + reconexión: los handlers reales de la cola contra un
 * servidor simulado (MockEngine), en el mismo orden en que los despacha SyncManager.
 */
class CalidadMuestreoSyncTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val database = EcoLactDatabase(driver)
    private val cache = CacheRepository(database)
    private val cola = SyncQueueRepository(database)
    private val capturas = CalidadCapturaStore(database)
    private val peticiones = mutableListOf<HttpRequestData>()

    /** Respuestas del "servidor" por ruta; por defecto 500. */
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
    private val api = CalidadApi(http)
    private var online = false
    private val repo = CalidadRepository(api, cache, cola, capturas, isOnline = { online }, database = database)

    private fun entrega(id: Long, nombre: String, litros: Double) =
        EntregaElegible(entregaId = id, proveedorNombre = nombre, litros = litros, zonaNombre = "Collana 1", fechaHora = "2026-10-27T08:00:00-05:00")

    private val juan = entrega(1, "Juan", 40.0)
    private val maria = entrega(2, "María", 35.0)
    private val pedro = entrega(3, "Pedro", 48.0)
    private val lucia = entrega(4, "Lucía", 42.0)
    private val carlos = entrega(5, "Carlos", 37.0)

    @AfterTest
    fun cerrar() {
        http.close()
        driver.close()
    }

    private fun cuerpoDe(req: HttpRequestData): String = (req.body as? OutgoingContent.ByteArrayContent)?.bytes()?.decodeToString().orEmpty()

    private fun panel(): PanelMuestras = runBlocking { withTimeout(5_000) { repo.observePanel().first { it != null }!!.value } }

    private fun sembrar() {
        // Lo que dejó la última sincronización: 5 entregas elegibles hoy, ninguna muestra todavía.
        cache.put("calidad.elegibles.hoy", listOf(juan, maria, pedro, lucia, carlos))
        cache.put("calidad.muestras", MuestrasResponse(MuestrasKpis(), emptyList()))
    }

    /** Lo que hace SyncManager.syncNow() con cada fila pendiente, sin depender de ConnectivityObserver. */
    private suspend fun reconectar(vararg handlers: pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler): List<SyncPushResult> {
        online = true
        val porTipo = handlers.associateBy { it.operationType }
        return cola.pending().map { fila ->
            val r = porTipo.getValue(fila.operation_type).push(fila.id, fila.payload_json)
            when (r) {
                is SyncPushResult.Success -> cola.markSynced(fila.id, r.serverId)
                is SyncPushResult.Rejected -> cola.markNeedsReview(fila.id, r.message)
                is SyncPushResult.TransientFailure -> cola.markPending(fila.id)
            }
            r
        }
    }

    @Test
    fun inicio_cuenta_solo_muestras_de_5_entregas_2_seleccionadas() = runBlocking {
        sembrar()

        repo.seleccionar(maria, MetodoSeleccion.MANUAL, TipoControl.RUTINARIO, null, null)
        repo.seleccionar(lucia, MetodoSeleccion.MANUAL, TipoControl.DIRIGIDO, null, null)

        assertEquals(2, panel().pendientes.size)
        // Manual sin conexión: la lista sale de la copia local y ya no ofrece las elegidas.
        val elegibles = repo.elegibles("hoy")
        assertIs<CacheOutcome.Stale<List<EntregaElegible>>>(elegibles)
        assertEquals(listOf("Juan", "Pedro", "Carlos"), elegibles.data.map { it.proveedorNombre })
    }

    @Test
    fun aleatorio_offline_bloqueado_sin_llamar_al_servidor() = runBlocking {
        val r = repo.sortear(emptyList())

        assertTrue(r.isFailure)
        assertEquals(CalidadRepository.MENSAJE_ALEATORIA_OFFLINE, r.exceptionOrNull()?.message)
        assertTrue(peticiones.isEmpty())
    }

    @Test
    fun aleatorio_online_lo_decide_el_servidor_y_se_confirma_con_su_firma() = runBlocking {
        online = true
        servidor = { req ->
            when (req.url.encodedPath.substringAfter("calidad/")) {
                "muestreos/aleatorio" -> HttpStatusCode.OK to """{"data":{"entrega":${ApiJson.encodeToString(pedro)},"propuesta_token":"firma-servidor"}}"""
                "muestreos" -> HttpStatusCode.Created to """{"data":{"id":9,"entregaId":3,"proveedorNombre":"Pedro","litros":48.0,"fechaHora":"2026-10-27T08:00:00-05:00","metodoSeleccion":"ALEATORIA","fechaSeleccion":"2026-10-27T09:00:00-05:00","estado":"SELECCIONADA"}}"""
                else -> HttpStatusCode.InternalServerError to "{}"
            }
        }

        val propuesta = repo.sortear(emptyList()).getOrThrow()
        val r = repo.seleccionar(propuesta.entrega, MetodoSeleccion.ALEATORIA, null, null, propuesta.propuesta_token)

        assertEquals("Pedro", propuesta.entrega.proveedorNombre)
        assertIs<SeleccionResult.Confirmada>(r)
        val cuerpo = cuerpoDe(peticiones.single { it.method.value == "POST" && it.url.encodedPath.endsWith("calidad/muestreos") })
        assertTrue("\"propuesta_token\":\"firma-servidor\"" in cuerpo)
        assertTrue("\"metodo_seleccion\":\"ALEATORIA\"" in cuerpo)
    }

    @Test
    fun reconexion_envia_en_orden_conflicto_ocr_remoto_a_revision_y_analisis_con_hora_real() = runBlocking {
        sembrar()
        // Sin conexión: selecciona María y Lucía, foto del ticket de María, análisis de Lucía.
        repo.seleccionar(maria, MetodoSeleccion.MANUAL, TipoControl.RUTINARIO, null, null)
        repo.seleccionar(lucia, MetodoSeleccion.MANUAL, TipoControl.RUTINARIO, null, null)
        repo.capturarTicket(2, null, ImagenAdjunta(byteArrayOf(1, 2), "ticket.jpg", "image/jpeg"), fechaCaptura = "2026-10-27T13:23:00Z")
        repo.confirmarAnalisis(4, mapOf("GRASA" to 4.2), mapOf("GRASA" to "MANUAL"), null, fechaAnalisis = "2026-10-27T13:25:00Z")

        servidor = { req ->
            val ruta = req.url.encodedPath.substringAfter("calidad/")
            val cuerpo = cuerpoDe(req)
            when {
                // Otro analista ya eligió a Lucía mientras este estaba sin señal.
                ruta == "muestreos" && "\"entrega_id\":4" in cuerpo -> HttpStatusCode.Conflict to """{"message":"Esta entrega ya fue seleccionada para análisis."}"""
                ruta == "muestreos" -> HttpStatusCode.Created to """{"data":{"id":7,"entregaId":2,"proveedorNombre":"María","litros":35.0,"fechaHora":"2026-10-27T08:00:00-05:00","metodoSeleccion":"MANUAL","fechaSeleccion":"2026-10-27T09:00:00-05:00","estado":"SELECCIONADA"}}"""
                ruta == "analisis-calidad/escanear-ticket" -> HttpStatusCode.OK to """{"success":true,"ticket_path":"analisis-calidad/tickets/entrega-2-x.jpg","valores":[{"parametro_id":1,"codigo":"GRASA","nombre":"Grasa","valor":4.1}],"mensaje":"Revisa la información"}"""
                // El análisis de Lucía ya no es de una muestra suya: el servidor lo rechaza.
                ruta == "analisis-calidad" -> HttpStatusCode.UnprocessableEntity to """{"message":"Esta entrega no fue seleccionada como muestra."}"""
                else -> HttpStatusCode.InternalServerError to "{}"
            }
        }

        val resultados = reconectar(
            CalidadSeleccionarMuestraSyncHandler(api),
            CalidadSubirTicketSyncHandler(api, capturas),
            CalidadAnalisisSyncHandler(api, capturas),
        )

        assertIs<SyncPushResult.Success>(resultados[0])
        assertEquals("Esta entrega ya fue seleccionada para análisis.", (resultados[1] as SyncPushResult.Rejected).message)
        // OCR remoto: solo propone valores, nunca confirma.
        val captura = capturas.obtener(2)!!
        assertEquals(EstadoCaptura.REQUIERE_REVISION, captura.estado)
        assertEquals(mapOf("GRASA" to 4.1), captura.valoresDetectados)
        assertEquals("2026-10-27T13:23:00Z", captura.fechaCapturaLocal)
        assertIs<SyncPushResult.Success>(resultados[2])
        assertEquals(4, resultados.size)
        assertIs<SyncPushResult.Rejected>(resultados[3])
        // El análisis guardado conserva la hora real del evento, no la de sincronización.
        assertTrue(cola.byOperationType(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS).single().payload_json.contains("2026-10-27T13:25:00Z"))

        // SQLite: el conflicto se muestra una vez y Lucía no vuelve como pendiente.
        val p = panel()
        assertEquals("Esta entrega ya fue seleccionada para análisis.", p.conflictos.single().mensaje)
        assertTrue(p.pendientes.none { it.entregaId == 4L })
    }
}
