package pe.edu.upeu.ecolactmobile.feature.acopiador

import app.cash.sqldelight.driver.jdbc.sqlite.JdbcSqliteDriver
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.HttpRequestData
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.content.OutgoingContent
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withTimeout
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationHandler
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncPushResult
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertIs
import kotlin.test.assertNull
import kotlin.test.assertTrue
import kotlin.time.ExperimentalTime
import kotlin.time.Instant
import pe.edu.upeu.ecolactmobile.core.util.fechaHoraEventoIso

/**
 * El acopiador en campo sin cobertura, contra la SQLite real de la app (JDBC en memoria) y un
 * servidor simulado (MockEngine). Los casos siguen la numeración del pedido (CASO 1…10).
 */
@OptIn(ExperimentalTime::class)
class AcopiadorOfflineTest {
    private val driver = JdbcSqliteDriver(JdbcSqliteDriver.IN_MEMORY).also { EcoLactDatabase.Schema.create(it) }
    private val database = EcoLactDatabase(driver)
    private val cache = CacheRepository(database)
    private val cola = SyncQueueRepository(database)
    private val peticiones = mutableListOf<HttpRequestData>()

    /** Entregas que "existen" en el servidor; la clave de idempotencia devuelve siempre la misma. */
    private val servidorEntregas = mutableListOf<AcopiadorEntrega>()
    private val porClave = mutableMapOf<String, Long>()
    private var rechazar: String? = null

    private val http = HttpClient(
        MockEngine { req ->
            peticiones += req
            val ruta = req.url.encodedPath.substringAfter("acopiador/")
            val (status, cuerpo) = when {
                req.method.value == "POST" && ruta == "entregas" -> registrar(req)
                req.method.value == "GET" && ruta == "entregas" ->
                    HttpStatusCode.OK to """{"data":{"filas":${ApiJson.encodeToString(servidorEntregas.toList())}}}"""
                else -> HttpStatusCode.InternalServerError to "{}"
            }
            respond(cuerpo, status, headersOf(HttpHeaders.ContentType, "application/json"))
        },
    ) {
        expectSuccess = false
        install(ContentNegotiation) { json(ApiJson) }
    }
    private val api = AcopiadorApi(http)
    private var online = false
    private val repo = AcopiadorRepository(api, cache, cola, isOnline = { online }, userId = 1)

    private val maria = proveedor(10, "María", "Quispe", "41234567", "qr-maria", zonaId = 1)
    private val juan = proveedor(11, "Juan", "Mamani", "42345678", "qr-juan", zonaId = 1)
    private val inactivo = proveedor(12, "Rosa", "Condori", "43456789", "qr-rosa", zonaId = 1, estado = "INACTIVO")
    private val deOtraZona = proveedor(13, "Pedro", "Huanca", "44567890", "qr-pedro", zonaId = 2)

    private fun proveedor(id: Long, nombres: String, apellidos: String, dni: String, qr: String, zonaId: Long, estado: String = "ACTIVO") =
        AcopiadorProveedorFila(id, dni, nombres, apellidos, "$nombres $apellidos", zona_id = zonaId, zona_nombre = "Collana $zonaId", estado = estado, qr_token = qr)

    private fun registrar(req: HttpRequestData): Pair<HttpStatusCode, String> {
        rechazar?.let { return HttpStatusCode.UnprocessableEntity to """{"message":"$it"}""" }
        val clave = req.headers["Idempotency-Key"]!!
        val body = ApiJson.decodeFromString<AcopiadorEntregaRequest>(cuerpoDe(req))
        val id = porClave.getOrPut(clave) {
            val nuevo = 100L + porClave.size + 1
            val p = listOf(maria, juan).first { it.qr_token == body.identificador || it.dni == body.identificador }
            servidorEntregas += AcopiadorEntrega(
                id = nuevo, proveedor_id = p.id, proveedor_nombre = p.nombre_completo, proveedor_dni = p.dni, zona_nombre = p.zona_nombre,
                fecha_hora = body.fecha_hora_evento!!, litros = body.litros, metodo_identificacion = body.metodo_identificacion,
                estado = "PENDIENTE_ANALISIS", control_calidad = "SIN_MUESTREO",
            )
            nuevo
        }
        return HttpStatusCode.Created to """{"data":${ApiJson.encodeToString(servidorEntregas.first { it.id == id })}}"""
    }

