package pe.edu.upeu.ecolactmobile.feature.proveedor

import kotlinx.datetime.LocalDate
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import kotlin.test.Test
import kotlin.test.assertEquals

class ProveedorPresentacionTest {
    // Mediodía en Lima: el día no cambia aunque el test corra en otra zona horaria cercana.
    private fun entrega(id: Long, fecha: String, litros: Double, estado: String = "ANALIZADA", resultado: String? = "ACEPTADA") =
        EntregaResumen(id = id, fecha_hora = "${fecha}T12:00:00-05:00", litros = litros, estado = estado, resultado = resultado)

    private val hoy = LocalDate(2026, 10, 28) // miércoles; semana del lunes 26/10

    private val filas = listOf(
        entrega(1, "2026-10-26", 45.0),
        entrega(2, "2026-10-27", 52.0, resultado = "OBSERVADA"),
        entrega(3, "2026-10-28", 30.0, resultado = "RECHAZADA"),
        entrega(4, "2026-10-28", 20.0, estado = "ANULADA", resultado = null),
        entrega(5, "2026-10-28", 48.0, estado = "PENDIENTE_ANALISIS", resultado = null),
        entrega(6, "2026-10-21", 60.0), // semana anterior
    )

    @Test
    fun estadoEntrega_combina_estado_y_resultado() {
        assertEquals(EstadoEntregaVisual.ACEPTADA, estadoEntrega("ANALIZADA", "ACEPTADA"))
        assertEquals(EstadoEntregaVisual.OBSERVADA, estadoEntrega("ANALIZADA", "OBSERVADA"))
        assertEquals(EstadoEntregaVisual.RECHAZADA, estadoEntrega("ANALIZADA", "RECHAZADA"))
        assertEquals(EstadoEntregaVisual.PENDIENTE, estadoEntrega("PENDIENTE_ANALISIS", null))
        assertEquals(EstadoEntregaVisual.ANULADA, estadoEntrega("ANULADA", "ACEPTADA"))
    }

    @Test
    fun resumenSemana_excluye_anuladas_y_litros_rechazados() {
        // 45 + 52 + 48 (la rechazada cuenta como entrega pero no suma litros; la anulada no cuenta).
        assertEquals(ResumenSemana(litros = 145.0, entregas = 4), resumenSemana(filas, hoy))
    }

    @Test
    fun filtrarEntregas_por_semana_y_estado() {
        assertEquals(listOf(6L), filtrarEntregas(filas, FiltroSemana.ANTERIOR, null, hoy).map { it.id })
        assertEquals(listOf(1L, 2L, 3L, 4L, 5L), filtrarEntregas(filas, FiltroSemana.ESTA, null, hoy).map { it.id })
        assertEquals(listOf(2L), filtrarEntregas(filas, FiltroSemana.TODAS, EstadoEntregaVisual.OBSERVADA, hoy).map { it.id })
        assertEquals(listOf(5L), filtrarEntregas(filas, FiltroSemana.ESTA, EstadoEntregaVisual.PENDIENTE, hoy).map { it.id })
    }

    @Test
    fun estados_de_pago_usan_los_valores_reales_del_backend() {
        assertEquals("Pagado" to Tono.EXITO, estadoPagoLabel("PAGADA"))
        assertEquals("Pendiente" to Tono.ATENCION, estadoPagoLabel("PENDIENTE"))
        assertEquals("Pago parcial" to Tono.ATENCION, estadoPagoLabel("PARCIALMENTE_PAGADA"))
    }

    private fun liquidacion(fechaInicio: String? = null, fechaCalculo: String? = null, incidencia: Boolean = false) = LiquidacionResumen(
        id = 1, periodo_id = 1, total_litros_entregados = 145.0, total_litros_pagables = 145.0, precio_litro_aplicado = 1.8,
        subtotal = 261.0, total_ajustes = -32.0, total_pagar = 229.0, estado = "PENDIENTE",
        fecha_calculo = fechaCalculo, tuvo_incidencia = incidencia, fecha_inicio = fechaInicio,
    )

    @Test
    fun semanaLabel_usa_inicio_del_periodo_o_fecha_de_calculo() {
        assertEquals("Semana 43", semanaLabel(liquidacion(fechaInicio = "2026-10-19")))
        assertEquals("Semana 44", semanaLabel(liquidacion(fechaCalculo = "2026-10-27T09:00:00-05:00")))
        assertEquals("Liquidación", semanaLabel(liquidacion()))
    }

    @Test
    fun descuentos_separan_compras_de_ajustes_de_calidad() {
        val detalle = LiquidacionDetalleResponse(
            liquidacion = liquidacion(incidencia = true),
            ajustes = listOf(
                AjusteLiquidacion(1, AJUSTE_DESCUENTO_PRODUCTO, monto = -32.0),
                AjusteLiquidacion(2, "SANCION", monto = -10.0),
            ),
        )
        assertEquals(32.0, comprasDescontadas(detalle))
        assertEquals(-10.0, otrosAjustes(detalle))
        assertEquals(EstadoEntregaVisual.OBSERVADA, resultadoSemanal(detalle.liquidacion))
    }

    /** Respuesta real de ObtenerDetalleLiquidacion: las entregas NO traen id ni fecha_hora. */
    @Test
    fun detalleLiquidacion_decodifica_entregas_del_backend() {
        val json = """
            {"liquidacion":{"id":7,"periodo_id":3,"total_litros_entregados":145,"total_litros_pagables":145,
             "precio_litro_aplicado":1.8,"subtotal":261,"total_ajustes":-32,"total_pagar":229,"estado":"PAGADA",
             "fecha_calculo":"2026-10-27T09:00:00-05:00","tuvo_incidencia":false},
             "entregas":[{"fecha":"26/10/2026","litros":45,"resultado":"ACEPTADA","pagables":45}],
             "incidencia":null,"compras":[],"ajustes":[],"pagos":[],"saldo_pendiente":0}
        """.trimIndent()
        val detalle = ApiJson.decodeFromString<LiquidacionDetalleResponse>(json)
        assertEquals(45.0, detalle.entregas.single().litros)
        assertEquals("PAGADA", detalle.liquidacion.estado)
    }
}
