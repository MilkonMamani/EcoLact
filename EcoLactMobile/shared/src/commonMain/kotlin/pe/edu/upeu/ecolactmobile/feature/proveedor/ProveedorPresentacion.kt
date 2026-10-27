package pe.edu.upeu.ecolactmobile.feature.proveedor

import kotlinx.datetime.DateTimeUnit
import kotlinx.datetime.LocalDate
import kotlinx.datetime.minus
import pe.edu.upeu.ecolactmobile.core.util.inicioDeSemana
import pe.edu.upeu.ecolactmobile.core.util.numeroSemanaIso
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal

/**
 * Reglas de presentación del proveedor, sin Compose para poder probarlas: cómo se nombra cada
 * estado, qué cuenta como "esta semana", cómo se arma "Semana 43", etc.
 */

/** Tono semántico de un estado. Verde = bien, naranja suave = atención, rojo = rechazo. */
enum class Tono { EXITO, ATENCION, NEUTRAL, ERROR }

/** Estado de una entrega tal como lo entiende el proveedor (combina estado + resultado de calidad). */
enum class EstadoEntregaVisual(val label: String, val tono: Tono) {
    ACEPTADA("Aceptada", Tono.EXITO),
    OBSERVADA("Observada", Tono.ATENCION),
    PENDIENTE("Pendiente de análisis", Tono.NEUTRAL),
    RECHAZADA("Rechazada", Tono.ERROR),
    ANULADA("Anulada", Tono.NEUTRAL),
}

fun estadoEntrega(estado: String?, resultado: String?): EstadoEntregaVisual = when {
    estado == "ANULADA" -> EstadoEntregaVisual.ANULADA
    resultado == "ACEPTADA" -> EstadoEntregaVisual.ACEPTADA
    resultado == "OBSERVADA" -> EstadoEntregaVisual.OBSERVADA
    resultado == "RECHAZADA" -> EstadoEntregaVisual.RECHAZADA
    else -> EstadoEntregaVisual.PENDIENTE
}

/** Resultado de un análisis de calidad (ACEPTADA / OBSERVADA / RECHAZADA). */
fun estadoResultado(resultado: String?): EstadoEntregaVisual = estadoEntrega(null, resultado)

/** Estados de liquidación del backend (EstadoLiquidacion.php). */
fun estadoPagoLabel(estado: String): Pair<String, Tono> = when (estado) {
    "PAGADA" -> "Pagado" to Tono.EXITO
    "PARCIALMENTE_PAGADA" -> "Pago parcial" to Tono.ATENCION
    "PENDIENTE" -> "Pendiente" to Tono.ATENCION
    "ANULADA" -> "Anulada" to Tono.NEUTRAL
    else -> estado.lowercase().replaceFirstChar { it.uppercase() } to Tono.NEUTRAL
}

data class ResumenSemana(val litros: Double, val entregas: Int)

/**
 * Litros y entregas de la semana de [hoy]. Igual que el dashboard del backend: una entrega
 * rechazada se cuenta como entrega pero sus litros no (esa leche no se acopió); las anuladas no
 * cuentan.
 */
fun resumenSemana(filas: List<EntregaResumen>, hoy: LocalDate): ResumenSemana {
    val inicio = inicioDeSemana(hoy)
    val deLaSemana = filas.filter { it.estado != "ANULADA" && semanaDe(it.fecha_hora) == inicio }
    return ResumenSemana(
        litros = deLaSemana.filter { it.resultado != "RECHAZADA" }.sumOf { it.litros },
        entregas = deLaSemana.size,
    )
}

/** Filtro de semana de "Mis entregas", relativo a hoy. */
enum class FiltroSemana(val label: String, val semanasAtras: Int?) {
    TODAS("Todas las semanas", null),
    ESTA("Esta semana", 0),
    ANTERIOR("Semana anterior", 1),
}

/** Filtros locales de "Mis entregas": funcionan igual con o sin conexión. [estado] null = todos. */
fun filtrarEntregas(
    filas: List<EntregaResumen>,
    semana: FiltroSemana,
    estado: EstadoEntregaVisual?,
    hoy: LocalDate,
): List<EntregaResumen> {
    val lunes = semana.semanasAtras?.let { inicioDeSemana(hoy).minus(it * 7, DateTimeUnit.DAY) }
    return filas.filter { fila ->
        (lunes == null || semanaDe(fila.fecha_hora) == lunes) &&
            (estado == null || estadoEntrega(fila.estado, fila.resultado) == estado)
    }
}

/** Lunes de la semana a la que pertenece una fecha del backend, o null si no se entiende. */
fun semanaDe(fecha: String): LocalDate? = parseFechaLocal(fecha)?.date?.let(::inicioDeSemana)

/** "Semana 43": por la fecha de inicio del periodo; si el backend no la envía, por la fecha de cálculo. */
fun semanaLabel(liquidacion: LiquidacionResumen): String {
    val referencia = liquidacion.fecha_inicio ?: liquidacion.fecha_calculo ?: return "Liquidación"
    val fecha = parseFechaLocal(referencia)?.date ?: return "Liquidación"
    return "Semana ${numeroSemanaIso(fecha)}"
}

/** Compras de producto descontadas en la liquidación (ajustes DESCUENTO_PRODUCTO, que el backend guarda en negativo). */
fun comprasDescontadas(detalle: LiquidacionDetalleResponse): Double =
    -detalle.ajustes.filter { it.tipo == AJUSTE_DESCUENTO_PRODUCTO }.sumOf { it.monto }

/** Ajustes que no son compras (ej. sanciones de calidad). */
fun otrosAjustes(detalle: LiquidacionDetalleResponse): Double =
    detalle.ajustes.filter { it.tipo != AJUSTE_DESCUENTO_PRODUCTO }.sumOf { it.monto }

/** El precio semanal se fija por el peor resultado de la semana: con cualquier incidencia, se paga como observada. */
fun resultadoSemanal(liquidacion: LiquidacionResumen): EstadoEntregaVisual =
    if (liquidacion.tuvo_incidencia) EstadoEntregaVisual.OBSERVADA else EstadoEntregaVisual.ACEPTADA

const val AJUSTE_DESCUENTO_PRODUCTO = "DESCUENTO_PRODUCTO"
const val FORMA_PAGO_DESCUENTO = "DESCUENTO_PLANILLA"