    private fun cuerpoDe(req: HttpRequestData): String = (req.body as? OutgoingContent.ByteArrayContent)?.bytes()?.decodeToString().orEmpty()

    /** Lo que dejó la última sincronización: la zona del acopiador y sus proveedores. */
    private fun sembrar() {
        cache.put("acopiador.1.dashboard", AcopiadorDashboard(acopiador = AcopiadorPerfil(nombres = "Juan", apellidos = "Pérez", zona_id = 1, zona_nombre = "Collana 1")))
        cache.put("acopiador.1.proveedores", AcopiadorProveedoresListResponse(listOf(maria, juan, inactivo, deOtraZona)))
    }

    private fun historial(r: AcopiadorRepository = repo): List<EntregaVista> =
        runBlocking { withTimeout(5_000) { r.observeEntregas().first { it != null }!!.value } }

    /** Lo que hace SyncManager.syncNow() con cada fila pendiente, sin depender de ConnectivityObserver. */
    private suspend fun reconectar(): List<SyncPushResult> {
        online = true
        val handlers: Map<String, SyncOperationHandler> = listOf(
            AcopiadorEntregaSyncHandler(api),
            AcopiadorAcopioParadaSyncHandler(api),
        ).associateBy { it.operationType }
        return cola.pending().map { fila ->
            cola.markSyncing(fila.id)
            val r = handlers.getValue(fila.operation_type).push(fila.id, fila.payload_json)
            when (r) {
                is SyncPushResult.Success -> cola.markSynced(fila.id, r.serverId)
                is SyncPushResult.Rejected -> cola.markNeedsReview(fila.id, r.message)
                is SyncPushResult.TransientFailure -> cola.markPending(fila.id)
            }
            r
        }
    }

    @AfterTest
    fun cerrar() {
        http.close()
        driver.close()
    }

    @Test
    fun caso1_online_qr_20_litros_queda_sincronizada() = runBlocking<Unit> {
        sembrar()
        online = true

        val id = assertIs<Identificacion.Identificado>(repo.identificar(MetodoIdentificacion.QR, "qr-maria"))
        val r = repo.registrarEntrega(id.proveedor, MetodoIdentificacion.QR, 20.0, null)

        assertIs<RegistrarEntregaResult.Confirmada>(r)
        val post = peticiones.single { it.method.value == "POST" }
        assertEquals(cola.all().single().id, post.headers["Idempotency-Key"])
        val fila = historial().single()
        assertEquals(EstadoRegistro.SINCRONIZADA, fila.registro)
        assertEquals("María Quispe", fila.proveedorNombre)
    }

    @Test
    fun caso2_y_3_offline_queda_pendiente_y_sigue_visible_al_reabrir_la_app() = runBlocking<Unit> {
        sembrar()

        val id = assertIs<Identificacion.Identificado>(repo.identificar(MetodoIdentificacion.QR, "qr-maria"))
        val r = repo.registrarEntrega(id.proveedor, MetodoIdentificacion.QR, 20.0, "Leche fría")

        assertIs<RegistrarEntregaResult.GuardadaOffline>(r)
        assertTrue(peticiones.isEmpty(), "sin conexión no se intenta la red")
        assertEquals(EstadoRegistro.PENDIENTE, historial().single().registro)

        // CASO 3: se cierra la app y se vuelve a abrir (repositorios nuevos sobre la misma base).
        val reabierto = AcopiadorRepository(api, CacheRepository(database), SyncQueueRepository(database), isOnline = { false }, userId = 1)
        val fila = historial(reabierto).single()
        assertEquals(EstadoRegistro.PENDIENTE, fila.registro)
        assertEquals(20.0, fila.litros)
        assertEquals("María Quispe", fila.proveedorNombre)
        assertEquals("Leche fría", fila.observacion)
    }

