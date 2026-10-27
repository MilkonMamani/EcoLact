package pe.edu.upeu.ecolactmobile.feature.acopiador

import kotlinx.datetime.DateTimeUnit
import kotlinx.datetime.LocalDate
import kotlinx.datetime.TimeZone
import kotlinx.datetime.minus
import pe.edu.upeu.ecolactmobile.core.database.Sync_queue
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.sync.SyncOperationTypes
import pe.edu.upeu.ecolactmobile.core.sync.SyncStatus
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.core.util.inicioDeSemana
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import kotlin.time.ExperimentalTime
import kotlin.time.Instant

/** Estado de registro visible para el acopiador. Nada de términos técnicos de la cola. */
enum class EstadoRegistro { SINCRONIZADA, PENDIENTE, ERROR }

/**
 * Una entrega del historial, venga del servidor o del teléfono. Nunca hay dos filas para la misma
 * entrega: una vez que el servidor la confirma, la copia local se oculta (ver [combinarEntregas]).
 *
 * [clave]: "s-<id servidor>" o "l-<id local>", estable para navegación y listas.
 */
data class EntregaVista(
    val clave: String,
    val serverId: Long?,
    val localId: String?,
    val proveedorId: Long?,
    val proveedorNombre: String,
    val proveedorDni: String?,
    val zonaNombre: String?,
    val fechaHora: String,
    val litros: Double,
    val observacion: String?,
    val registro: EstadoRegistro,
    val errorSync: String?,
    /** Solo para entregas que ya están en el servidor (ver ControlCalidad). */
    val controlCalidad: String?,
    val deRecorrido: Boolean,
)

fun claveServidor(id: Long) = "s-$id"
fun claveLocal(localId: String) = "l-$localId"

/** Operaciones de la cola que crean una entrega (registro directo o acopio en una parada). */
val OPERACIONES_ENTREGA = setOf(SyncOperationTypes.ACOPIADOR_REGISTRAR_ENTREGA, SyncOperationTypes.ACOPIADOR_ACOPIO_PARADA)

/**
 * Lee el payload de una entrega encolada. Las filas creadas por versiones anteriores de la app
 * guardaban solo la petición, sin datos del proveedor: se siguen pudiendo leer y enviar.
 */
fun decodificarEntregaLocal(payloadJson: String): EntregaLocalPayload? =
    runCatching { ApiJson.decodeFromString<EntregaLocalPayload>(payloadJson) }.getOrNull()
        ?: runCatching { EntregaLocalPayload(ApiJson.decodeFromString<AcopiadorEntregaRequest>(payloadJson)) }.getOrNull()

/**
 * Historial único = servidor + teléfono:
 * - PENDING/SYNCING → "Pendiente"; NEEDS_REVIEW → "Error" (sigue guardada para revisión);
 * - SYNCED cuyo id de servidor ya está en la lista del servidor → se oculta (la fila del
 *   servidor la reemplaza, sin duplicado);
 * - SYNCED que el servidor todavía no listó (recién enviada) → "Sincronizada" con los datos locales.
 * Orden: hora real del acopio, más reciente primero.
 */
fun combinarEntregas(servidor: List<AcopiadorEntrega>, cola: List<Sync_queue>): List<EntregaVista> {
    val idsServidor = servidor.map { it.id }.toSet()
    val remotas = servidor.map { e ->
        EntregaVista(
            clave = claveServidor(e.id),
            serverId = e.id,
            localId = null,
            proveedorId = e.proveedor_id,
            proveedorNombre = e.proveedor_nombre ?: "Proveedor",
            proveedorDni = e.proveedor_dni,
            zonaNombre = e.zona_nombre,
            fechaHora = e.fecha_hora,
            litros = e.litros,
            observacion = e.observacion,
            registro = EstadoRegistro.SINCRONIZADA,
            errorSync = null,
            controlCalidad = e.control_calidad ?: e.resultado ?: ControlCalidad.SIN_MUESTREO.api,
            deRecorrido = false,
        )
    }
    val locales = cola
        .filter { it.operation_type in OPERACIONES_ENTREGA }
        .filterNot { it.status == SyncStatus.SYNCED.name && it.server_id != null && it.server_id in idsServidor }
        .mapNotNull { fila ->
            val payload = decodificarEntregaLocal(fila.payload_json) ?: return@mapNotNull null
            val registro = when (fila.status) {
                SyncStatus.SYNCED.name -> EstadoRegistro.SINCRONIZADA
                SyncStatus.NEEDS_REVIEW.name -> EstadoRegistro.ERROR
                else -> EstadoRegistro.PENDIENTE
            }
            EntregaVista(
                clave = claveLocal(fila.id),
                serverId = fila.server_id,
                localId = fila.id,
                proveedorId = payload.proveedor_id,
                proveedorNombre = payload.proveedor_nombre ?: "DNI ${payload.request.identificador}".takeIf { payload.request.metodo_identificacion == "DNI" } ?: "Proveedor",
                proveedorDni = payload.proveedor_dni,
                zonaNombre = payload.zona_nombre,
                // Siempre la hora del acopio; created_at (inserción en la cola) solo para filas sin ella.
                fechaHora = payload.request.fecha_hora_evento ?: fila.created_at,
                litros = payload.request.litros,
                observacion = payload.request.observacion,
                registro = registro,
                errorSync = fila.error_message.takeIf { registro == EstadoRegistro.ERROR },
                controlCalidad = null,
                deRecorrido = payload.parada != null,
            )
        }
    return (remotas + locales).sortedByDescending { instante(it.fechaHora) }
}

