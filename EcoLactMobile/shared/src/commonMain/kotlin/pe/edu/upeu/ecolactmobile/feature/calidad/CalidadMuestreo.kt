package pe.edu.upeu.ecolactmobile.feature.calidad

import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import kotlinx.datetime.LocalDateTime

/*
 * Reglas de presentación del muestreo y análisis de calidad, sin Compose ni red: las usan los
 * ViewModels y se prueban en commonTest. Las reglas de NEGOCIO (qué entrega es elegible, el
 * resultado definitivo) viven en el backend; aquí solo se previsualiza y se combina lo local.
 */

enum class MetodoSeleccion(val api: String, val label: String) {
    ALEATORIA("ALEATORIA", "Aleatoria"),
    MANUAL("MANUAL", "Manual"),
    DIRECTA("DIRECTA", "Muestreo directo"),
    ;

    companion object {
        fun from(api: String?): MetodoSeleccion = entries.firstOrNull { it.api == api } ?: MANUAL
    }
}

enum class TipoControl(val api: String, val label: String) {
    RUTINARIO("RUTINARIO", "Muestreo rutinario"),
    DIRIGIDO("DIRIGIDO", "Control dirigido"),
}

/** Estado de la foto/OCR local. Nunca hay salto automático de PENDIENTE_OCR a confirmado. */
enum class EstadoCaptura {
    /** Foto guardada sin internet: el servidor todavía no la leyó. */
    PENDIENTE_OCR,

    /** El servidor leyó (o intentó leer) el ticket: el analista debe revisar antes de confirmar. */
    REQUIERE_REVISION,
    ;

    companion object {
        fun from(texto: String?): EstadoCaptura? = entries.firstOrNull { it.name == texto }
    }
}

enum class ResultadoCalidad(val label: String, val tono: Tono) {
    ACEPTADA("Aceptada", Tono.EXITO),
    OBSERVADA("Observada", Tono.ATENCION),
    RECHAZADA("Rechazada", Tono.ERROR),
    ;

    companion object {
        fun from(texto: String?): ResultadoCalidad? = entries.firstOrNull { it.name == texto }
    }
}

/** Fila de "Muestras pendientes": lo del servidor más lo seleccionado sin conexión. */
data class MuestraPendiente(
    val entregaId: Long,
    val muestreoId: Long?,
    val proveedorNombre: String,
    val proveedorDni: String?,
    val zonaNombre: String?,
    val acopiadorNombre: String?,
    val litros: Double,
    val fechaHora: String,
    val litrosEditablesHasta: String?,
    val metodo: MetodoSeleccion,
    val tipoControl: String?,
    val fechaSeleccion: String,
    val observacion: String?,
    /** Selección hecha sin conexión, todavía no confirmada por el servidor. */
    val seleccionLocal: Boolean,
    /** Hay foto/valores guardados en el teléfono para esta muestra. */
    val captura: EstadoCaptura?,
)

/** Selección encolada sin conexión (payload de CALIDAD_SELECCIONAR_MUESTRA). */
data class SeleccionLocal(val request: SeleccionarMuestraRequest, val entrega: EntregaElegible)

fun MuestraCalidad.esPendiente(): Boolean = estado == "SELECCIONADA" || estado == "EN_ANALISIS"

/**
 * Pendientes = muestras del servidor aún sin analizar + selecciones locales todavía no
 * sincronizadas, sin las que ya tienen un análisis confirmado en el teléfono (esperando
 * enviarse). NUNCA incluye entregas que no fueron seleccionadas.
 */
fun combinarPendientes(
    servidor: List<MuestraCalidad>,
    seleccionesLocales: List<SeleccionLocal>,
    entregasConAnalisisLocal: Set<Long>,
    capturas: Map<Long, EstadoCaptura>,
): List<MuestraPendiente> {
    val delServidor = servidor.filter { it.esPendiente() }.map { m ->
        MuestraPendiente(
            entregaId = m.entregaId,
            muestreoId = m.id,
            proveedorNombre = m.proveedorNombre,
            proveedorDni = m.proveedorDni,
            zonaNombre = m.zonaNombre,
            acopiadorNombre = m.acopiadorNombre,
            litros = m.litros,
            fechaHora = m.fechaHora,
            litrosEditablesHasta = m.litrosEditablesHasta,
            metodo = MetodoSeleccion.from(m.metodoSeleccion),
            tipoControl = m.tipoControl,
            fechaSeleccion = m.fechaSeleccion,
            observacion = m.observacion,
            seleccionLocal = false,
            captura = capturas[m.entregaId],
        )
    }
    val idsServidor = servidor.map { it.entregaId }.toSet()
    val locales = seleccionesLocales
        .filter { it.entrega.entregaId !in idsServidor }
        .distinctBy { it.entrega.entregaId }
        .map { s ->
            MuestraPendiente(
                entregaId = s.entrega.entregaId,
                muestreoId = null,
                proveedorNombre = s.entrega.proveedorNombre,
                proveedorDni = s.entrega.proveedorDni,
                zonaNombre = s.entrega.zonaNombre,
                acopiadorNombre = s.entrega.acopiadorNombre,
                litros = s.entrega.litros,
                fechaHora = s.entrega.fechaHora,
                litrosEditablesHasta = s.entrega.litrosEditablesHasta,
                metodo = MetodoSeleccion.from(s.request.metodo_seleccion),
                tipoControl = s.request.tipo_control,
                fechaSeleccion = s.request.fecha_seleccion.orEmpty(),
                observacion = s.request.observacion,
                seleccionLocal = true,
                captura = capturas[s.entrega.entregaId],
            )
        }
    return (delServidor + locales)
        .filter { it.entregaId !in entregasConAnalisisLocal }
        .sortedBy { it.fechaSeleccion }
}

