package pe.edu.upeu.ecolactmobile.feature.planta

import kotlinx.serialization.Serializable

// Contratos de Api\Planta (EcoLactWeb/routes/api.php, prefijo planta/). La fuente de verdad de
// recetas, precios, stock y requerimientos es siempre el servidor: el teléfono solo guarda la
// última copia (CacheRepository) para trabajar sin conexión.

// ---- Perfil / Mi QR ----

/** GET planta/perfil: se guarda en el teléfono para mostrar "Mi QR" sin conexión. */
@Serializable
data class PlantaPerfil(
    val user_id: Long,
    val username: String? = null,
    val nombres: String? = null,
    val apellidos: String? = null,
    val dni: String? = null,
    val telefono: String? = null,
    val rol: String = "Encargado de planta",
    val qr_token: String? = null,
)

// ---- Insumos ----

@Serializable
data class InsumosKpis(val insumos: Int = 0, val stock_bajo: Int = 0, val con_stock: Int = 0, val sin_stock: Int = 0)

@Serializable
data class InsumoFila(
    val id: Long,
    val nombre: String,
    val unidad_medida: String? = null,
    val unidad_base: String? = null,
    val codigo: String? = null,
    val stock_actual: Double,
    val stock_minimo: Double? = null,
    val estado: String? = null,
    val esta_bajo_minimo: Boolean = false,
    val descripcion: String? = null,
    val es_leche: Boolean = false,
) {
    val unidad: String get() = unidad_base ?: unidad_medida.orEmpty()

    /** La leche no se compra ni se ajusta a mano: sube con Acopio y baja con Producción. */
    val esLeche: Boolean get() = es_leche || codigo == "LECHE"
}

@Serializable
data class InsumosListResponse(val kpis: InsumosKpis = InsumosKpis(), val filas: List<InsumoFila> = emptyList())

/** Presentación de compra configurada por el administrador: "Saco 25 kg" = 25 000 g. */
@Serializable
data class PresentacionInsumo(
    val id: Long,
    val nombre: String,
    val etiqueta: String,
    val cantidad: Double = 0.0,
    val unidad: String = "",
    val cantidad_base: Double,
)

@Serializable
data class MovimientoInsumo(
    val id: Long,
    val tipo: String,
    val motivo: String? = null,
    val cantidad: Double,
    val fecha: String? = null,
    val observacion: String? = null,
    val origen_tipo: String? = null,
    val origen_id: Long? = null,
)

@Serializable
data class InsumoDetalleResponse(
    val insumo: InsumoFila,
    val presentaciones: List<PresentacionInsumo> = emptyList(),
    val movimientos: List<MovimientoInsumo> = emptyList(),
)

// ---- Productos (solo lectura) ----

@Serializable
data class ProductosKpis(val productos: Int = 0, val stock_total: Double = 0.0, val agotados: Int = 0)

@Serializable
data class ProductoFila(val id: Long, val nombre: String, val unidad_medida: String? = null, val cantidad_actual: Double, val sin_stock: Boolean = false)

@Serializable
data class ProductosListResponse(val kpis: ProductosKpis = ProductosKpis(), val filas: List<ProductoFila> = emptyList())

/** Precio vigente configurado por el ADMINISTRADOR. En la app es solo información. */
@Serializable
data class PrecioVigente(val precio: Double, val cantidad_minima: Double? = null, val desde: String? = null)

@Serializable
data class PreciosProducto(val publico: PrecioVigente? = null, val mayorista: PrecioVigente? = null, val proveedor: PrecioVigente? = null) {
    val sinConfigurar: Boolean get() = publico == null && mayorista == null && proveedor == null
}

@Serializable
data class UltimaProduccion(val lote_id: Long, val codigo: String, val fecha: String? = null, val estado: String? = null)

@Serializable
data class RecetaProducto(val id: Long, val nombre: String? = null, val version: Int = 1, val estado: String = "ACTIVA")