    @Test
    fun caso4_al_volver_internet_pasa_a_sincronizada_sin_duplicarse() = runBlocking<Unit> {
        sembrar()
        repo.registrarEntrega(maria, MetodoIdentificacion.QR, 20.0, null)

        // La respuesta se pierde y la cola reintenta con la MISMA clave: el servidor no duplica.
        reconectar()
        cola.markPending(cola.all().single().id)
        reconectar()

        assertEquals(1, servidorEntregas.size)
        assertEquals(1, peticiones.map { it.headers["Idempotency-Key"] }.toSet().size)
        assertEquals(EstadoRegistro.SINCRONIZADA, historial().single().registro)

        // La sincronización descarga la lista del servidor: la copia local se retira, queda UNA fila.
        repo.refreshEntregas()
        val filas = historial()
        assertEquals(1, filas.size)
        assertEquals(claveServidor(servidorEntregas.single().id), filas.single().clave)
        assertTrue(cola.byOperationType(SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA).isEmpty())
    }

    @Test
    fun caso5_qr_offline_cacheado_funciona_y_no_cacheado_da_mensaje_claro() = runBlocking<Unit> {
        sembrar()

        assertEquals(maria.id, assertIs<Identificacion.Identificado>(repo.identificar(MetodoIdentificacion.QR, "qr-maria")).proveedor.id)
        val noGuardado = assertIs<Identificacion.NoEncontrado>(repo.identificar(MetodoIdentificacion.QR, "qr-desconocido"))
        assertEquals("No se encontró este proveedor entre los datos guardados.", noGuardado.mensaje)
        assertIs<Identificacion.NoPermitido>(repo.identificar(MetodoIdentificacion.QR, "qr-rosa"))
    }

    @Test
    fun caso6_dni_offline_busca_en_la_copia_local() = runBlocking<Unit> {
        sembrar()

        assertEquals(juan.id, assertIs<Identificacion.Identificado>(repo.identificar(MetodoIdentificacion.DNI, "42345678")).proveedor.id)
        assertIs<Identificacion.NoEncontrado>(repo.identificar(MetodoIdentificacion.DNI, "99999999"))
        assertTrue(peticiones.isEmpty())
    }

    @Test
    fun caso7_fuera_de_zona_se_bloquea_offline_y_si_el_servidor_rechaza_queda_en_error_sin_borrarse() = runBlocking<Unit> {
        sembrar()
        assertIs<Identificacion.NoPermitido>(repo.identificar(MetodoIdentificacion.DNI, deOtraZona.dni))

        // Guardada offline y luego el servidor la rechaza (p. ej. el proveedor fue desactivado).
        repo.registrarEntrega(juan, MetodoIdentificacion.DNI, 18.0, null)
        rechazar = "El proveedor no está habilitado para registrar entregas."
        val r = reconectar().single()

        assertIs<SyncPushResult.Rejected>(r)
        val fila = historial().single()
        assertEquals(EstadoRegistro.ERROR, fila.registro)
        assertEquals("El proveedor no está habilitado para registrar entregas.", fila.errorSync)
        assertEquals(SyncStatus.NEEDS_REVIEW.name, cola.all().single().status)
    }

