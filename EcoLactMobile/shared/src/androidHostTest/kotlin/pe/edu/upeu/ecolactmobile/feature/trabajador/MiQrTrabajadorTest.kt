package pe.edu.upeu.ecolactmobile.feature.trabajador

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.http.HttpStatusCode
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadApi
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadCapturaStore
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNull

/** Mi QR del trabajador: se dibuja desde SQLite sin conexión y nunca muestra el QR de otro usuario. */
class MiQrTrabajadorTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val database = EcoLactDatabase(driver)
    private val cache = CacheRepository(database)
    private var llamadas = 0
    private val http = HttpClient(MockEngine { llamadas++; respond("{}", HttpStatusCode.InternalServerError) })

    private fun calidad(userId: Long) =
        CalidadRepository(CalidadApi(http), cache, SyncQueueRepository(database), CalidadCapturaStore(database), isOnline = { false }, database = database, userId = userId)

    @AfterTest
    fun cerrar() {
        http.close()
        driver.close()
    }

    @Test
    fun el_contrato_trae_la_identidad_unica_del_trabajador() {
        val a = ApiJson.decodeFromString(
            MiAsistencia.serializer(),
            """{"registrado":true,"trabajador_id":3,"user_id":9,"nombres":"María","apellidos":"Torres","dni":"41234567","qr_token":"qr-9","controla_asistencia":true,"jornadas":[]}""",
        )
        assertEquals(3L, a.trabajador_id)
        assertEquals("María Torres", a.nombreCompleto)
        assertEquals("qr-9", a.qr_token)
    }

    @Test
    fun sin_internet_el_qr_sale_de_sqlite_y_es_solo_del_usuario_que_lo_descargo() = runBlocking {
        cache.put("calidad.9.asistencia", MiAsistencia(registrado = true, nombres = "María", dni = "41234567", qr_token = "qr-9"))

        assertEquals("qr-9", calidad(9).observeAsistencia().first()?.value?.qr_token)
        assertNull(calidad(10).observeAsistencia().first(), "otro usuario en el mismo teléfono no ve ese QR")
        assertEquals(0, llamadas, "abrir Mi QR no llama al servidor")
    }
}
