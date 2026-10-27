package pe.edu.upeu.ecolactmobile.feature.trabajador

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withTimeout
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncManager
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorApi
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorProveedorFila
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.MetodoIdentificacion
import pe.edu.upeu.ecolactmobile.feature.acopiador.RegistrarEntregaResult
import pe.edu.upeu.ecolactmobile.feature.planta.LineaProduccion
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaApi
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaIniciarProduccionPayload
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaIniciarProduccionSyncHandler
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertIs
import kotlin.test.assertTrue

/**
 * Regla global de operación en el teléfono: solo una confirmación del servidor con la fecha EXACTA
 * de hoy habilita operar sin conexión; la de ayer no; el servidor revalida al sincronizar.
 */
class AsistenciaGateTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val database = EcoLactDatabase(driver)
    private val cache = CacheRepository(database)
    private val cola = SyncQueueRepository(database)
    private val online = MutableStateFlow(false)
    private var respuestaAsistencia = """{"data":{"fecha":"2026-10-27","requiere_asistencia":true,"puede_operar":false,"estado":"SIN_ASISTENCIA"}}"""
    private var respuestaOperacion: Pair<HttpStatusCode, String> = HttpStatusCode.InternalServerError to "{}"

    private val http = HttpClient(
        MockEngine { req ->
            val (status, cuerpo) = if (req.url.encodedPath.endsWith("me/asistencia")) HttpStatusCode.OK to respuestaAsistencia else respuestaOperacion
            respond(cuerpo, status, headersOf(HttpHeaders.ContentType, "application/json"))
        },
    ) {
        expectSuccess = false
        install(ContentNegotiation) { json(ApiJson) }
    }

    private var hoy = "2026-10-27"
    private val gate = AsistenciaGate(AsistenciaApi(http), cache, userId = 7, isOnline = online, hoy = { hoy })
    private val acopiador = AcopiadorRepository(AcopiadorApi(http), cache, cola, isOnline = { online.value }, userId = 7, puedeOperarSinConexion = gate::puedeOperarSinConexion)
    private val maria = AcopiadorProveedorFila(10, "41234567", "María", "Quispe", "María Quispe", zona_id = 1, zona_nombre = "Collana 1", estado = "ACTIVO", qr_token = "qr-maria")

    @AfterTest
    fun cerrar() {
        gate.onDispose()
        http.close()
        driver.close()
    }

    private fun confirmado(fecha: String) =
        cache.put("asistencia.7", EstadoAsistenciaHoy(fecha = fecha, requiere_asistencia = true, puede_operar = true, estado = "TIENE_ASISTENCIA", hora_marcacion = "${fecha}T06:55:00-05:00"))

    @Test
    fun la_regla_local_exige_confirmacion_del_servidor_con_la_fecha_de_hoy() {
        val hoyOk = EstadoAsistenciaHoy("2026-10-27", puede_operar = true)
        assertEquals(Autorizacion.HABILITADA, autorizacion(hoyOk, enLinea = false, hoy = "2026-10-27"))
        assertEquals(Autorizacion.NO_VERIFICADA, autorizacion(null, enLinea = false, hoy = "2026-10-27"))
        assertEquals(Autorizacion.PENDIENTE, autorizacion(null, enLinea = true, hoy = "2026-10-27"))
        // Ayer presente no autoriza hoy (y a medianoche deja de valer sola).
        assertEquals(Autorizacion.NO_VERIFICADA, autorizacion(EstadoAsistenciaHoy("2026-10-26", puede_operar = true), enLinea = false, hoy = "2026-10-27"))
        assertEquals(Autorizacion.PENDIENTE, autorizacion(EstadoAsistenciaHoy("2026-10-27", puede_operar = false, estado = "FALTA"), enLinea = true, hoy = "2026-10-27"))
        // Roles que no marcan asistencia (administrador).
        assertEquals(Autorizacion.HABILITADA, autorizacion(EstadoAsistenciaHoy("2026-10-01", requiere_asistencia = false, puede_operar = true), enLinea = false, hoy = "2026-10-27"))
    }

    @Test
    fun caso8_asistencia_confirmada_hoy_permite_registrar_sin_conexion() = runBlocking {
        confirmado("2026-10-27")

        val r = acopiador.registrarEntrega(maria, MetodoIdentificacion.QR, 25.0, null, fechaHoraEvento = "2026-10-27T09:35:00-05:00")

        assertIs<RegistrarEntregaResult.GuardadaOffline>(r)
        assertEquals(1, cola.byOperationType(SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA).size)
    }

    @Test
    fun caso9_sin_asistencia_confirmada_no_se_encola_nada() = runBlocking {
        val r = acopiador.registrarEntrega(maria, MetodoIdentificacion.QR, 25.0, null)

        assertIs<RegistrarEntregaResult.Rechazada>(r)
        assertEquals(MENSAJE_ASISTENCIA_NO_VERIFICADA, r.mensaje)
        assertTrue(cola.all().isEmpty())
    }

    @Test
    fun caso10_la_asistencia_de_ayer_no_habilita_hoy() = runBlocking {
        confirmado("2026-10-26")

        assertFalse(gate.puedeOperarSinConexion())
        assertIs<RegistrarEntregaResult.Rechazada>(acopiador.registrarEntrega(maria, MetodoIdentificacion.QR, 25.0, null))

        // Y el cambio de día deja vencida la de hoy.
        confirmado("2026-10-27")
        assertTrue(gate.puedeOperarSinConexion())
        hoy = "2026-10-28"
        assertFalse(gate.puedeOperarSinConexion())
    }

    @Test
    fun al_registrar_el_administrador_la_asistencia_la_app_se_habilita_sola() = runBlocking {
        online.value = true
        withTimeout(5_000) { gate.estado.first { it == Autorizacion.PENDIENTE } }

        respuestaAsistencia = """{"data":{"fecha":"2026-10-27","requiere_asistencia":true,"puede_operar":true,"estado":"TIENE_ASISTENCIA","hora_marcacion":"2026-10-27T06:55:00-05:00"}}"""
        gate.refresh()

        assertEquals(Autorizacion.HABILITADA, withTimeout(5_000) { gate.estado.first { it == Autorizacion.HABILITADA } })
        // Ya confirmada por el servidor con la fecha de hoy: sirve también si se pierde la conexión.
        online.value = false
        assertTrue(gate.puedeOperarSinConexion())
    }

    @Test
    fun caso12_si_el_servidor_rechaza_por_asistencia_la_operacion_queda_para_revision_con_mensaje_claro() = runBlocking {
        respuestaOperacion = HttpStatusCode.Forbidden to """{"message":"Debes registrar tu asistencia antes de realizar operaciones.","code":"ASISTENCIA_REQUERIDA"}"""
        val payload = ApiJson.encodeToString(PlantaIniciarProduccionPayload(listOf(LineaProduccion(10, 5.0)), "Queso", 5.0, "2026-10-27T09:35:00-05:00"))

        val r = PlantaIniciarProduccionSyncHandler(PlantaApi(http)).push("op-1", payload)

        assertIs<SyncPushResult.Rejected>(r)
        assertEquals(ApiException.MENSAJE_ASISTENCIA_REQUERIDA, r.message)
        assertEquals(
            "No se pudo sincronizar esta operación porque no existe una asistencia válida para ese momento.",
            SyncManager.mensajeParaRevision(r.message),
        )
    }
}
