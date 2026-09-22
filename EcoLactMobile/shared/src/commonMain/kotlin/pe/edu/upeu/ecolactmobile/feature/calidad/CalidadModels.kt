package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.serialization.Serializable

// ---- NUEVO (Etapa 3): Registrar entrega por muestreo directo ----
//
// `POST /calidad/entregas` (App\Application\Calidad\RegistrarEntregaMuestreada) — caso acotado:
// el analista toma una muestra directamente del proveedor, ANTES de que pase por el acopiador,
// cuando anticipa que el resultado va a ser RECHAZADA (si va a ser ACEPTADA/OBSERVADA, avisa
// verbalmente al acopiador de siempre — este endpoint NO es un "acopio general" para CALIDAD).
// `acopiador_id` siempre sale null en la respuesta: nunca hubo acopiador involucrado. Es la
// pantalla concreta que materializa "el analista también puede acopiar" en el negocio real.

@Serializable
data class CalidadEntregaMuestreadaRequest(
    val metodo_identificacion: String, // "QR" | "DNI"
    val identificador: String,
    val litros: Double,
    val observacion: String? = null,
)

@Serializable
data class CalidadEntregaMuestreada(
    val id: Long,
    val proveedor_id: Long,
    val acopiador_id: Long? = null,
    val zona_id: Long? = null,
    val fecha_hora: String,
    val litros: Double,
    val metodo_identificacion: String,
    val estado: String,
    val observacion: String? = null,
)

// ---- INICIO ----

@Serializable
data class KpisPeriodo(val analizados: Int = 0, val aceptados: Int = 0, val observados: Int = 0, val rechazados: Int = 0)

@Serializable
data class PendienteFila(
    val id: Long,
    val fecha: String,
    val proveedorNombre: String? = null,
    val proveedorDni: String? = null,
    val zonaNombre: String? = null,
    val litros: Double,
)

@Serializable
data class UltimoAnalisisFila(
    val id: Long,
    val fecha: String,
    val proveedorNombre: String? = null,
    val proveedorDni: String? = null,
    val entregaId: Long,
    val zonaNombre: String? = null,
    val resultado: String? = null,
    val estado: String,
)

@Serializable
data class CalidadComunicadoResumen(val id: Long, val titulo: String, val tipo: String, val fecha_publicacion: String)

@Serializable
data class CalidadDashboard(
    val pendientes_analisis: Int = 0,
    val kpis_periodo: KpisPeriodo,
    val pendientes_filas: List<PendienteFila> = emptyList(),
    val ultimos_analisis_filas: List<UltimoAnalisisFila> = emptyList(),
    val comunicados_recientes: List<CalidadComunicadoResumen> = emptyList(),
)

// ---- PENDIENTES / HISTORIAL (mismo endpoint, `tab=`) ----

@Serializable
data class ZonaOption(val id: Long, val nombre: String)

@Serializable
data class PendientesKpis(val litrosRegistrados: Double = 0.0, val entregas: Int = 0, val pendientesAnalisis: Int = 0, val analizadas: Int = 0)

@Serializable
data class PendienteEntregaFila(
    val id: Long,
    val fecha: String,
    val proveedorNombre: String? = null,
    val proveedorDni: String? = null,
    val zonaNombre: String? = null,
    val acopiadorNombre: String? = null,
    val litros: Double,
    val metodoIdentificacion: String? = null,
    val estado: String,
    val resultado: String? = null,
)

@Serializable
data class PendientesListResponse(
    val tab: String,
    val kpis: PendientesKpis,
    val zonas: List<ZonaOption> = emptyList(),
    val filas: List<PendienteEntregaFila>,
)

@Serializable
data class HistorialKpis(val total: Int = 0, val aceptadas: Int = 0, val observadas: Int = 0, val rechazadas: Int = 0)

@Serializable
data class HistorialFila(
    val id: Long,
    val fecha: String,
    val proveedorNombre: String? = null,
    val proveedorDni: String? = null,
    val entregaId: Long,
    val zonaNombre: String? = null,
    val resultado: String,
    val estado: String,
    val confirmadoPor: String? = null,
)

@Serializable
data class HistorialListResponse(
    val tab: String,
    val kpis: HistorialKpis,
    val zonas: List<ZonaOption> = emptyList(),
    val filas: List<HistorialFila>,
)

// ---- Detalle previo de una entrega (antes de analizar / continuar borrador) ----

@Serializable
data class AnalisisBorradorInfo(
    val id: Long,
    val entrega_id: Long,
    val imagen_path: String? = null,
    val estado: String,
    val resultado: String? = null,
    val fecha_analisis: String? = null,
    val confirmado_por_user_id: Long? = null,
    val observacion: String? = null,
)

@Serializable
data class ParametroActivo(val id: Long, val codigo: String, val nombre: String, val unidad: String? = null)

@Serializable
data class AnalisisPrevioResponse(
    val analisis: AnalisisBorradorInfo? = null,
    val parametros: List<ParametroActivo> = emptyList(),
)

// ---- Detalle completo (historial) ----

@Serializable
data class AnalisisEntregaRef(
    val id: Long,
    val litros: Double,
    val fecha_hora: String? = null,
    val proveedor_nombre: String? = null,
    val zona_nombre: String? = null,
)

@Serializable
data class AnalisisDetalleParametro(
    val parametro_id: Long,
    val parametro_codigo: String,
    val parametro_nombre: String,
    val unidad: String? = null,
    val valor: Double,
    val origen: String,
    val confianza_ocr: Double? = null,
    val cumple: Boolean? = null,
)

@Serializable
data class AnalisisDetalleResponse(
    val id: Long,
    val entrega_id: Long,
    val imagen_path: String? = null,
    val estado: String,
    val resultado: String? = null,
    val fecha_analisis: String? = null,
    val entrega: AnalisisEntregaRef? = null,
    val detalles: List<AnalisisDetalleParametro> = emptyList(),
    val observacion: String? = null,
)

// ---- MÁS / Proveedores ----

@Serializable
data class CalidadProveedorFila(
    val id: Long,
    val nombre: String,
    val dni: String,
    val zonaNombre: String? = null,
    val telefono: String? = null,
    val advertencias: Int = 0,
    val estado: String,
)

@Serializable
data class CalidadProveedoresListResponse(val filas: List<CalidadProveedorFila>)

@Serializable
data class CalidadProveedorInfo(
    val id: Long,
    val dni: String,
    val nombre_completo: String,
    val zona_id: Long? = null,
    val estado: String,
    val advertencias_count: Int = 0,
)

@Serializable
data class CalidadProveedorEntregaResumen(val id: Long, val fecha_hora: String, val litros: Double, val estado: String)

@Serializable
data class CalidadProveedorAnalisisResumen(val id: Long, val entrega_id: Long, val resultado: String, val fecha_analisis: String)

@Serializable
data class CalidadProveedorDetalleResponse(
    val proveedor: CalidadProveedorInfo,
    val entregas: List<CalidadProveedorEntregaResumen> = emptyList(),
    val analisis: List<CalidadProveedorAnalisisResumen> = emptyList(),
)

// ---- Comunicados ----

@Serializable
data class CalidadComunicado(
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
data class CrearComunicadoRequest(
    val titulo: String,
    val contenido: String,
    val tipo: String,
    val alcance: String, // "INDIVIDUAL" | "ZONA" | "GENERAL"
    val destinatarios: List<Long>? = null,
    val zona_id: Long? = null,
    val fecha_evento: String? = null,
    val estado: String? = null,
    val fecha_publicacion: String? = null,
)
