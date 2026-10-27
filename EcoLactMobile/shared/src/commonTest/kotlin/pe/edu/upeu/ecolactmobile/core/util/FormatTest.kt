package pe.edu.upeu.ecolactmobile.core.util

import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlin.test.Test
import kotlin.test.assertEquals

class FormatTest {
    @Test
    fun formatDecimal_redondea_y_rellena() {
        assertEquals("128.50", formatDecimal(128.5))
        assertEquals("0.05", formatDecimal(0.049))
        assertEquals("-3.20", formatDecimal(-3.2))
        assertEquals("0.00", formatDecimal(-0.001))
        assertEquals("1000", formatDecimal(999.6, 0))
    }

    @Test
    fun formatSoles_y_litros() {
        assertEquals("S/ 420.00", formatSoles(420.0))
        assertEquals("120 L", formatLitros(120.0))
        assertEquals("128.5 L", formatLitros(128.5))
    }

    @Test
    fun initials_usa_nombre_y_apellido() {
        assertEquals("JQ", initials("Juan Carlos", "Quispe Mamani", "Juan Carlos Quispe Mamani"))
        assertEquals("JQ", initials(null, null, "juan quispe"))
        assertEquals("JU", initials(null, null, "juan"))
        assertEquals("?", initials(null, null, " "))
    }

    @Test
    fun formatCachedAt_devuelve_original_si_no_es_iso() {
        assertEquals("no-es-fecha", formatCachedAt("no-es-fecha"))
        assertEquals(11, formatCachedAt("2026-10-26T14:14:00Z").length) // "dd/MM HH:mm"
    }

    private val lima = TimeZone.of("America/Lima")

    @Test
    fun fechas_del_backend_en_hora_local() {
        val atom = "2026-10-29T10:32:00-05:00"
        assertEquals("29 OCT", formatDiaMes(atom, lima))
        assertEquals("10:32", formatHora(atom, lima))
        assertEquals("29/10/2026", formatFecha(atom, lima))
        assertEquals("29/10 · 10:32", formatActualizado(atom, lima))
        // UTC convertido a Lima: cambia el día.
        assertEquals("28 OCT", formatDiaMes("2026-10-29T03:00:00Z", lima))
        // Solo fecha y valores no interpretables.
        assertEquals("20/10/2026", formatFecha("2026-10-20", lima))
        assertEquals("05 SET", formatDiaMes("2026-09-05", lima))
        assertEquals("—", formatHora("—", lima))
    }

    @Test
    fun formatRelativo_hoy_ayer_y_otros() {
        val hoy = LocalDate(2026, 10, 26)
        assertEquals("Hoy 15:40", formatRelativo("2026-10-26T15:40:00-05:00", lima, hoy))
        assertEquals("Ayer 09:05", formatRelativo("2026-10-25T09:05:00-05:00", lima, hoy))
        assertEquals("20/10 08:00", formatRelativo("2026-10-20T08:00:00-05:00", lima, hoy))
    }

    @Test
    fun semanas_iso() {
        assertEquals(LocalDate(2026, 10, 26), inicioDeSemana(LocalDate(2026, 11, 1))) // domingo → lunes anterior
        assertEquals(LocalDate(2026, 10, 26), inicioDeSemana(LocalDate(2026, 10, 26)))
        assertEquals(44, numeroSemanaIso(LocalDate(2026, 10, 26)))
        assertEquals(53, numeroSemanaIso(LocalDate(2021, 1, 1))) // pertenece a la última semana de 2020
        assertEquals(1, numeroSemanaIso(LocalDate(2024, 12, 30))) // ya es semana 1 de 2025
    }

    @Test
    fun primerNombre_toma_la_primera_palabra() {
        assertEquals("Milkon", primerNombre(" Milkon  Edgar "))
    }
}
