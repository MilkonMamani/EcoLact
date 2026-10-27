package pe.edu.upeu.ecolactmobile.feature.calidad

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withTimeout
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
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
 * Operación sin conexión contra la SQLite real de la app (driver JDBC en memoria): selección,
 * foto, análisis y conflicto quedan guardados con su hora real y en la cola de sincronización.
 */
class CalidadOfflineTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val database = EcoLactDatabase(driver)
    private val cache = CacheRepository(database)
    private val cola = SyncQueueRepository(database)
    private val capturas = CalidadCapturaStore(database)
    private val http = HttpClient()
    private val repo = CalidadRepository(CalidadApi(http), cache, cola, capturas, isOnline = { false }, database = database)

    private val elegible = EntregaElegible(entregaId = 7, proveedorNombre = "Juan Quispe", litros = 45.0, zonaNombre = "Collana 1", fechaHora = "2026-10-27T08:25:00-05:00")

    @AfterTest
    fun cerrar() {
        http.close()
        driver.close()
    }

    private fun sembrarMuestras(vararg filas: MuestraCalidad) =
        cache.put("calidad.muestras", MuestrasResponse(MuestrasKpis(pendientes = filas.size), filas.toList()))

    private fun panel(): PanelMuestras = runBlocking { withTimeout(5_000) { repo.observePanel().first { it != null }!!.value } }

    @Test
    fun caso5_seleccion_offline_se_guarda_en_la_cola_y_aparece_en_pendientes() = runBlocking {
        sembrarMuestras()

        val r = repo.seleccionar(elegible, MetodoSeleccion.MANUAL, TipoControl.DIRIGIDO, "Sospecha de agua", null)

        assertIs<SeleccionResult.GuardadaOffline>(r)
        val fila = cola.byOperationType(SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA).single()
        val payload = ApiJson.decodeFromString<CalidadSeleccionMuestraSyncPayload>(fila.payload_json)
        assertEquals("MANUAL", payload.request.metodo_seleccion)
        assertEquals("DIRIGIDO", payload.request.tipo_control)
        assertNotNull(payload.request.fecha_seleccion)
        assertEquals(listOf(7L), panel().pendientes.map { it.entregaId })
    }

    @Test
    fun el_sorteo_aleatorio_nunca_se_hace_en_el_telefono() = runBlocking {
        assertTrue(repo.sortear(emptyList()).isFailure)
    }

    @Test
    fun caso8_foto_offline_queda_pendiente_de_ocr_y_el_ocr_remoto_solo_la_pasa_a_revision() = runBlocking {
        val r = repo.capturarTicket(7, null, ImagenAdjunta(byteArrayOf(1, 2, 3), "ticket.jpg", "image/jpeg"), fechaCaptura = "2026-10-27T13:23:00Z")

        assertIs<CapturaResult.GuardadaOffline>(r)
        assertEquals(EstadoCaptura.PENDIENTE_OCR, capturas.obtener(7)!!.estado)
        assertEquals(1, cola.byOperationType(SyncOperationTypes.CALIDAD_SUBIR_TICKET).size)

        // Vuelve internet: el servidor lee el ticket (lo hace CalidadSubirTicketSyncHandler).
        capturas.marcarOcrProcesado(7, "analisis-calidad/tickets/entrega-7-x.jpg", mapOf("GRASA" to 4.2), "Revisa la información")

        val revisada = capturas.obtener(7)!!
        assertEquals(EstadoCaptura.REQUIERE_REVISION, revisada.estado)
        assertEquals(mapOf("GRASA" to 4.2), revisada.valoresDetectados)
        assertNull(revisada.valoresDetectados["PH"])
        assertEquals("2026-10-27T13:23:00Z", revisada.fechaCapturaLocal)
        assertNotNull(revisada.fechaSincronizacion)
        // Nada se confirmó solo: no hay análisis en la cola.
        assertTrue(cola.byOperationType(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS).isEmpty())

        // Un segundo resultado de OCR no pisa una captura que ya está en revisión.
        capturas.marcarOcrProcesado(7, null, emptyMap(), "otro")
        assertEquals(mapOf("GRASA" to 4.2), capturas.obtener(7)!!.valoresDetectados)
    }

    @Test
    fun caso5_analisis_offline_guarda_hora_real_de_foto_y_analisis_y_sale_de_pendientes() = runBlocking {
        sembrarMuestras(MuestraCalidad(1, 7, "Juan Quispe", litros = 45.0, fechaHora = "2026-10-27T08:25:00-05:00", metodoSeleccion = "ALEATORIA", fechaSeleccion = "2026-10-27T08:30:00-05:00", estado = "SELECCIONADA"))
        repo.capturarTicket(7, 1, ImagenAdjunta(byteArrayOf(9), "ticket.jpg", "image/jpeg"), fechaCaptura = "2026-10-27T13:23:00Z")

        val r = repo.confirmarAnalisis(7, mapOf("GRASA" to 4.2), mapOf("GRASA" to "MANUAL"), null, fechaAnalisis = "2026-10-27T13:25:00Z")

        assertIs<ConfirmarAnalisisResult.GuardadoOffline>(r)
        assertTrue(r.conFoto)
        val payload = ApiJson.decodeFromString<CalidadAnalisisSyncPayload>(cola.byOperationType(SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS).single().payload_json)
        assertEquals("2026-10-27T13:25:00Z", payload.fecha_analisis)
        assertEquals("2026-10-27T13:23:00Z", payload.fecha_captura_local)
        assertNotNull(payload.imagen_base64)
        // La foto viaja con el análisis: ya no hace falta subirla para OCR.
        assertTrue(cola.byOperationType(SyncOperationTypes.CALIDAD_SUBIR_TICKET).isEmpty())
        assertTrue(panel().pendientes.isEmpty())
    }

    @Test
    fun conflicto_al_sincronizar_se_muestra_y_no_queda_como_pendiente() = runBlocking {
        sembrarMuestras()
        repo.seleccionar(elegible, MetodoSeleccion.MANUAL, TipoControl.RUTINARIO, null, null)
        val fila = cola.byOperationType(SyncOperationTypes.CALIDAD_SELECCIONAR_MUESTRA).single()

        // Respuesta 409 del servidor (CalidadSeleccionarMuestraSyncHandler → Rejected).
        cola.markNeedsReview(fila.id, "Esta entrega ya fue seleccionada para análisis.")

        val p = panel()
        assertTrue(p.pendientes.isEmpty())
        assertEquals("Esta entrega ya fue seleccionada para análisis.", p.conflictos.single().mensaje)

        repo.descartarConflicto(p.conflictos.single().colaId)
        assertTrue(panel().conflictos.isEmpty())
    }

    @Test
    fun migracion_v1_a_v2_crea_la_tabla_de_capturas_en_instalaciones_existentes() {
        driver.execute(null, "DROP TABLE calidad_captura", 0)

        EcoLactDatabase.Schema.migrate(driver, 1, EcoLactDatabase.Schema.version)

        capturas.guardarPendienteOcr(1, null, byteArrayOf(1), "image/jpeg", "2026-10-27T13:00:00Z")
        assertEquals(EstadoCaptura.PENDIENTE_OCR, capturas.obtener(1)!!.estado)
    }
}