/**
 * Pasados los 10 minutos editables, los litros quedan confirmados (dato informativo). La regla
 * de Entrega::MINUTOS_EDICION_LITROS solo limita la corrección de litros: Calidad puede
 * muestrear y analizar después sin ningún bloqueo.
 */
fun litrosConfirmados(litrosEditablesHasta: String?, ahora: LocalDateTime): Boolean {
    val limite = litrosEditablesHasta?.let { parseFechaLocal(it) } ?: return false
    return ahora >= limite
}

/** Acepta coma o punto decimal. Vacío o inválido = null ("No detectado"), nunca 0. */
fun parseValor(texto: String): Double? = texto.trim().replace(',', '.').takeIf { it.isNotEmpty() }?.toDoubleOrNull()

/**
 * Valores propuestos por el OCR para TODOS los parámetros activos: los no detectados quedan null
 * (se muestran vacíos / "No detectado"), jamás 0.
 */
fun valoresDesdeOcr(ocr: TicketOcrResponse, parametros: List<ParametroActivo>): Map<String, Double?> {
    val leidos = ocr.valores.associate { it.codigo to it.valor }
    return parametros.associate { it.codigo to leidos[it.codigo] }
}

data class ResultadoEstimado(val resultado: ResultadoCalidad, val sinRegla: List<String>)

/**
 * Vista previa del resultado con las reglas vigentes guardadas (sirve sin conexión). Mismo
 * criterio que CalcularResultadoCalidad: cualquier RECHAZADA gana, luego OBSERVADA, si no
 * ACEPTADA. Solo orienta: el resultado que cuenta lo calcula el servidor al confirmar.
 * null si todavía no hay ningún valor.
 */
fun estimarResultado(valores: Map<String, Double?>, reglas: List<ReglaVigente>): ResultadoEstimado? {
    val medidos = valores.filterValues { it != null }
    if (medidos.isEmpty()) return null
    val porCodigo = reglas.associateBy { it.codigo }
    val incumplimientos = mutableListOf<ResultadoCalidad>()
    val sinRegla = mutableListOf<String>()
    medidos.forEach { (codigo, valor) ->
        val regla = porCodigo[codigo]
        if (regla == null) {
            sinRegla += codigo
            return@forEach
        }
        val v = valor!!
        val fuera = (regla.valor_minimo != null && v < regla.valor_minimo) || (regla.valor_maximo != null && v > regla.valor_maximo)
        if (fuera) ResultadoCalidad.from(regla.resultado_incumplimiento)?.let { incumplimientos += it }
    }
    val resultado = when {
        ResultadoCalidad.RECHAZADA in incumplimientos -> ResultadoCalidad.RECHAZADA
        ResultadoCalidad.OBSERVADA in incumplimientos -> ResultadoCalidad.OBSERVADA
        else -> ResultadoCalidad.ACEPTADA
    }
    return ResultadoEstimado(resultado, sinRegla)
}

/**
 * Orden fijo de los parámetros en "Datos detectados" y en el detalle, igual que el ticket del
 * analizador. Los códigos que no estén aquí van al final en el orden del servidor.
 */
private val ORDEN_PARAMETROS = listOf(
    "GRASA", "SNG", "DENSIDAD", "PROTEINA", "LACTOSA", "SALES", "TOTAL_SOLIDOS", "AGUA_ANADIDA", "PUNTO_CONGELACION", "PH",
)

fun <T> ordenarPorParametro(items: List<T>, codigo: (T) -> String): List<T> =
    items.sortedBy { ORDEN_PARAMETROS.indexOf(codigo(it)).let { i -> if (i < 0) Int.MAX_VALUE else i } }
