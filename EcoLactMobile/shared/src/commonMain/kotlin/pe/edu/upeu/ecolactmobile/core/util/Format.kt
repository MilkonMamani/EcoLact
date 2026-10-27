package pe.edu.upeu.ecolactmobile.core.util

import kotlinx.datetime.DateTimeUnit
import kotlinx.datetime.LocalDate
import kotlinx.datetime.LocalDateTime
import kotlinx.datetime.LocalTime
import kotlinx.datetime.TimeZone
import kotlinx.datetime.isoDayNumber
import kotlinx.datetime.minus
import kotlinx.datetime.plus
import kotlinx.datetime.toLocalDateTime
import kotlin.math.abs
import kotlin.math.pow
import kotlin.math.roundToLong
import kotlin.time.Clock
import kotlin.time.ExperimentalTime
import kotlin.time.Instant

/** `String.format` no existe en commonMain: redondeo manual a [decimals] decimales (ej. 128.5 → "128.50"). */
fun formatDecimal(value: Double, decimals: Int = 2): String {
    val factor = 10.0.pow(decimals)
    val scaled = abs(value * factor).roundToLong()
    val entero = scaled / factor.toLong()
    val sign = if (value < 0 && scaled != 0L) "-" else ""
    if (decimals == 0) return "$sign$entero"
    val fraccion = (scaled % factor.toLong()).toString().padStart(decimals, '0')
    return "$sign$entero.$fraccion"
}

fun formatSoles(value: Double): String = "S/ ${formatDecimal(value, 2)}"

/** Litros sin decimales sobrantes: 120.0 → "120 L", 128.5 → "128.5 L". */
fun formatLitros(value: Double): String {
    val texto = formatDecimal(value, 1)
    return (if (texto.endsWith(".0")) texto.dropLast(2) else texto) + " L"
}

/**
 * Convierte la marca ISO-UTC que guarda la caché local (ver `nowIso`) a hora local corta
 * "26/10 09:14". Si no se puede interpretar, devuelve el texto original.
 */
@OptIn(ExperimentalTime::class)
fun formatCachedAt(iso: String): String = runCatching {
    // LocalDateTime.toString() = "2026-10-26T09:14:05.123"
    val local = Instant.parse(iso).toLocalDateTime(TimeZone.currentSystemDefault()).toString()
    "${local.substring(8, 10)}/${local.substring(5, 7)} ${local.substring(11, 16)}"
}.getOrDefault(iso)

/** "Juan Carlos Quispe Mamani" → "JQ" (primer nombre + primer apellido cuando se conoce). */
fun initials(nombres: String?, apellidos: String?, fallback: String): String {
    val n = nombres?.trim()?.firstOrNull()
    val a = apellidos?.trim()?.firstOrNull()
    if (n != null && a != null) return "$n$a".uppercase()
    val partes = fallback.trim().split(" ").filter { it.isNotBlank() }
    return when {
        partes.size >= 2 -> "${partes[0].first()}${partes[1].first()}".uppercase()
        partes.size == 1 -> partes[0].take(2).uppercase()
        else -> "?"
    }
}

/** Primer nombre para saludos cortos: "Milkon Edgar" → "Milkon". */
fun primerNombre(nombres: String): String = nombres.trim().split(" ").firstOrNull { it.isNotBlank() } ?: nombres

// ---- Fechas del backend ----
// El backend envía DATE_ATOM ("2026-10-29T10:32:00-05:00") o solo fecha ("2026-10-20"). Todo se
// muestra en la hora local del teléfono. Si un texto no se puede interpretar se devuelve tal cual,
// nunca se rompe la pantalla por una fecha.

private val MESES = listOf("ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SET", "OCT", "NOV", "DIC")

@OptIn(ExperimentalTime::class)
fun parseFechaLocal(texto: String, zona: TimeZone = TimeZone.currentSystemDefault()): LocalDateTime? {
    val t = texto.trim()
    return runCatching { Instant.parse(t).toLocalDateTime(zona) }.getOrNull()
        ?: runCatching { LocalDateTime.parse(t.replace(' ', 'T')) }.getOrNull()
        ?: runCatching { LocalDateTime(LocalDate.parse(t.take(10)), LocalTime(0, 0)) }.getOrNull()
}

/** LocalDateTime.toString() = "2026-10-29T10:32[:ss]": se trocea en vez de depender de getters que cambian entre versiones. */
private fun LocalDateTime.partes(): Triple<String, String, String> {
    val s = toString()
    return Triple(s.substring(0, 4), s.substring(5, 7), s.substring(8, 10))
}

/** "29 OCT" */
fun formatDiaMes(texto: String, zona: TimeZone = TimeZone.currentSystemDefault()): String {
    val f = parseFechaLocal(texto, zona) ?: return texto
    val (_, mes, dia) = f.partes()
    return "$dia ${MESES[mes.toInt() - 1]}"
}

/** "10:32" */
fun formatHora(texto: String, zona: TimeZone = TimeZone.currentSystemDefault()): String =
    parseFechaLocal(texto, zona)?.toString()?.substring(11, 16) ?: texto

/** "29/10/2026" */
fun formatFecha(texto: String, zona: TimeZone = TimeZone.currentSystemDefault()): String {
    val f = parseFechaLocal(texto, zona) ?: return texto
    val (anio, mes, dia) = f.partes()
    return "$dia/$mes/$anio"
}

/** Marca de última actualización, discreta: "26/10 · 18:20". */
fun formatActualizado(texto: String, zona: TimeZone = TimeZone.currentSystemDefault()): String {
    val f = parseFechaLocal(texto, zona) ?: return texto
    val (_, mes, dia) = f.partes()
    return "$dia/$mes · ${f.toString().substring(11, 16)}"
}

/** "Hoy 15:40", "Ayer 09:10" o "24/10 15:40". */
fun formatRelativo(texto: String, zona: TimeZone = TimeZone.currentSystemDefault(), hoy: LocalDate = hoy(zona)): String {
    val f = parseFechaLocal(texto, zona) ?: return texto
    val hora = f.toString().substring(11, 16)
    return when (f.date) {
        hoy -> "Hoy $hora"
        hoy.minus(1, DateTimeUnit.DAY) -> "Ayer $hora"
        else -> {
            val (_, mes, dia) = f.partes()
            "$dia/$mes $hora"
        }
    }
}

@OptIn(ExperimentalTime::class)
fun hoy(zona: TimeZone = TimeZone.currentSystemDefault()): LocalDate = Clock.System.now().toLocalDateTime(zona).date

/** Lunes de la semana de [fecha] (semanas de lunes a domingo, igual que las liquidaciones). */
fun inicioDeSemana(fecha: LocalDate): LocalDate = fecha.minus(fecha.dayOfWeek.isoDayNumber - 1, DateTimeUnit.DAY)

/** Número de semana ISO-8601 (la semana 1 es la que contiene el primer jueves del año). */
fun numeroSemanaIso(fecha: LocalDate): Int {
    val jueves = fecha.plus(4 - fecha.dayOfWeek.isoDayNumber, DateTimeUnit.DAY)
    return (jueves.dayOfYear - 1) / 7 + 1
}
