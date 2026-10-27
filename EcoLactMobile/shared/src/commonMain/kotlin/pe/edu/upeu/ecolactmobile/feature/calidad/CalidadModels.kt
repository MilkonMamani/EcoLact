package pe.edu.upeu.ecolactmobile.feature.calidad

import kotlinx.serialization.Serializable

// ---- MUESTREO DIRECTO ----
//
// `POST /calidad/entregas` (App\Application\Calidad\RegistrarEntregaMuestreada): el analista
// llega al proveedor, lo identifica (QR/DNI) y toma la muestra antes del acopio normal
// (inspección sorpresa). El servidor crea la entrega de la muestra y su muestreo DIRECTA, que
// aparece de inmediato en "Muestras pendientes". `acopiador_id` sale null: no hubo acopiador.

@Serializable
data class CalidadEntregaMuestreadaRequest(
    val metodo_identificacion: String, // "QR" | "DNI"
    val identificador: String,
    val litros: Double,
    val observacion: String? = null,
    /** Hora real del muestreo en campo (se fija al guardar y viaja igual en cada reintento). */
    val fecha_hora_evento: String? = null,
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
    val litros: Double? = null,
    /** Misma fecha que [fecha] pero en ISO, para mostrarla en hora local. */
    val fechaIso: String? = null,
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

/** Regla vigente de un parámetro: solo para PREVISUALIZAR el resultado; el servidor lo recalcula al confirmar. */
@Serializable
data class ReglaVigente(
    val codigo: String,
    val valor_minimo: Double? = null,
    val valor_maximo: Double? = null,
    val resultado_incumplimiento: String,
)

@Serializable
data class AnalisisPrevioResponse(
    val analisis: AnalisisBorradorInfo? = null,
    val parametros: List<ParametroActivo> = emptyList(),
    val reglas: List<ReglaVigente> = emptyList(),
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

// ---- MUESTREO (flujo principal): Calidad solo analiza las entregas que selecciona ----

/** Entrega registrada por el acopiador que todavía puede entrar a control de calidad. */
@Serializable
data class EntregaElegible(
    val entregaId: Long,
    val proveedorNombre: String,
    val proveedorDni: String? = null,
    val zonaNombre: String? = null,
    val acopiadorNombre: String? = null,
    val litros: Double,
    val fechaHora: String,
    /** Hasta cuándo el acopiador puede corregir litros (10 min). Solo informativo: NO limita a Calidad. */
    val litrosEditablesHasta: String? = null,
    val entregaEstado: String? = null,
)

@Serializable
data class ElegiblesResponse(val filas: List<EntregaElegible> = emptyList())

/** Muestra seleccionada (SELECCIONADA / EN_ANALISIS / ANALIZADA). */
@Serializable
data class MuestraCalidad(
    val id: Long,
    val entregaId: Long,
    val proveedorNombre: String,
    val proveedorDni: String? = null,
    val zonaNombre: String? = null,
    val acopiadorNombre: String? = null,
    val litros: Double,
    val fechaHora: String,
    val litrosEditablesHasta: String? = null,
    val entregaEstado: String? = null,
    val metodoSeleccion: String,
    val tipoControl: String? = null,
    val fechaSeleccion: String,
    val fechaCapturaLocal: String? = null,
    val estado: String,
    val observacion: String? = null,
)

@Serializable
data class MuestrasKpis(val pendientes: Int = 0, val analizadas_hoy: Int = 0)

@Serializable
data class AnalistaNombre(val nombres: String, val apellidos: String)

@Serializable
data class MuestrasResponse(
    val kpis: MuestrasKpis = MuestrasKpis(),
    val filas: List<MuestraCalidad> = emptyList(),
    val analista: AnalistaNombre? = null,
)

/** Sorteo hecho por el servidor; [propuesta_token] prueba que la muestra fue realmente aleatoria. */
@Serializable
data class PropuestaAleatoria(val entrega: EntregaElegible, val propuesta_token: String)

@Serializable
data class SeleccionarMuestraRequest(
    val entrega_id: Long,
    val metodo_seleccion: String, // "ALEATORIA" | "MANUAL"
    val tipo_control: String? = null, // "RUTINARIO" | "DIRIGIDO"
    val propuesta_token: String? = null,
    /** Hora real en el teléfono (puede sincronizarse mucho después). */
    val fecha_seleccion: String? = null,
    val observacion: String? = null,
)

// ---- OCR del ticket (POST /calidad/analisis-calidad/escanear-ticket) ----

@Serializable
data class TicketValorDetectado(val parametro_id: Long, val codigo: String, val nombre: String, val valor: Double, val unidad: String? = null)

@Serializable
data class TicketParametroNoDetectado(val codigo: String, val nombre: String)

/** `success=false` con `ticket_path`: la foto se guardó pero no se pudo leer (el analista ingresa a mano). */
@Serializable
data class TicketOcrResponse(
    val success: Boolean,
    val ticket_path: String? = null,
    val valores: List<TicketValorDetectado> = emptyList(),
    val no_detectados: List<TicketParametroNoDetectado> = emptyList(),
    val suficientes: Boolean = false,
    val mensaje: String? = null,
)
