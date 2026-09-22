package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

// ---- INICIO ----

@Serializable
data class PlantaDashboardKpis(
    val enCurso: Int = 0,
    val lotesProducidos: Int = 0,
    val litrosUtilizados: Double = 0.0,
    val productosProducidos: Int = 0,
)

@Serializable
data class ProduccionResumen(
    val id: Long,
    val codigo: String,
    val fecha_hora_inicio: String? = null,
    val fecha_hora_fin: String? = null,
    val litros_leche_utilizados: Double = 0.0,
    val estado: String,
)

@Serializable
data class InsumoBajoMinimo(val id: Long, val nombre: String, val unidad_medida: String? = null, val stock_actual: Double, val stock_minimo: Double)

@Serializable
data class InventarioResumen(val total: Int = 0, val suficiente: Int = 0, val bajo: Int = 0, val sinStock: Int = 0)

@Serializable
data class ProductoDisponibleResumen(val producto: String, val stock: Double, val unidad: String? = null)

@Serializable
data class PlantaNotificacionResumen(val id: Long, val tipo: String, val titulo: String, val mensaje: String, val leido: Boolean = false)

@Serializable
data class PlantaDashboard(
    val kpis: PlantaDashboardKpis,
    val produccion_actual: List<ProduccionResumen> = emptyList(),
    val produccion_reciente: List<ProduccionResumen> = emptyList(),
    val insumos_bajo_minimo: List<InsumoBajoMinimo> = emptyList(),
    val inventario: InventarioResumen? = null,
    val productos_disponibles: List<ProductoDisponibleResumen> = emptyList(),
    val notificaciones_recientes: List<PlantaNotificacionResumen> = emptyList(),
)

// ---- PRODUCCIÓN ----

@Serializable
data class ProduccionKpis(val enProceso: Int = 0, val finalizados: Int = 0, val lecheUtilizada: Double = 0.0, val productosObtenidos: Int = 0)

/** `productos` no tiene shape confirmado (el mockup muestra "125 unidades" pero el .md no lo detalla) — se guarda crudo y se muestra tal cual. */
@Serializable
data class ProduccionListFila(
    val id: Long,
    val codigo: String,
    val inicio: String? = null,
    val fin: String? = null,
    val litros: Double = 0.0,
    val productos: JsonElement? = null,
    val estado: String,
)

@Serializable
data class ProduccionListResponse(val kpis: ProduccionKpis, val filas: List<ProduccionListFila>)

// ---- Registrar producción: paso "Leche" ----
//
// CORREGIDO (Etapa 3): el acopio no separa la leche por entrega (se junta todo en un solo
// recipiente antes de llegar a planta) — el backend real (CrearLoteProduccion.php,
// StoreLoteProduccionRequest.php) NO tiene "seleccionar entregas", solo pide el total de litros
// a usar contra el acumulado disponible. Las clases EntregaDisponible/EntregaUtilizadaInput y el
// endpoint `entregas-disponibles` que existían antes en la app NUNCA correspondieron al backend
// real — no era una desactualización reciente, sino un supuesto incorrecto desde el diseño
// original. `codigo` tampoco se envía: lo genera el servidor (LOT-00001, ...).
//
// IMPORTANTE: IniciarProduccionViewModel y RegistrarResultadoViewModel (feature/planta/screens)
// todavía usan las clases viejas — quedan rotos a propósito hasta la Etapa 8 (Producción e
// Insumos), donde se rediseña el flujo de esas pantallas para que coincida con este contrato.

@Serializable
data class LecheDisponibleResponse(val litros_disponibles: Double)

@Serializable
data class IniciarProduccionRequest(
    val litros_utilizados: Double,
    val fecha_hora_inicio: String? = null,
    val observacion: String? = null,
)

@Serializable
data class LoteCreado(val id: Long, val codigo: String, val estado: String)

// ---- Detalle de lote (+ catálogo de productos para "Resultado") ----
//
// CORREGIDO (Etapa 3): ProduccionController::show()/registrarProduccion() (Api\Planta) no
// devuelven `entregas_utilizadas` (no se trackea por entrega) ni `insumos_activos` (ya no se
// eligen insumos a mano, se calculan solos — ver RegistrarResultadoRequest debajo).

@Serializable
data class InsumoUtilizadoDetalle(val insumoId: Long, val insumoNombre: String? = null, val unidadMedida: String? = null, val cantidadUsada: Double = 0.0)