@OptIn(ExperimentalTime::class)
private fun instante(texto: String): Long =
    runCatching { Instant.parse(texto).toEpochMilliseconds() }.getOrNull()
        ?: parseFechaLocal(texto)?.let { runCatching { Instant.parse(it.toString() + "Z").toEpochMilliseconds() }.getOrNull() }
        ?: 0L

// ---- Control de calidad (muestreo) ----

/** Cómo ve el acopiador el control de calidad de una entrega: no todas se analizan. */
enum class ControlCalidad(val api: String, val label: String, val detalle: String) {
    SIN_MUESTREO("SIN_MUESTREO", "Sin muestreo", "Esta entrega no fue seleccionada para análisis."),
    SELECCIONADA("SELECCIONADA", "Seleccionada para análisis", "Calidad tomará una muestra de esta entrega."),
    EN_ANALISIS("EN_ANALISIS", "En análisis", "El laboratorio está analizando la muestra."),
    ACEPTADA("ACEPTADA", "Aceptada", "La muestra cumplió los parámetros de calidad."),
    OBSERVADA("OBSERVADA", "Observada", "La muestra tuvo observaciones de calidad."),
    RECHAZADA("RECHAZADA", "Rechazada", "La muestra no cumplió los parámetros de calidad."),
    ANULADA("ANULADA", "Anulada", "La entrega fue anulada."),
    ;

    companion object {
        fun from(valor: String?): ControlCalidad? = entries.firstOrNull { it.api == valor }
    }
}

// ---- Filtros del historial ----

enum class FiltroPeriodo(val label: String) { TODAS("Todas"), HOY("Hoy"), SEMANA("Esta semana"), MES("Este mes") }

fun filtrarEntregas(
    entregas: List<EntregaVista>,
    periodo: FiltroPeriodo,
    texto: String,
    hoy: LocalDate,
    zona: TimeZone = TimeZone.currentSystemDefault(),
): List<EntregaVista> {
    val desde: LocalDate? = when (periodo) {
        FiltroPeriodo.TODAS -> null
        FiltroPeriodo.HOY -> hoy
        FiltroPeriodo.SEMANA -> inicioDeSemana(hoy)
        FiltroPeriodo.MES -> hoy.minus(hoy.day - 1, DateTimeUnit.DAY)
    }
    val q = texto.trim().lowercase()
    return entregas.filter { e ->
        val fecha = parseFechaLocal(e.fechaHora, zona)?.date
        val enPeriodo = desde == null || (fecha != null && fecha >= desde && fecha <= hoy)
        val coincide = q.isEmpty() || e.proveedorNombre.lowercase().contains(q) || e.proveedorDni.orEmpty().contains(q) ||
            formatFecha(e.fechaHora, zona).contains(q)
        enPeriodo && coincide
    }
}

fun entregasDelDia(entregas: List<EntregaVista>, dia: LocalDate, zona: TimeZone = TimeZone.currentSystemDefault()): List<EntregaVista> =
    entregas.filter { parseFechaLocal(it.fechaHora, zona)?.date == dia }

// ---- Identificación del proveedor (QR / DNI) contra la copia local ----

sealed class Identificacion {
    data class Identificado(val proveedor: AcopiadorProveedorFila) : Identificacion()
    data class NoPermitido(val mensaje: String) : Identificacion()
    data class NoEncontrado(val mensaje: String) : Identificacion()
}

const val MENSAJE_NO_GUARDADO = "No se encontró este proveedor entre los datos guardados."

/**
 * Busca al proveedor en la lista de la zona guardada en el teléfono (la misma que da el servidor,
 * ya acotada a la zona del acopiador). Solo se permite si existe, está activo y su zona coincide
 * con la del acopiador cuando ambas se conocen. Nunca se acepta un DNI/QR sin validar.
 */
fun identificarLocal(
    proveedores: List<AcopiadorProveedorFila>,
    metodo: MetodoIdentificacion,
    identificador: String,
    zonaAcopiadorId: Long?,
): Identificacion {
    val valor = identificador.trim()
    val proveedor = when (metodo) {
        MetodoIdentificacion.QR -> proveedores.firstOrNull { it.qr_token != null && it.qr_token == valor }
        MetodoIdentificacion.DNI -> proveedores.firstOrNull { it.dni == valor }
    } ?: return Identificacion.NoEncontrado(MENSAJE_NO_GUARDADO)

    if (!proveedor.activo) return Identificacion.NoPermitido("${proveedor.nombre_completo} no está habilitado para registrar entregas.")
    if (zonaAcopiadorId != null && proveedor.zona_id != null && proveedor.zona_id != zonaAcopiadorId) {
        return Identificacion.NoPermitido("${proveedor.nombre_completo} pertenece a otra zona.")
    }
    return Identificacion.Identificado(proveedor)
}

