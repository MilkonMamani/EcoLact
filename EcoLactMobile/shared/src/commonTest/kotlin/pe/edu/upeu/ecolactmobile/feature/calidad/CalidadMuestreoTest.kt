package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.datetime.LocalDate
import kotlinx.datetime.LocalDateTime
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.FiltroResultado
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.PeriodoHistorial
import pe.edu.upeu.ecolactmobile.feature.calidad.screens.filtrarHistorial
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertTrue

class CalidadMuestreoTest {

    private fun muestra(entregaId: Long, estado: String = "SELECCIONADA", seleccion: String = "2026-10-27T08:30:00-05:00") = MuestraCalidad(
        id = entregaId * 10,
        entregaId = entregaId,
        proveedorNombre = "Proveedor $entregaId",
        litros = 40.0,
        fechaHora = "2026-10-27T08:00:00-05:00",
        litrosEditablesHasta = "2026-10-27T08:10:00-05:00",
        metodoSeleccion = "MANUAL",
        fechaSeleccion = seleccion,
        estado = estado,
    )

    private fun local(entregaId: Long) = SeleccionLocal(
        SeleccionarMuestraRequest(entrega_id = entregaId, metodo_seleccion = "MANUAL", fecha_seleccion = "2026-10-27T09:00:00Z"),
        EntregaElegible(entregaId = entregaId, proveedorNombre = "Local $entregaId", litros = 36.0, fechaHora = "2026-10-27T07:30:00-05:00"),
    )

    @Test
    fun caso1_pendientes_son_solo_las_muestras_seleccionadas_sin_analizar() {
        val servidor = (1L..5L).map { muestra(it) } + muestra(6, "ANALIZADA") + muestra(7, "EN_ANALISIS")

        val pendientes = combinarPendientes(servidor, emptyList(), emptySet(), emptyMap())

        assertEquals(6, pendientes.size)
        assertFalse(pendientes.any { it.entregaId == 6L })
    }

    @Test
    fun caso3_y_5_seleccion_offline_aparece_en_pendientes_marcada_como_local() {
        val pendientes = combinarPendientes(listOf(muestra(1)), listOf(local(2)), emptySet(), emptyMap())

        assertEquals(listOf(1L, 2L), pendientes.map { it.entregaId })
        assertTrue(pendientes.single { it.entregaId == 2L }.seleccionLocal)
    }

    @Test
    fun seleccion_local_ya_confirmada_por_el_servidor_no_se_duplica() {
        val pendientes = combinarPendientes(listOf(muestra(2)), listOf(local(2)), emptySet(), emptyMap())

        assertEquals(1, pendientes.size)
        assertFalse(pendientes.single().seleccionLocal)
    }

    @Test
    fun caso4_analisis_confirmado_en_el_telefono_sale_de_pendientes() {
        val pendientes = combinarPendientes(listOf(muestra(1), muestra(2)), emptyList(), setOf(1L), emptyMap())

        assertEquals(listOf(2L), pendientes.map { it.entregaId })
    }

    @Test
    fun caso7_entrega_con_30_minutos_muestra_litros_confirmados_sin_bloquear_nada() {
        val treintaMinutosDespues = LocalDateTime.parse("2026-10-27T08:30:00")
        val cincoMinutosDespues = LocalDateTime.parse("2026-10-27T08:05:00")
        val limite = "2026-10-27T08:10:00"

        assertTrue(litrosConfirmados(limite, treintaMinutosDespues))
        assertFalse(litrosConfirmados(limite, cincoMinutosDespues))
        // La muestra sigue pendiente y analizable: el tiempo no la saca de la lista.
        assertEquals(1, combinarPendientes(listOf(muestra(1)), emptyList(), emptySet(), emptyMap()).size)
    }