@Serializable
data class ProductoDetalleResponse(
    val id: Long,
    val nombre: String,
    val nombre_base: String? = null,
    val presentacion: String? = null,
    val unidad_medida: String? = null,
    val cantidad_actual: Double,
    val estado: String? = null,
    val precios: PreciosProducto = PreciosProducto(),
    val ultima_produccion: UltimaProduccion? = null,
    val receta: RecetaProducto? = null,
)

// ---- Recetas (solo lectura) ----

@Serializable
data class IngredienteReceta(val insumoId: Long, val insumo: String, val cantidad: Double, val unidad: String, val observacion: String? = null)

/** Lo que necesita UNA unidad del producto, ya en la unidad base de cada insumo (calculado por el servidor). */
@Serializable
data class InsumoPorUnidad(val insumo_id: Long, val nombre: String, val unidad: String, val cantidad: Double, val es_leche: Boolean = false)

@Serializable
data class RecetaFila(
    val id: Long,
    val productoId: Long,
    val producto: String,
    val productoNombreBase: String? = null,
    val presentacion: String? = null,
    val nombre: String = "",
    val version: Int = 1,
    val rendimientoCantidad: Double = 1.0,
    val rendimientoUnidad: String = "unidad",
    val estado: String = "ACTIVA",
    val actualizada: String? = null,
    val ingredientes: List<IngredienteReceta> = emptyList(),
    val por_unidad: List<InsumoPorUnidad> = emptyList(),
) {
    val activa: Boolean get() = estado == "ACTIVA"

    /** "Queso Paria" (sin la presentación, que se muestra aparte). */
    val nombreProducto: String get() = productoNombreBase ?: producto
}

@Serializable
data class RecetasListResponse(val filas: List<RecetaFila> = emptyList())

// ---- Producción ----

@Serializable
data class ProduccionKpis(val enProceso: Int = 0, val finalizados: Int = 0)

@Serializable
data class ProduccionFila(
    val id: Long,
    val codigo: String,
    val inicio: String? = null,
    val fin: String? = null,
    val litros: Double = 0.0,
    val productos: Int = 0,
    val productosNombres: List<String> = emptyList(),
    val unidadObtenido: String? = null,
    val obtenido: Double? = null,
    val planificado: Double = 0.0,
    val estado: String,
)

@Serializable
data class ProduccionListResponse(val kpis: ProduccionKpis = ProduccionKpis(), val filas: List<ProduccionFila> = emptyList())

@Serializable
data class LineaProduccion(val producto_id: Long, val cantidad: Double)

/**
 * Cuerpo de POST planta/produccion y planta/produccion/requerimientos. [fecha_hora_evento]: hora real
 * en que se inició en el teléfono; el servidor evalúa la asistencia de ese día.
 */
@Serializable
data class ProduccionRequest(val productos: List<LineaProduccion>, val observacion: String? = null, val fecha_hora_evento: String? = null)

@Serializable
data class RequerimientoInsumo(
    val insumo_id: Long,
    val nombre: String,
    val unidad: String,
    val necesario: Double,
    val disponible: Double,
    val faltante: Double = 0.0,
    val suficiente: Boolean = true,
    val es_leche: Boolean = false,
)

@Serializable
data class RequerimientosResponse(
    val insumos: List<RequerimientoInsumo> = emptyList(),
    val puede_iniciar: Boolean = false,
    val mensajes: List<String> = emptyList(),
    val resumen: String? = null,
)

@Serializable
data class LoteCreado(val id: Long, val codigo: String, val estado: String)

@Serializable
data class LoteProducto(
    val detalle_id: Long,
    val producto_id: Long,
    val producto_nombre: String? = null,
    val unidad_medida: String? = null,
    val cantidad_planificada: Double = 0.0,
    val cantidad_obtenida: Double? = null,
)

