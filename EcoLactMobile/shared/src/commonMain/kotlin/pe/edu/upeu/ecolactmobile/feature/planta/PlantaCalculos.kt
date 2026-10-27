package pe.edu.upeu.ecolactmobile.feature.planta

import pe.edu.upeu.ecolactmobile.core.util.formatDecimal
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import kotlin.math.abs
import kotlin.math.round

// Presentación y cálculos de la app de planta. Los números de verdad (requerimientos, stock,
// equivalencias) los calcula siempre el servidor; aquí solo se multiplican valores que ya vinieron
// de él, para previsualizar sin conexión. Al sincronizar, el servidor vuelve a calcular y validar.

/** Redondeo a 4 decimales, igual que el backend (round(x, 4)). */
fun redondear4(valor: Double): Double = round(valor * 10_000.0) / 10_000.0

/**
 * "50 000 g", "245 L", "1.5 kg", "0.25 L": miles con espacio, sin ceros decimales sobrantes.
 * Nunca usa coma de miles para no confundirla con el separador decimal.
 */
fun formatCantidad(valor: Double, unidad: String? = null): String {
    val texto = formatDecimal(valor, 2).trimEnd('0').trimEnd('.')
    val negativo = texto.startsWith("-")
    val sinSigno = texto.removePrefix("-")
    val entero = sinSigno.substringBefore('.')
    val decimales = sinSigno.substringAfter('.', "")
    val agrupado = entero.reversed().chunked(3).joinToString(" ").reversed()
    val numero = (if (negativo) "-" else "") + agrupado + (if (decimales.isNotEmpty()) ".$decimales" else "")
    return if (unidad.isNullOrBlank()) numero else "$numero $unidad"
}

/** "+20 000 ml" / "-60 ml" para movimientos de stock. */
fun formatCantidadConSigno(valor: Double, unidad: String?, entrada: Boolean): String =
    (if (entrada) "+" else "-") + formatCantidad(abs(valor), unidad)

/** "Mililitros (ml)" para la unidad base de un insumo. */
fun nombreUnidad(unidad: String): String = when (unidad.lowercase()) {
    "ml" -> "Mililitros (ml)"
    "l" -> "Litros (L)"
    "g" -> "Gramos (g)"
    "kg" -> "Kilogramos (kg)"
    "unidad", "und", "u" -> "Unidades"
    else -> unidad
}

/** "500 unid." para cantidades de producto. */
fun formatUnidades(valor: Double, unidad: String? = null): String {
    val u = unidad?.lowercase()
    val corta = if (u == null || u == "unidad" || u == "unidades" || u == "und") "unid." else unidad
    return formatCantidad(valor, corta)
}

/** "480 unidades" en el detalle (texto completo). */
fun formatUnidadesLargo(valor: Double, unidad: String? = null): String {
    val u = unidad?.lowercase()
    val larga = if (u == null || u == "unidad" || u == "unidades" || u == "und") (if (valor == 1.0) "unidad" else "unidades") else unidad
    return formatCantidad(valor, larga)
}

// ---- Estado de los insumos ----

enum class NivelInsumo(val label: String, val tono: Tono) {
    LECHE("Del sistema (acopio)", Tono.NEUTRAL),
    OK("OK", Tono.EXITO),
    BAJO("Stock bajo", Tono.ATENCION),
    SIN_STOCK("Sin stock", Tono.ERROR),
}

fun nivelInsumo(insumo: InsumoFila): NivelInsumo = when {
    insumo.esLeche -> NivelInsumo.LECHE
    insumo.stock_actual <= 0.0 -> NivelInsumo.SIN_STOCK
    insumo.esta_bajo_minimo -> NivelInsumo.BAJO
    else -> NivelInsumo.OK
}

// ---- Estado de los lotes ----

fun estadoLote(estado: String): Pair<String, Tono> = when (estado) {
    "EN_PROCESO" -> "En proceso" to Tono.EXITO
    "FINALIZADO" -> "Finalizado" to Tono.NEUTRAL
    "ANULADO" -> "Anulado" to Tono.ERROR
    else -> estado.lowercase().replaceFirstChar { it.uppercase() } to Tono.NEUTRAL
}