@Serializable
data class ProductoObtenidoDetalle(val productoId: Long, val productoNombre: String? = null, val unidadMedida: String? = null, val cantidadProducida: Double = 0.0)

@Serializable
data class LoteDetalleInfo(
    val id: Long,
    val codigo: String,
    val estado: String,
    val fecha_hora_inicio: String? = null,
    val fecha_hora_fin: String? = null,
    val litros_leche_utilizados: Double = 0.0,
    val observacion: String? = null,
    val insumos_utilizados: List<InsumoUtilizadoDetalle> = emptyList(),
    val productos_obtenidos: List<ProductoObtenidoDetalle> = emptyList(),
)

@Serializable
data class ProductoActivo(val id: Long, val nombre: String, val unidad_medida: String? = null)

@Serializable
data class LoteDetalleResponse(
    val lote: LoteDetalleInfo,
    val productos_activos: List<ProductoActivo> = emptyList(),
)

// ---- Registrar producción: paso "Resultado + Confirmar" (un solo paso, sin insumos manuales) ----
//
// CORREGIDO (Etapa 3): `insumos_utilizados` ya NO se envía en el request — el backend calcula
// solo la receta × cantidad_producida y la devuelve en la respuesta (LoteDetalleInfo de arriba).
// Enviar este campo hoy no rompe nada (el backend simplemente lo ignora, ver
// StoreProduccionDetalleRequest.php), pero mantenerlo en el DTO induce a error: por eso se quita.

@Serializable
data class RegistrarResultadoRequest(
    val producto_id: Long,
    val cantidad_producida: Double,
    val marcar_finalizado: Boolean = true,
    val fecha_hora_fin: String? = null,
)

// ---- INVENTARIO / Insumos ----

@Serializable
data class InsumosKpis(val insumos: Int = 0, val stock_bajo: Int = 0, val con_stock: Int = 0, val sin_stock: Int = 0)

@Serializable
data class InsumoFila(
    val id: Long,
    val nombre: String,
    val unidad_medida: String? = null,
    val stock_actual: Double,
    val stock_minimo: Double? = null,
    val estado: String? = null,
    val esta_bajo_minimo: Boolean = false,
)

@Serializable
data class InsumosListResponse(val kpis: InsumosKpis, val filas: List<InsumoFila>)

@Serializable
data class MovimientoInsumo(val id: Long, val tipo: String, val motivo: String? = null, val cantidad: Double, val fecha: String, val observacion: String? = null)

@Serializable
data class InsumoDetalleResponse(val insumo: InsumoFila, val movimientos: List<MovimientoInsumo> = emptyList())

@Serializable
data class MovimientosKpis(val total: Int = 0, val entradas: Int = 0, val salidas: Int = 0)

@Serializable
data class MovimientoFila(
    val id: Long,
    val insumo_id: Long,
    val insumo_nombre: String? = null,
    val tipo: String,
    val motivo: String? = null,
    val cantidad: Double,
    val fecha: String,
    val observacion: String? = null,
)

@Serializable
data class MovimientosListResponse(val kpis: MovimientosKpis, val filas: List<MovimientoFila>)

@Serializable
data class CrearInsumoRequest(val nombre: String, val unidad_medida: String, val stock_minimo: Double, val stock_inicial: Double? = null)

@Serializable
data class RegistrarMovimientoRequest(val tipo: String, val motivo: String, val cantidad: Double, val observacion: String? = null)

// ---- INVENTARIO / Productos (solo lectura) ----

@Serializable
data class ProductosKpis(val productos: Int = 0, val stock_total: Double = 0.0, val agotados: Int = 0)

@Serializable
data class ProductoFila(val id: Long, val nombre: String, val unidad_medida: String? = null, val cantidad_actual: Double, val sin_stock: Boolean = false)

@Serializable
data class ProductosListResponse(val kpis: ProductosKpis, val filas: List<ProductoFila>)

@Serializable
data class ProductoMovimiento(val fecha: String, val tipo: String, val cantidad: Double, val origen: String? = null)

@Serializable
data class ProductoDetalleResponse(
    val tipo: String,
    val id: Long,
    val nombre: String,
    val unidad_medida: String? = null,
    val cantidad_actual: Double,
    val estado: String? = null,
    val movimientos: List<ProductoMovimiento> = emptyList(),
)