    @Test
    fun valores_no_detectados_quedan_vacios_nunca_en_cero() {
        val parametros = listOf(
            ParametroActivo(1, "GRASA", "Grasa", "%"),
            ParametroActivo(2, "PH", "pH", null),
        )
        val ocr = TicketOcrResponse(success = true, valores = listOf(TicketValorDetectado(1, "GRASA", "Grasa", 4.2)))

        val valores = valoresDesdeOcr(ocr, parametros)

        assertEquals(4.2, valores["GRASA"])
        assertTrue(valores.containsKey("PH"))
        assertNull(valores["PH"])
        assertNull(parseValor(""))
        assertNull(parseValor("abc"))
        assertEquals(1.028, parseValor("1,028"))
    }

    @Test
    fun resultado_estimado_sigue_el_criterio_del_servidor() {
        val reglas = listOf(
            ReglaVigente("GRASA", 3.0, 6.0, "OBSERVADA"),
            ReglaVigente("PH", 6.5, 6.8, "RECHAZADA"),
        )

        assertEquals(ResultadoCalidad.ACEPTADA, estimarResultado(mapOf("GRASA" to 4.2, "PH" to 6.6), reglas)?.resultado)
        assertEquals(ResultadoCalidad.OBSERVADA, estimarResultado(mapOf("GRASA" to 2.0, "PH" to 6.6), reglas)?.resultado)
        assertEquals(ResultadoCalidad.RECHAZADA, estimarResultado(mapOf("GRASA" to 2.0, "PH" to 7.2), reglas)?.resultado)
        // Un valor no medido (null) no cuenta como 0 ni incumple nada.
        assertEquals(ResultadoCalidad.ACEPTADA, estimarResultado(mapOf("GRASA" to 4.2, "PH" to null), reglas)?.resultado)
        assertNull(estimarResultado(mapOf("GRASA" to null), reglas))
        assertEquals(listOf("SNG"), estimarResultado(mapOf("SNG" to 8.6), reglas)?.sinRegla)
    }

    @Test
    fun aviso_de_sincronizacion_temporal_segun_lo_pendiente() {
        assertEquals("Enviando análisis…", mensajeEnvio(listOf(SyncOperationTypes.CALIDAD_SUBIR_TICKET, SyncOperationTypes.CALIDAD_REGISTRAR_ANALISIS)))
        assertEquals("Procesando OCR…", mensajeEnvio(listOf(SyncOperationTypes.CALIDAD_SUBIR_TICKET)))
        assertNull(mensajeEnvio(listOf(SyncOperationTypes.NOTIFICACION_MARCAR_LEIDA)))
    }

    @Test
    fun parametros_en_el_orden_del_ticket() {
        val orden = ordenarPorParametro(listOf("PH", "OTRO", "GRASA", "DENSIDAD", "SNG")) { it }
        assertEquals(listOf("GRASA", "SNG", "DENSIDAD", "PH", "OTRO"), orden)
    }

    @Test
    fun historial_filtra_esta_semana_y_resultado() {
        val filas = listOf(
            HistorialFila(1, "27/10/2026 10:15", "Juan", entregaId = 1, resultado = "ACEPTADA", estado = "CONFIRMADO", fechaIso = "2026-10-27T10:15:00"),
            HistorialFila(2, "27/10/2026 09:40", "María", entregaId = 2, resultado = "OBSERVADA", estado = "CONFIRMADO", fechaIso = "2026-10-27T09:40:00"),
            HistorialFila(3, "10/10/2026 08:20", "Pedro", entregaId = 3, resultado = "RECHAZADA", estado = "CONFIRMADO", fechaIso = "2026-10-10T08:20:00"),
        )
        val hoy = LocalDate.parse("2026-10-28")

        assertEquals(listOf(1L, 2L), filtrarHistorial(filas, PeriodoHistorial.SEMANA, FiltroResultado.TODOS, hoy).map { it.id })
        assertEquals(listOf(2L), filtrarHistorial(filas, PeriodoHistorial.SEMANA, FiltroResultado.OBSERVADAS, hoy).map { it.id })
        assertEquals(3, filtrarHistorial(filas, PeriodoHistorial.TODO, FiltroResultado.TODOS, hoy).size)
    }
}