@Serializable
data class LoteInsumo(
    val insumo_id: Long,
    val insumo_nombre: String? = null,
    val unidad_base: String? = null,
    val cantidad_requerida: Double = 0.0,
    val cantidad_usada: Double = 0.0,
)

@Serializable
data class LoteDetalle(
    val id: Long,
    val codigo: String,
    val estado: String,
    val fecha_hora_inicio: String? = null,
    val fecha_hora_fin: String? = null,
    val litros_leche_utilizados: Double = 0.0,
    val observacion: String? = null,
    val productos: List<LoteProducto> = emptyList(),
    val insumos_utilizados: List<LoteInsumo> = emptyList(),
) {
    val enProceso: Boolean get() = estado == "EN_PROCESO"
}

@Serializable
data class LoteDetalleResponse(val lote: LoteDetalle)

/** `obtenido` indexado por el id del DETALLE de producción (no del producto). Puede ser 0. */
@Serializable
data class FinalizarProduccionRequest(val obtenido: Map<String, Double>, val observacion: String? = null, val fecha_hora_evento: String? = null)

// ---- Compras de insumos ----

@Serializable
data class CatalogoPresentacion(val id: Long, val nombre: String, val etiqueta: String, val cantidad_base: Double)

/** Insumo comprable (activo, nunca la leche) con sus presentaciones activas. */
@Serializable
data class CatalogoInsumo(
    val id: Long,
    val nombre: String,
    val unidad_base: String,
    val stock_actual: Double = 0.0,
    val presentaciones: List<CatalogoPresentacion> = emptyList(),
)

/** Una línea que el OCR leyó de la boleta ("2 SACOS SAL"): solo una PROPUESTA. */
@Serializable
data class BoletaLineaOcr(
    val texto: String? = null,
    val cantidad: Double = 0.0,
    val presentacion_detectada: String? = null,
    val descripcion: String? = null,
    val insumo_id: Long? = null,
    val insumo: String? = null,
    val presentacion_id: Long? = null,
    val opciones_presentacion: List<Long> = emptyList(),
    val requiere_confirmacion_presentacion: Boolean = false,
    val estado: String? = null,
)

@Serializable
data class BoletaCabecera(
    val proveedor: String? = null,
    val ruc: String? = null,
    val numero_comprobante: String? = null,
    val fecha: String? = null,
    val total: Double? = null,
)

/** POST planta/compras-insumo/boleta (sin sobre `data`). 422 + `success=false` = boleta ilegible, foto guardada. */
@Serializable
data class BoletaOcrResponse(
    val success: Boolean = false,
    val mensaje: String? = null,
    val lineas: List<BoletaLineaOcr> = emptyList(),
    val cabecera: BoletaCabecera? = null,
    val imagen_boleta: String? = null,
)

@Serializable
data class CompraLineaRequest(val insumo_id: Long, val presentacion_id: Long, val cantidad: Double)

@Serializable
data class CompraRequest(
    val lineas: List<CompraLineaRequest>,
    val proveedor_comercial: String? = null,
    val numero_comprobante: String? = null,
    val fecha: String? = null,
    val total: Double? = null,
    val imagen_boleta: String? = null,
    val fecha_hora_evento: String? = null,
)

@Serializable
data class CompraRegistrada(val id: Long, val referencia: String? = null, val total: Double? = null)

@Serializable
data class CompraRecienteLinea(
    val insumo_id: Long,
    val insumo: String? = null,
    val presentacion: String? = null,
    val cantidad: Double = 0.0,
    val cantidad_base: Double = 0.0,
    val unidad_base: String? = null,
)

@Serializable
data class CompraReciente(
    val id: Long,
    val origen: String? = null,
    val fecha: String? = null,
    val registrada: String? = null,
    val proveedor_comercial: String? = null,
    val numero_comprobante: String? = null,
    val total: Double? = null,
    val lineas: List<CompraRecienteLinea> = emptyList(),
)