enum class MetodoIdentificacion { QR, DNI }

fun dniValido(dni: String): Boolean = dni.length == 8 && dni.all { it.isDigit() }

// ---- Recorridos: estado de cada parada combinando servidor + acciones guardadas en el teléfono ----

enum class EstadoParada(val label: String) { PENDIENTE("Pendiente"), RECOLECTADO("Registrado"), NO_ENTREGO("No entregó") }

data class ParadaVista(
    val parada: ParadaRecorrido,
    val estado: EstadoParada,
    val litros: Double,
    /** Registrada en el teléfono y todavía sin confirmar por el servidor. */
    val pendienteEnvio: Boolean,
    val errorSync: String?,
    val motivo: String?,
)

data class RecorridoVista(
    val detalle: RecorridoDetalle,
    /** PENDIENTE / EN_PROGRESO / FINALIZADO / CANCELADO, incluido "iniciado" sin conexión. */
    val estado: String,
    val paradas: List<ParadaVista>,
) {
    val abierto: Boolean get() = estado == "PENDIENTE" || estado == "EN_PROGRESO"
    val visitados: Int get() = paradas.count { it.estado != EstadoParada.PENDIENTE }
    val litros: Double get() = paradas.filter { it.estado == EstadoParada.RECOLECTADO }.sumOf { it.litros }
    val pendientesEnvio: Int get() = paradas.count { it.pendienteEnvio }
}

private val ACTIVAS = setOf(SyncStatus.PENDING.name, SyncStatus.SYNCING.name, SyncStatus.SYNCED.name)

/** Por parada, la acción vigente (si se reintentó tras un error, la nueva gana a la rechazada). */
private fun <T> List<Pair<Long, Pair<Sync_queue, T>>>.preferirActivas(): Map<Long, Pair<Sync_queue, T>> =
    groupBy({ it.first }, { it.second }).mapValues { (_, filas) -> filas.firstOrNull { it.first.status in ACTIVAS } ?: filas.first() }

fun combinarRecorrido(detalle: RecorridoDetalle, cola: List<Sync_queue>): RecorridoVista {
    val acopios = cola.filter { it.operation_type == SyncOperationTypes.ACOPIADOR_ACOPIO_PARADA }
        .mapNotNull { fila -> decodificarEntregaLocal(fila.payload_json)?.let { p -> p.parada?.takeIf { it.recorrido_id == detalle.id }?.let { it.parada_id to (fila to p) } } }
        .preferirActivas()
    val noEntrego = cola.filter { it.operation_type == SyncOperationTypes.ACOPIADOR_NO_ENTREGO }
        .mapNotNull { fila -> runCatching { ApiJson.decodeFromString<NoEntregoPayload>(fila.payload_json) }.getOrNull()?.takeIf { it.recorrido_id == detalle.id }?.let { it.parada_id to (fila to it) } }
        .preferirActivas()
    val iniciadoLocal = cola.any { fila ->
        fila.operation_type == SyncOperationTypes.ACOPIADOR_INICIAR_RECORRIDO && fila.status in ACTIVAS &&
            runCatching { ApiJson.decodeFromString<RecorridoRefPayload>(fila.payload_json).recorrido_id }.getOrNull() == detalle.id
    }

    val paradas = detalle.paradas.sortedBy { it.orden_visita }.map { parada ->
        val servidor = when (parada.estado) {
            "RECOLECTADO" -> EstadoParada.RECOLECTADO
            "NO_ENTREGO" -> EstadoParada.NO_ENTREGO
            else -> EstadoParada.PENDIENTE
        }
        val acopio = acopios[parada.id]
        val marca = noEntrego[parada.id]
        when {
            servidor != EstadoParada.PENDIENTE -> ParadaVista(parada, servidor, parada.litros_registrados, false, null, parada.observacion)
            acopio != null && acopio.first.status in ACTIVAS -> ParadaVista(
                parada, EstadoParada.RECOLECTADO, acopio.second.request.litros,
                pendienteEnvio = acopio.first.status != SyncStatus.SYNCED.name, errorSync = null, motivo = null,
            )
            marca != null && marca.first.status in ACTIVAS -> ParadaVista(
                parada, EstadoParada.NO_ENTREGO, 0.0,
                pendienteEnvio = marca.first.status != SyncStatus.SYNCED.name, errorSync = null, motivo = marca.second.motivo,
            )
            else -> ParadaVista(
                parada, EstadoParada.PENDIENTE, 0.0, pendienteEnvio = false,
                errorSync = (acopio ?: marca)?.first?.takeIf { it.status == SyncStatus.NEEDS_REVIEW.name }?.error_message,
                motivo = null,
            )
        }
    }
    val estado = if (detalle.estado == "PENDIENTE" && iniciadoLocal) "EN_PROGRESO" else detalle.estado
    return RecorridoVista(detalle, estado, paradas)
}
