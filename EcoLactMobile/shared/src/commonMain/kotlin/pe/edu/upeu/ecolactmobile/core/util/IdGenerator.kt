package pe.edu.upeu.ecolactmobile.core.util

import kotlin.random.Random
import kotlin.time.Clock
import kotlin.time.ExperimentalTime

/** Id local para filas creadas offline, antes de que el servidor asigne su id real. */
@OptIn(ExperimentalTime::class)
fun generateLocalId(): String {
    val time = Clock.System.now().toEpochMilliseconds()
    val rand = Random.nextInt(0, Int.MAX_VALUE)
    return "local-$time-$rand"
}

@OptIn(ExperimentalTime::class)
fun nowIso(): String = Clock.System.now().toString()
