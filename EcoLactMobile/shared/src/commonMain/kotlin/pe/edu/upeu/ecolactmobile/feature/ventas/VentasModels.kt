package pe.edu.upeu.ecolactmobile.feature.ventas

import kotlinx.serialization.Serializable
import kotlinx.serialization.SerialName
import pe.edu.upeu.ecolactmobile.core.util.*

@Serializable
data class RangoVenta(val cliente: String, val desde: Double, val hasta: Double? = null, val precio: Double, val tipo: String)

@Serializable
data class ProductoVenta(
    val id: Long, val nombre: String, val stock: Double,
    val publico: Double? = null, val mayorista: Double? = null,
    val minimoMayorista: Double? = null, val proveedor: Double? = null,
    val rangos: List<RangoVenta> = emptyList(),
) {
    /** Laravel envía la etiqueta "Queso Paria · 1 kg"; se separa solo para presentarla en dos líneas. */
    val nombreBase get() = nombre.substringBefore(" · ")
    val presentacion get() = nombre.substringAfter(" · ", "").ifBlank { null }
    val vendible get() = stock > 0 && rangos.isNotEmpty()
}

@Serializable
data class ClienteVenta(val id: Long, val nombre: String, val documento: String? = null, val telefono: String? = null, val estado: String = "ACTIVO") {
    val documentoVisible get() = documento?.let { "${etiquetaDocumento(it)} $it" } ?: "Sin documento"
}

/** Solo presentación: el tipo de documento no se almacena, se deduce por longitud (DNI 8, RUC 11). */
fun etiquetaDocumento(documento: String?): String = when (documento?.length) { 8 -> "DNI"; 11 -> "RUC"; else -> "Documento" }

/** Cantidades sin ceros sobrantes: 12.0 → "12", 2.5 → "2.50". */
fun formatCantidad(valor: Double): String = formatDecimal(valor).removeSuffix(".00")

@Serializable
data class CatalogoVentas(val productos: List<ProductoVenta> = emptyList(), val proveedores: List<ClienteVenta> = emptyList())

@Serializable
data class CotizacionVenta(val precio_unitario: Double, val tipo_cliente: String, val importe_total: Double)

@Serializable
data class VentaRequest(
    val producto_id: Long, val cantidad: Double, val cliente: String = "PUBLICO",
    val proveedor_id: Long? = null, val comprador_id: Long? = null,
    val forma_pago: String = "CONTADO", val fecha_hora_evento: String = nowIso(),
    val precio_mostrado: Double? = null,
)

@Serializable
data class Venta(
    val id: Long = 0, val localId: String? = null, val producto_id: Long,
    val producto_nombre: String = "", val cliente: String = "Público general", val documento: String? = null,
    val proveedor_id: Long? = null, val comprador_id: Long? = null,
    val cantidad: Double, val precio_unitario: Double, val importe_total: Double,
    val tipo_cliente: String, val forma_pago: String, val fecha: String,
    val vendedor: String = "", val estado: String = "REGISTRADA", val error: String? = null,
    @SerialName("codigo") val codigoServidor: String? = null,
) {
    val codigo get() = codigoServidor ?: if (id > 0) "V-${id.toString().padStart(5, '0')}" else "LOCAL-${localId.orEmpty().take(8).uppercase()}"
    val pendiente get() = id == 0L && error == null
    val pago get() = if (forma_pago == "DESCUENTO_PLANILLA") "Descuento del pago semanal" else "Efectivo"
    val tipoPrecio get() = when (tipo_cliente) { "PROVEEDOR" -> "Proveedor"; "MAYORISTA" -> "Mayorista"; else -> "Público" }
    val documentoBoleta get() = if (proveedor_id == null && comprador_id == null) "XXXXXXXX" else documento ?: "—"
    val etiquetaDocumento get() = if (proveedor_id != null) "DNI" else etiquetaDocumento(documento)
    val estadoVisible get() = when { error != null -> "Error"; pendiente -> "Pendiente sync"; forma_pago == "DESCUENTO_PLANILLA" -> "Descuento semanal"; else -> "Completada" }
    val descuentoSemanal get() = forma_pago == "DESCUENTO_PLANILLA"
    val aceptada get() = id > 0 && error == null && estado == "REGISTRADA"
    /** La boleta definitiva (PDF de Laravel) solo existe para una venta ya aceptada por el servidor. */
    val boletaDisponible get() = aceptada
    val nombreArchivoBoleta get() = "EcoLact_Venta_$codigo.pdf"
    val textoCompartirBoleta get() = "EcoLact - Boleta de venta $codigo"
}

const val MENSAJE_BOLETA_PENDIENTE = "La boleta estará disponible cuando la venta se sincronice."

/** KPIs del vendedor: solo sus ventas aceptadas por Laravel; el descuento semanal no es efectivo cobrado. */
data class ResumenVentasDia(val ventas: Int, val cobrado: Double, val unidades: Double)

fun resumenDelDia(ventas: List<Venta>): ResumenVentasDia {
    val hoy = ventasEnPeriodo(ventas, "Hoy").filter { it.aceptada }
    return ResumenVentasDia(hoy.size, hoy.filterNot { it.descuentoSemanal }.sumOf { it.importe_total }, hoy.sumOf { it.cantidad })
}

@Serializable
data class VentaPendiente(val userId: Long, val request: VentaRequest, val venta: Venta)

@Serializable
data class NuevoComprador(val nombre: String, val documento: String? = null, val telefono: String? = null)

@Serializable
data class ComunicadoVentas(val id: Long, val titulo: String, val contenido: String, val fecha_publicacion: String = "")

/** Server-issued intervals are data, not a second public/wholesale pricing policy. */
fun cotizacionGuardada(producto: ProductoVenta, cliente: String, cantidad: Double): CotizacionVenta? {
    if (!cantidad.isFinite() || cantidad <= 0) return null
    val grupo = if (cliente == "PROVEEDOR") "PROVEEDOR" else "PUBLICO"
    val rango = producto.rangos.singleOrNull { it.cliente == grupo && cantidad >= it.desde && (it.hasta == null || cantidad < it.hasta) } ?: return null
    return CotizacionVenta(rango.precio, rango.tipo, kotlin.math.round(rango.precio * cantidad * 100) / 100)
}

fun ventasEnPeriodo(ventas: List<Venta>, periodo: String): List<Venta> {
    val hoy = hoy(ZONA_OPERACION)
    return ventas.filter {
        val dia = parseFechaLocal(it.fecha, ZONA_OPERACION)?.date
        when (periodo) {
            "Hoy" -> dia == hoy
            "Esta semana" -> dia != null && dia >= inicioDeSemana(hoy) && dia <= hoy
            "Este mes" -> dia != null && dia.toString().take(7) == hoy.toString().take(7)
            else -> true
        }
    }
}
