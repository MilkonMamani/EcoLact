package pe.edu.upeu.ecolactmobile.feature.acopiador

import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.network.NullableStringMapOrEmptyArraySerializer

// ---- Registrar entrega (única escritura offline de este rol) ----

@Serializable
data class AcopiadorEntregaRequest(
    val metodo_identificacion: String, // "QR" | "DNI"
    val identificador: String,
    val litros: Double,
    val fecha_hora: String? = null,
    val observacion: String? = null,
)

/** Usado tanto para la fila de un listado como para la respuesta del POST (algunos campos solo aplican a uno u otro). */
@Serializable
data class AcopiadorEntrega(
    val id: Long,
    val proveedor_id: Long,
    val acopiador_id: Long? = null,
    val zona_id: Long? = null,
    val registrado_por_user_id: Long? = null,
    val fecha_hora: String,
    val litros: Double,
    val metodo_identificacion: String,
    val estado: String,
    val observacion: String? = null,
    val resultado: String? = null,
)

// ---- INICIO ----

@Serializable
data class AcopiadorDashboardKpis(
    val entregas: Int = 0,
    val litros: Double = 0.0,
    val proveedoresAtendidos: Int = 0,
    val pendientesAnalisis: Int = 0,
)

@Serializable
data class ProveedorAtendido(val proveedorId: Long, val nombre: String, val entregas: Int = 0)

@Serializable
data class AcopiadorComunicado(
    val id: Long,
    val titulo: String,
    val contenido: String? = null,
    val tipo: String,
    val alcance: String? = null,
    val fecha_publicacion: String,
    val fecha_evento: String? = null,
    val estado: String? = null,
)

@Serializable
data class AcopiadorDashboard(
    val kpis: AcopiadorDashboardKpis,
    val ultimas_entregas: List<AcopiadorEntrega> = emptyList(),
    @Serializable(with = NullableStringMapOrEmptyArraySerializer::class)
    val resultados_por_entrega: Map<String, String?> = emptyMap(),
    val proveedores_atendidos: List<ProveedorAtendido> = emptyList(),
    val comunicados_recientes: List<AcopiadorComunicado> = emptyList(),
)

// ---- ENTREGAS ----

@Serializable
data class AcopiadorEntregasKpis(val entregas: Int = 0, val litros: Double = 0.0, val proveedores: Int = 0, val pendientes: Int = 0)

@Serializable
data class AcopiadorEntregasListResponse(val kpis: AcopiadorEntregasKpis, val filas: List<AcopiadorEntrega>)

@Serializable
data class AcopiadorEntregaDetalleEntrega(
    val id: Long,
    val proveedor_id: Long,
    val acopiador_id: Long? = null,
    val zona_id: Long? = null,
    val registrado_por_user_id: Long? = null,
    val fecha_hora: String,
    val litros: Double,
    val metodo_identificacion: String,
    val estado: String,
    val observacion: String? = null,
)

@Serializable
data class AcopiadorEntregaDetalleResponse(
    val entrega: AcopiadorEntregaDetalleEntrega,
    val analisis: String? = null,
)

// ---- SEGUIMIENTO DE CALIDAD (solo lectura) ----

@Serializable
data class SeguimientoKpis(val pendientes: Int = 0, val analizadas: Int = 0, val aceptadas: Int = 0, val con_observaciones: Int = 0)

@Serializable
data class SeguimientoFila(
    val id: Long,
    val proveedor_id: Long,
    val fecha_hora: String,
    val litros: Double,
    val estado: String,
    val resultado: String? = null,
)

@Serializable
data class SeguimientoListResponse(val kpis: SeguimientoKpis, val filas: List<SeguimientoFila>)

@Serializable
data class SeguimientoAnalisis(
    val id: Long,
    val estado: String,
    val resultado: String? = null,
    val fecha_analisis: String? = null,
    val observacion: String? = null,
)

@Serializable
data class SeguimientoDetalleResponse(val entrega_id: Long, val estado: String, val analisis: SeguimientoAnalisis? = null)

// ---- MÁS / Proveedores de mi zona ----

@Serializable
data class AcopiadorProveedoresKpis(val proveedores: Int = 0, val activos: Int = 0, val inactivos: Int = 0)

@Serializable
data class AcopiadorProveedorFila(
    val id: Long,
    val dni: String,
    val nombres: String,
    val apellidos: String,
    val nombre_completo: String,
    val telefono: String? = null,
    val zona_id: Long? = null,
    val estado: String,
    val entregas: Int = 0,
    val ultima_entrega: String? = null,
)

@Serializable
data class AcopiadorProveedoresListResponse(val kpis: AcopiadorProveedoresKpis, val filas: List<AcopiadorProveedorFila>)

@Serializable
data class AcopiadorProveedorInfo(
    val id: Long,
    val dni: String,
    val nombres: String,
    val apellidos: String,
    val nombre_completo: String,
    val telefono: String? = null,
    val zona_id: Long? = null,
    val estado: String,
)

@Serializable
data class AcopiadorProveedorResumen(
    val entregas_totales: Int = 0,
    val litros_entregados: Double = 0.0,
    val ultima_entrega: String? = null,
    val pendientes_analisis: Int = 0,
)

@Serializable
data class AcopiadorProveedorDetalleResponse(
    val proveedor: AcopiadorProveedorInfo,
    val resumen: AcopiadorProveedorResumen,
    val ultimo_analisis_resultado: String? = null,
)
