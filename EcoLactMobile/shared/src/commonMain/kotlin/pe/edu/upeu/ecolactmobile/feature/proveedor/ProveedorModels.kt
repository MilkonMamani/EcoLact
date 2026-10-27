package pe.edu.upeu.ecolactmobile.feature.proveedor

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

// ---- 3. INICIO ----

@Serializable
data class DashboardKpis(val entregas: Int = 0, val litros: Double = 0.0)

@Serializable
data class UltimaLiquidacionResumen(val id: Long, val total_pagar: Double, val estado: String)

@Serializable
data class UltimaLiquidacionPeriodo(val fecha_inicio: String, val fecha_fin: String)

@Serializable
data class EntregaResumen(
    val id: Long,
    val fecha_hora: String,
    val litros: Double,
    val estado: String? = null,
    val zona_id: Long? = null,
    val resultado: String? = null,
)

@Serializable
data class CompraResumen(
    val id: Long,
    val producto_id: Long,
    val producto_nombre: String? = null,
    val cantidad: Double,
    val importe_total: Double,
    val forma_pago: String,
    val fecha: String,
)

@Serializable
data class ComunicadoResumen(
    val id: Long,
    val titulo: String,
    val tipo: String,
    val fecha_publicacion: String,
    val fecha_evento: String? = null,
    val leido: Boolean? = null,
)

@Serializable
data class ProveedorDashboard(
    val kpis: DashboardKpis,
    val ultimo_analisis_resultado: String? = null,
    val ultima_liquidacion: UltimaLiquidacionResumen? = null,
    val ultima_liquidacion_periodo: UltimaLiquidacionPeriodo? = null,
    val por_cobrar: Double = 0.0,
    val ultimas_entregas: List<EntregaResumen> = emptyList(),
    val compras_recientes: List<CompraResumen> = emptyList(),
    val comunicados_recientes: List<ComunicadoResumen> = emptyList(),
)

// ---- 4. ENTREGAS ----

@Serializable
data class EntregasKpis(val entregas: Int = 0, val litros: Double = 0.0, val analizadas: Int = 0, val pendientes: Int = 0)

@Serializable
data class EntregasListResponse(val kpis: EntregasKpis, val filas: List<EntregaResumen>)

@Serializable
data class EntregaDetalleEntrega(
    val id: Long,
    val fecha_hora: String,
    val litros: Double,
    val zona_id: Long? = null,
    val estado: String,
    val zona_nombre: String? = null,
    val acopiador_nombre: String? = null,
)

@Serializable
data class EntregaDetalleAnalisis(val id: Long, val resultado: String, val fecha_analisis: String)

@Serializable
data class EntregaDetalleResponse(
    val entrega: EntregaDetalleEntrega,
    val analisis: EntregaDetalleAnalisis? = null,
)

// ---- 5. PAGOS (Liquidaciones + Pagos) ----

@Serializable
data class LiquidacionResumen(
    val id: Long,
    val periodo_id: Long,
    val total_litros_entregados: Double,
    val total_litros_pagables: Double,
    @Serializable(with = pe.edu.upeu.ecolactmobile.core.network.NullAsZeroDoubleSerializer::class)
    val precio_litro_aplicado: Double = 0.0,
    val subtotal: Double,
    val total_ajustes: Double,
    val total_pagar: Double,
    val estado: String,
    val fecha_calculo: String? = null,
    val tuvo_incidencia: Boolean = false,
    val saldo_pendiente: Double? = null,
    /** Semana liquidada (lunes a domingo). Opcionales: backends anteriores no los envían. */
    val fecha_inicio: String? = null,
    val fecha_fin: String? = null,
    /** Compras de producto descontadas del pago de esa semana (positivo). */
    val compras_descontadas: Double? = null,
)

@Serializable
data class LiquidacionesResumenGlobal(
    val ultima_liquidacion_id: Long? = null,
    val por_cobrar: Double = 0.0,
    val pagado: Double = 0.0,
    val total_liquidaciones: Int = 0,
)

@Serializable
data class LiquidacionesListResponse(
    val filas: List<LiquidacionResumen>,
    val resumen: LiquidacionesResumenGlobal? = null,
)

@Serializable
data class AjusteLiquidacion(val id: Long, val tipo: String, val descripcion: String? = null, val monto: Double)