    @Test
    fun caso8_la_hora_del_acopio_se_captura_una_vez_y_no_cambia_al_sincronizar_ni_al_reintentar() = runBlocking<Unit> {
        sembrar()
        val evento = "2026-10-27T09:35:12-05:00"
        repo.registrarEntrega(maria, MetodoIdentificacion.QR, 20.0, null, fechaHoraEvento = evento)

        // Pendiente: el historial muestra la hora del acopio (no "ahora", aunque se abra horas después).
        val pendiente = historial().single()
        assertEquals(EstadoRegistro.PENDIENTE, pendiente.registro)
        assertEquals(evento, pendiente.fechaHora)

        // 11:00 vuelve internet; la primera respuesta se pierde y la cola reintenta.
        reconectar()
        cola.markPending(cola.all().single().id)
        reconectar()

        val posts = peticiones.filter { it.method.value == "POST" }
        assertEquals(2, posts.size)
        val enviados = posts.map { ApiJson.decodeFromString<AcopiadorEntregaRequest>(cuerpoDe(it)) }
        assertTrue(enviados.all { it.fecha_hora_evento == evento }, "cada reintento reenvía la MISMA hora")
        assertTrue("\"fecha_hora_evento\":\"$evento\"" in cuerpoDe(posts.first()))
        assertEquals(1, posts.map { it.headers["Idempotency-Key"] }.toSet().size)
        assertEquals(1, servidorEntregas.size)

        // Sincronizada y reconciliada con la fila del servidor: misma hora, una sola fila.
        repo.refreshEntregas()
        val sincronizada = historial().single()
        assertEquals(EstadoRegistro.SINCRONIZADA, sincronizada.registro)
        assertEquals(claveServidor(servidorEntregas.single().id), sincronizada.clave)
        assertEquals(Instant.parse(evento), Instant.parse(sincronizada.fechaHora))
    }

    @Test
    fun la_hora_del_evento_lleva_el_desfase_de_peru_y_no_se_corre_a_utc() {
        assertEquals("2026-10-27T09:35:12-05:00", fechaHoraEventoIso(Instant.parse("2026-10-27T14:35:12.987Z")))
        assertEquals("2026-10-27T09:35:00-05:00", fechaHoraEventoIso(Instant.parse("2026-10-27T14:35:00Z")))
    }

    @Test
    fun regla_10_minutos_cuenta_desde_el_acopio_y_no_desde_la_sincronizacion() = runBlocking<Unit> {
        sembrar()
        repo.registrarEntrega(juan, MetodoIdentificacion.DNI, 18.0, null, fechaHoraEvento = "2026-10-27T09:35:12-05:00")
        val localId = cola.all().single().id
        cola.markNeedsReview(localId, "El proveedor no está habilitado para registrar entregas.")

        // 11:01: ya no se pueden cambiar los litros (la ventana fue 09:35 → 09:45).
        val tarde = repo.corregirEntrega(localId, 25.0, null, ahora = Instant.parse("2026-10-27T16:01:00Z"))
        assertIs<AccionResult.Error>(tarde)
        assertEquals(18.0, decodificarEntregaLocal(cola.all().single().payload_json)!!.request.litros)

        // Corregir solo la observación sí se permite, y nunca toca la hora del acopio.
        assertIs<AccionResult.Hecha>(repo.corregirEntrega(localId, 18.0, "Revisado", ahora = Instant.parse("2026-10-27T16:01:00Z")))
        // Dentro de la ventana (09:40) sí se pueden corregir los litros.
        cola.markNeedsReview(localId, "otra vez")
        assertIs<AccionResult.Hecha>(repo.corregirEntrega(localId, 19.0, null, ahora = Instant.parse("2026-10-27T14:40:00Z")))
        val payload = decodificarEntregaLocal(cola.all().single().payload_json)!!
        assertEquals("2026-10-27T09:35:12-05:00", payload.request.fecha_hora_evento)
        assertEquals(19.0, payload.request.litros)
    }

    @Test
    fun las_filas_viejas_con_fecha_hora_conservan_su_hora_como_evento() {
        val vieja = """{"metodo_identificacion":"DNI","identificador":"41234567","litros":12.0,"fecha_hora":"2026-10-27T13:00:00Z"}"""
        assertEquals("2026-10-27T13:00:00Z", decodificarEntregaLocal(vieja)!!.request.fecha_hora_evento)
    }

