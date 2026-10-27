package pe.edu.upeu.ecolactmobile.core.util

import kotlinx.datetime.TimeZone
import kotlinx.datetime.number
import kotlinx.datetime.offsetAt
import kotlinx.datetime.toLocalDateTime
import kotlin.random.Random
import kotlin.time.Clock
import kotlin.time.ExperimentalTime
import kotlin.time.Instant

/** Id local para filas creadas offline, antes de que el servidor asigne su id real. */
@OptIn(ExperimentalTime::class)
fun generateLocalId(): String {
    val time = Clock.System.now().toEpochMilliseconds()
    val rand = Random.nextInt(0, Int.MAX_VALUE)
    return "local-$time-$rand"
}

@OptIn(ExperimentalTime::class)
fun nowIso(): String = Clock.System.now().toString()

/** Zona horaria de operación de EcoLact (Perú). */
val ZONA_OPERACION: TimeZone = TimeZone.of("America/Lima")

/**
 * Hora de un EVENTO capturado en el teléfono (p. ej. "Guardar entrega"), en ISO 8601 con el
 * desfase de la zona de operación y al segundo: "2026-10-27T09:35:12-05:00". Se genera UNA sola
 * vez al ocurrir el evento y se guarda en el payload; los reintentos la reutilizan tal cual.
 */
@OptIn(ExperimentalTime::class)
fun fechaHoraEventoIso(instante: Instant = Clock.System.now(), zona: TimeZone = ZONA_OPERACION): String {
    val l = instante.toLocalDateTime(zona)
    fun dd(n: Int) = n.toString().padStart(2, '0')
    return "${l.year}-${dd(l.month.number)}-${dd(l.day)}T${dd(l.hour)}:${dd(l.minute)}:${dd(l.second)}${zona.offsetAt(instante)}"
}