/** "Compra (boleta)", "Producción LOT-00001"... a partir del motivo del movimiento. */
fun descripcionMovimiento(m: MovimientoInsumo): String {
    val base = when (m.motivo) {
        "INGRESO_COMPRA" -> "Compra (boleta)"
        "INGRESO_ACOPIO" -> "Ingreso por acopio"
        "CONSUMO_PRODUCCION" -> "Producción"
        "AJUSTE_ENTRADA" -> "Ajuste de entrada"
        "AJUSTE_SALIDA" -> "Ajuste de salida"
        "REVERSO" -> "Reverso"
        else -> if (m.tipo == "ENTRADA") "Entrada" else "Salida"
    }
    val lote = m.observacion?.let { Regex("LOT-\\d+").find(it)?.value }
    return if (m.motivo == "CONSUMO_PRODUCCION" && lote != null) "$base $lote" else base
}

// ---- Nueva producción ----

/**
 * Previsualización SIN conexión: receta del servidor (cantidad por unidad, ya en la unidad base)
 * × cantidad a producir, contra el último stock descargado. Es solo una guía: al sincronizar,
 * el servidor recalcula con el stock real y rechaza si ya no alcanza.
 */
fun requerimientosLocales(receta: RecetaFila, cantidad: Double, insumos: List<InsumoFila>): RequerimientosResponse {
    val porId = insumos.associateBy { it.id }
    val filas = receta.por_unidad.map { ingrediente ->
        val necesario = redondear4(ingrediente.cantidad * cantidad)
        val disponible = porId[ingrediente.insumo_id]?.stock_actual ?: 0.0
        val suficiente = disponible + 0.00005 >= necesario
        RequerimientoInsumo(
            insumo_id = ingrediente.insumo_id,
            nombre = ingrediente.nombre,
            unidad = ingrediente.unidad,
            necesario = necesario,
            disponible = disponible,
            faltante = if (suficiente) 0.0 else redondear4(necesario - disponible),
            suficiente = suficiente,
            es_leche = ingrediente.es_leche,
        )
    }
    val puede = cantidad > 0 && filas.isNotEmpty() && filas.all { it.suficiente }
    return RequerimientosResponse(
        insumos = filas,
        puede_iniciar = puede,
        mensajes = filas.filterNot { it.suficiente }.map { "No hay suficiente ${it.nombre}." },
    )
}

/** Stock que quedará en el producto al finalizar: anterior + obtenido. */
fun stockNuevo(anterior: Double, obtenido: Double): Double = redondear4(anterior + obtenido)

private val MESES_CORTOS = listOf("ene.", "feb.", "mar.", "abr.", "may.", "jun.", "jul.", "ago.", "set.", "oct.", "nov.", "dic.")

/** "14 abr. 2025 · 09:35" (hora local del teléfono). */
fun formatFechaHoraLarga(texto: String): String {
    val f = parseFechaLocal(texto) ?: return texto
    val s = f.toString()
    val mes = MESES_CORTOS[s.substring(5, 7).toInt() - 1]
    return "${s.substring(8, 10).trimStart('0')} $mes ${s.substring(0, 4)} · ${s.substring(11, 16)}"
}

/** "14 abr. 2025" */
fun formatFechaLarga(texto: String): String = formatFechaHoraLarga(texto).substringBefore(" ·")

/** "Leche utilizada", "Cuajo utilizado": concordancia simple por el nombre del insumo. */
fun etiquetaUtilizado(insumo: String): String {
    val primera = insumo.trim().substringBefore(' ').lowercase()
    val femenino = primera.endsWith("a") || primera in setOf("leche", "sal", "miel", "levadura")
    return "$insumo ${if (femenino) "utilizada" else "utilizado"}"
}

/** Texto de un decimal escrito por la persona: acepta coma o punto; null si no es un número válido. */
fun parseDecimal(texto: String): Double? = texto.trim().replace(',', '.').toDoubleOrNull()?.takeIf { it.isFinite() }