@Serializable
data class PagoLiquidacion(
    val id: Long,
    val fecha_pago: String? = null,
    val monto: Double,
    val estado: String,
    val observacion: String? = null,
)

/** Fila de entrega dentro del detalle de liquidación (ObtenerDetalleLiquidacion: fecha ya formateada "d/m/Y"). */
@Serializable
data class LiquidacionEntregaFila(
    val fecha: String,
    val litros: Double,
    val resultado: String? = null,
    val pagables: Double = 0.0,
)

/** `incidencia` se deja como JSON crudo: solo se usa para saber si existe (no null). */
@Serializable
data class LiquidacionDetalleResponse(
    val liquidacion: LiquidacionResumen,
    val entregas: List<LiquidacionEntregaFila> = emptyList(),
    val incidencia: JsonElement? = null,
    val compras: List<CompraResumen> = emptyList(),
    val ajustes: List<AjusteLiquidacion> = emptyList(),
    val pagos: List<PagoLiquidacion> = emptyList(),
    val saldo_pendiente: Double = 0.0,
)

@Serializable
data class PagoResumen(
    val id: Long,
    val liquidacion_id: Long,
    val fecha_pago: String? = null,
    val monto: Double,
    val estado: String,
    val observacion: String? = null,
)

// ---- 6. MÁS / Mi calidad ----

@Serializable
data class AnalisisKpis(val total: Int = 0, val aceptados: Int = 0, val observados: Int = 0, val rechazados: Int = 0)

@Serializable
data class AnalisisResumen(
    val id: Long,
    val entrega_id: Long,
    val resultado: String,
    val estado: String,
    val fecha_analisis: String,
)

@Serializable
data class AnalisisListResponse(val kpis: AnalisisKpis, val filas: List<AnalisisResumen>)

@Serializable
data class AnalisisEntregaRef(val litros: Double, val zona_nombre: String? = null)

@Serializable
data class AnalisisParametroDetalle(
    val parametro_codigo: String,
    val parametro_nombre: String,
    val unidad: String? = null,
    val valor: Double,
    val cumple: Boolean? = null,
)

@Serializable
data class AnalisisSancion(val id: Long, val tipo: String, val descripcion: String? = null)

@Serializable
data class AnalisisDetalleResponse(
    val id: Long,
    val entrega_id: Long,
    val resultado: String,
    val estado: String,
    val fecha_analisis: String,
    val observacion: String? = null,
    val entrega: AnalisisEntregaRef? = null,
    val detalles: List<AnalisisParametroDetalle> = emptyList(),
    val sancion: AnalisisSancion? = null,
)

// ---- 6. Mis compras ----

@Serializable
data class ComprasKpis(val compras: Int = 0, val unidades_adquiridas: Double = 0.0, val total_comprado: Double = 0.0)

@Serializable
data class ComprasListResponse(val kpis: ComprasKpis, val filas: List<CompraResumen>)

@Serializable
data class CompraDetalleResponse(
    val id: Long,
    val producto_id: Long,
    val producto_nombre: String? = null,
    val cantidad: Double,
    val precio_unitario: Double,
    val importe_total: Double,
    val forma_pago: String,
    val fecha: String,
    val liquidacion_relacionada_id: Long? = null,
)

// ---- 6. Mi QR ----

@Serializable
data class MiQrResponse(
    val qr_token: String,
    val nombre_completo: String,
    val dni: String,
    val zona_id: Long? = null,
    val estado: String,
    val zona_nombre: String? = null,
)

// ---- 6. Comunicados ----

@Serializable
data class ComunicadosKpis(val total: Int = 0, val no_leidos: Int = 0)

@Serializable
data class ComunicadosListResponse(val kpis: ComunicadosKpis, val filas: List<ComunicadoResumen>)

@Serializable
data class ComunicadoDetalleResponse(
    val id: Long,
    val titulo: String,
    val contenido: String,
    val tipo: String,
    val fecha_publicacion: String,
    val fecha_evento: String? = null,
)

// ---- 6. Mi perfil ----

@Serializable
data class ProveedorPerfil(
    val id: Long,
    val dni: String,
    val nombres: String,
    val apellidos: String,
    val nombre_completo: String,
    val telefono: String? = null,
    val zona_id: Long? = null,
    val estado: String,
    val fecha_registro: String? = null,
    val zona_nombre: String? = null,
)