    @Test
    fun caso9_y_10_el_detalle_refleja_el_muestreo_no_pendiente_de_analisis() {
        fun entrega(id: Long, control: String) = AcopiadorEntrega(
            id = id, proveedor_id = 10, fecha_hora = "2026-10-27T09:35:00-05:00", litros = 20.0,
            metodo_identificacion = "QR", estado = "PENDIENTE_ANALISIS", control_calidad = control,
        )
        val filas = combinarEntregas(
            listOf(entrega(1, "SIN_MUESTREO"), entrega(2, "SELECCIONADA"), entrega(3, "OBSERVADA")),
            emptyList(),
        ).associateBy { it.serverId }

        assertEquals("Sin muestreo", ControlCalidad.from(filas[1]!!.controlCalidad)!!.label)
        assertEquals("Seleccionada para análisis", ControlCalidad.from(filas[2]!!.controlCalidad)!!.label)
        assertEquals("Observada", ControlCalidad.from(filas[3]!!.controlCalidad)!!.label)
        // Una entrega todavía en el teléfono no inventa estado de calidad.
        assertNull(combinarEntregas(emptyList(), cola.all()).firstOrNull()?.controlCalidad)
    }

    @Test
    fun recorrido_offline_acopio_y_no_entrego_se_ven_al_instante_y_no_se_pisan() = runBlocking<Unit> {
        sembrar()
        val parada = { id: Long, p: AcopiadorProveedorFila, orden: Int ->
            ParadaRecorrido(id, p.id, p.nombre_completo, p.dni, orden, estado = "PENDIENTE")
        }
        cache.put(
            "acopiador.1.recorridos",
            listOf(RecorridoDetalle(7, ruta_nombre = "Ruta 1", zona_nombre = "Collana 1", estado = "PENDIENTE", fecha_operativa = "2026-10-27", paradas = listOf(parada(70, maria, 1), parada(71, juan, 2)))),
        )

        assertIs<AccionResult.GuardadaOffline>(repo.iniciarRecorrido(7))
        repo.registrarEntrega(maria, MetodoIdentificacion.QR, 20.0, null, parada = ParadaRef(7, 70, maria.id))
        assertIs<AccionResult.GuardadaOffline>(repo.marcarNoEntrego(7, 71, "No tenía leche"))
        // No se puede marcar "no entregó" sobre una parada que ya tiene acopio.
        assertIs<AccionResult.Error>(repo.marcarNoEntrego(7, 70, "Error"))

        val r = withTimeout(5_000) { repo.observeRecorrido(7).first { it?.value != null }!!.value!! }
        assertEquals("EN_PROGRESO", r.estado)
        assertEquals(EstadoParada.RECOLECTADO, r.paradas[0].estado)
        assertTrue(r.paradas[0].pendienteEnvio)
        assertEquals(EstadoParada.NO_ENTREGO, r.paradas[1].estado)
        assertEquals(2, r.visitados)
        assertEquals(20.0, r.litros)
        // La entrega de la parada también aparece en el historial único.
        assertTrue(historial().single().deRecorrido)
        // El acopio de la parada usa el id local como token de idempotencia del servidor.
        val fila = cola.byOperationType(SyncOperationTypes.ACOPIADOR_ACOPIO_PARADA).single()
        assertEquals(7L, decodificarEntregaLocal(fila.payload_json)!!.parada!!.recorrido_id)
    }

    @Test
    fun filas_encoladas_por_la_version_anterior_siguen_visibles_y_se_envian() = runBlocking<Unit> {
        cola.enqueue(SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA, ApiJson.encodeToString(AcopiadorEntregaRequest("DNI", "41234567", 12.0, "2026-10-27T13:00:00Z")))
        sembrar()

        assertEquals(EstadoRegistro.PENDIENTE, historial().single().registro)
        assertIs<SyncPushResult.Success>(reconectar().single())
    }
}
