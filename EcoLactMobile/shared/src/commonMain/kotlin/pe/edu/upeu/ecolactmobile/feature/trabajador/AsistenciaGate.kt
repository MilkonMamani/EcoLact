package pe.edu.upeu.ecolactmobile.feature.trabajador

import io.ktor.client.HttpClient
import io.ktor.client.request.get
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import kotlinx.datetime.toLocalDateTime
import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.cache.CacheRepository
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.util.ZONA_OPERACION
import kotlin.time.Clock
import kotlin.time.Duration
import kotlin.time.Duration.Companion.minutes
import kotlin.time.Duration.Companion.seconds
import kotlin.time.ExperimentalTime

// Regla global de operación en el teléfono (misma para Acopiador, Calidad y Planta): sin asistencia
// válida HOY se puede consultar todo, pero no operar. El servidor es la fuente de verdad y vuelve a
// validar cada operación (también al sincronizar, con la hora real del evento); esto solo evita que
// el teléfono deje crear operaciones que el servidor va a rechazar, sobre todo sin conexión.

/** GET me/asistencia. */
@Serializable
data class EstadoAsistenciaHoy(
    val fecha: String,
    val requiere_asistencia: Boolean = true,
    val puede_operar: Boolean = false,
    val estado: String = "SIN_ASISTENCIA",
    val hora_marcacion: String? = null,
    val hora_cierre: String = "07:00",
)

class AsistenciaApi(private val httpClient: HttpClient) {
    suspend fun estado(): EstadoAsistenciaHoy = httpClient.get("me/asistencia").toEnvelopeData()
}

/** Lo que la app permite ahora. Nada que ver con el punto de conexión (verde/naranja). */
enum class Autorizacion {
    /** Asistencia confirmada por el servidor para HOY (o el rol no marca asistencia). */
    HABILITADA,

    /** Con conexión, el servidor todavía no registra asistencia válida hoy. */
    PENDIENTE,

    /** Sin conexión y sin una confirmación del servidor con la fecha de hoy. */
    NO_VERIFICADA,
    ;

    val puedeOperar: Boolean get() = this == HABILITADA
}

/**
 * Regla local, pura: solo vale una confirmación DEL SERVIDOR con la fecha EXACTA de hoy (zona del
 * negocio). La de ayer no autoriza hoy; a medianoche deja de valer sola.
 */
fun autorizacion(guardado: EstadoAsistenciaHoy?, enLinea: Boolean, hoy: String): Autorizacion = when {
    guardado != null && !guardado.requiere_asistencia -> Autorizacion.HABILITADA
    guardado != null && guardado.fecha == hoy && guardado.puede_operar -> Autorizacion.HABILITADA
    enLinea -> Autorizacion.PENDIENTE
    else -> Autorizacion.NO_VERIFICADA
}

/** "2026-10-27" en la zona horaria del negocio (Perú), igual que las jornadas del servidor. */
@OptIn(ExperimentalTime::class)
fun hoyNegocio(): String = Clock.System.now().toLocalDateTime(ZONA_OPERACION).date.toString()

/**
 * Estado de asistencia del usuario de la sesión, compartido por la shell de cada rol. Guarda en
 * SQLite (por usuario) solo lo que confirmó el servidor, con su fecha; con conexión lo vuelve a
 * consultar solo (cada minuto mientras falte la asistencia, para habilitar al trabajador apenas el
 * administrador escanea su QR, sin cerrar sesión).
 */
class AsistenciaGate(
    private val api: AsistenciaApi,
    private val cache: CacheRepository,
    userId: Long,
    private val isOnline: StateFlow<Boolean>,
    private val hoy: () -> String = ::hoyNegocio,
) : ScreenModel() {
    private val key = "asistencia.$userId"
    private val dia = MutableStateFlow(hoy())

    val estado: StateFlow<Autorizacion> =
        combine(cache.observe<EstadoAsistenciaHoy>(key), isOnline, dia) { guardado, online, d -> autorizacion(guardado?.value, online, d) }
            .stateIn(scope, SharingStarted.Eagerly, autorizacionAhora())

    init {
        // Cambio de día: la autorización de ayer deja de valer sola.
        scope.launch {
            while (true) {
                delay(30.seconds)
                dia.value = hoy()
            }
        }
        scope.launch {
            isOnline.collectLatest { online ->
                if (!online) return@collectLatest
                while (true) {
                    runCatching { refresh() }
                    delay(if (autorizacionAhora().puedeOperar) INTERVALO_HABILITADO else INTERVALO_PENDIENTE)
                }
            }
        }
    }

    suspend fun refresh() = cache.put(key, api.estado())

    fun guardado(): CachedValue<EstadoAsistenciaHoy>? = runCatching { cache.get<EstadoAsistenciaHoy>(key) }.getOrNull()

    /** Lectura inmediata (sin esperar flujos): la usan los repositorios antes de encolar una operación. */
    fun autorizacionAhora(): Autorizacion = autorizacion(guardado()?.value, isOnline.value, hoy())

    /** Para operar SIN conexión hace falta la confirmación del servidor con la fecha de hoy. */
    fun puedeOperarSinConexion(): Boolean = autorizacion(guardado()?.value, enLinea = false, hoy = hoy()).puedeOperar

    private companion object {
        val INTERVALO_PENDIENTE: Duration = 1.minutes
        val INTERVALO_HABILITADO: Duration = 10.minutes
    }
}

/** Mensaje al intentar operar sin conexión y sin asistencia confirmada para hoy. */
const val MENSAJE_ASISTENCIA_NO_VERIFICADA =
    "Asistencia no verificada para hoy. Conéctate a Internet después de registrar tu asistencia para habilitar las operaciones."

/**
 * Hora REAL de una operación hecha en el teléfono, en el cuerpo de las peticiones que no la
 * llevaban: el servidor evalúa la asistencia de ese día (no la del momento de la sincronización).
 */
@Serializable
data class EventoRequest(val fecha_hora_evento: String? = null)
