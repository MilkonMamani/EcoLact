package pe.edu.upeu.ecolactmobile.feature.acopiador

import kotlinx.serialization.ExperimentalSerializationApi
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonNames
import pe.edu.upeu.ecolactmobile.core.network.NullableStringMapOrEmptyArraySerializer

// ---- Registrar entrega ----

/**
 * [fecha_hora_evento]: hora REAL del acopio, capturada UNA vez al pulsar "Guardar entrega"
 * (ISO 8601 con desfase, ver fechaHoraEventoIso) y guardada en el payload de la cola: los
 * reintentos reenvían exactamente la misma. El servidor la guarda en `entregas.fecha_hora`; la
 * hora de llegada queda en `created_at` (`fecha_registro` en la respuesta). Las filas encoladas
 * por versiones anteriores la guardaban como `fecha_hora`: se siguen leyendo.
 */
@OptIn(ExperimentalSerializationApi::class)
@Serializable
data class AcopiadorEntregaRequest(
    val metodo_identificacion: String, // "QR" | "DNI"
    val identificador: String,
    val litros: Double,
    @JsonNames("fecha_hora") val fecha_hora_evento: String? = null,
    val observacion: String? = null,
)

/**
 * Fila de entrega tal como la devuelve la API del acopiador (listado, detalle, dashboard,
 * seguimiento y respuesta del POST). [control_calidad]: SIN_MUESTREO, SELECCIONADA, EN_ANALISIS,
 * ACEPTADA, OBSERVADA, RECHAZADA o ANULADA (ver ControlCalidadEntrega.php).
 */
@Serializable
data class AcopiadorEntrega(
    val id: Long,
    val proveedor_id: Long,
    val proveedor_nombre: String? = null,
    val proveedor_dni: String? = null,
    val acopiador_id: Long? = null,
    val zona_id: Long? = null,
    val zona_nombre: String? = null,
    val registrado_por_user_id: Long? = null,
    val fecha_hora: String,
    val fecha_registro: String? = null,
    val litros: Double,
    val metodo_identificacion: String,
    val estado: String,
    val observacion: String? = null,
    val resultado: String? = null,
    val muestreo_estado: String? = null,
    val control_calidad: String? = null,
)

// ---- Inicio / perfil ----

@Serializable
data class AcopiadorPerfil(
    val id: Long? = null,
    val nombres: String,
    val apellidos: String,
    val dni: String? = null,
    val telefono: String? = null,
    val estado: String? = null,
    val zona_id: Long? = null,
    val zona_nombre: String? = null,
) {
    val nombreCompleto: String get() = "$nombres $apellidos".trim()
}

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
    val leido: Boolean = false,
)

@Serializable
data class AcopiadorDashboard(
    val kpis: AcopiadorDashboardKpis = AcopiadorDashboardKpis(),
    val ultimas_entregas: List<AcopiadorEntrega> = emptyList(),
    @Serializable(with = NullableStringMapOrEmptyArraySerializer::class)
    val resultados_por_entrega: Map<String, String?> = emptyMap(),
    val proveedores_atendidos: List<ProveedorAtendido> = emptyList(),
    val comunicados_recientes: List<AcopiadorComunicado> = emptyList(),
    val acopiador: AcopiadorPerfil? = null,
)

// ---- Entregas ----

@Serializable
data class AcopiadorEntregasKpis(val entregas: Int = 0, val litros: Double = 0.0, val proveedores: Int = 0, val pendientes: Int = 0)

@Serializable
data class AcopiadorEntregasListResponse(val kpis: AcopiadorEntregasKpis = AcopiadorEntregasKpis(), val filas: List<AcopiadorEntrega>)

@Serializable
data class AcopiadorEntregaDetalleResponse(val entrega: AcopiadorEntrega, val analisis: String? = null)

// ---- Seguimiento de calidad (solo lectura) ----

@Serializable
data class SeguimientoListResponse(val filas: List<AcopiadorEntrega>)

@Serializable
data class SeguimientoAnalisis(
    val id: Long,
    val estado: String,
    val resultado: String? = null,
    val fecha_analisis: String? = null,
    val observacion: String? = null,
)

@Serializable
data class SeguimientoMuestreo(val estado: String, val fecha_seleccion: String? = null)

@Serializable
data class SeguimientoDetalleResponse(
    val entrega_id: Long,
    val estado: String,
    val control_calidad: String? = null,
    val muestreo: SeguimientoMuestreo? = null,
    val analisis: SeguimientoAnalisis? = null,
)

// ---- Proveedores de mi zona ----

/** Con [qr_token] el teléfono identifica al proveedor escaneando su QR sin conexión. */
@Serializable
data class AcopiadorProveedorFila(
    val id: Long,
    val dni: String,
    val nombres: String,
    val apellidos: String,
    val nombre_completo: String,
    val telefono: String? = null,
    val zona_id: Long? = null,
    val zona_nombre: String? = null,
    val estado: String,
    val qr_token: String? = null,
    val entregas: Int = 0,
    val ultima_entrega: String? = null,
) {
    val activo: Boolean get() = estado == "ACTIVO"
}

@Serializable
data class AcopiadorProveedoresListResponse(val filas: List<AcopiadorProveedorFila>)

@Serializable
data class AcopiadorProveedorResumen(
    val entregas_totales: Int = 0,
    val litros_entregados: Double = 0.0,
    val ultima_entrega: String? = null,
)

@Serializable
data class AcopiadorProveedorDetalleResponse(val resumen: AcopiadorProveedorResumen = AcopiadorProveedorResumen())

// ---- Recorridos de acopio ----

@Serializable
data class RecorridoResumen(
    val id: Long,
    val ruta_id: Long? = null,
    val fecha_operativa: String,
    val estado: String,
    val iniciado_en: String? = null,
    val finalizado_en: String? = null,
)

@Serializable
data class RecorridoProgreso(val recolectados: Int = 0, val pendientes: Int = 0, val no_entrego: Int = 0, val litros_validos: Double = 0.0)

@Serializable
data class ParadaRecorrido(
    val id: Long,
    val proveedor_id: Long,
    val proveedor_nombre: String,
    val proveedor_dni: String,
    val orden_visita: Int,
    val latitud: Double? = null,
    val longitud: Double? = null,
    val referencia_ubicacion: String? = null,
    val estado: String, // PENDIENTE | RECOLECTADO | NO_ENTREGO
    val observacion: String? = null,
    val litros_registrados: Double = 0.0,
)

@Serializable
data class RecorridoDetalle(
    val id: Long,
    val ruta_id: Long? = null,
    val ruta_nombre: String,
    val zona_nombre: String,
    val estado: String, // PENDIENTE | EN_PROGRESO | FINALIZADO | CANCELADO
    val fecha_operativa: String,
    val progreso: RecorridoProgreso = RecorridoProgreso(),
    val paradas: List<ParadaRecorrido> = emptyList(),
) {
    val abierto: Boolean get() = estado == "PENDIENTE" || estado == "EN_PROGRESO"
}

@Serializable
data class RegistrarAcopioParadaRequest(
    val litros: Double,
    /** Mismo contrato que [AcopiadorEntregaRequest.fecha_hora_evento]. */
    val fecha_hora_evento: String? = null,
    val observacion: String? = null,
    val token_idempotencia: String,
)

@Serializable
data class NoEntregoRequest(val motivo: String, val fecha_hora_evento: String? = null)

@Serializable
data class AcopioParadaRespuesta(val entrega_id: Long, val litros: Double)

@Serializable
data class ParadaEstadoRespuesta(val id: Long, val estado: String)

@Serializable
data class NoEntregoRespuesta(val parada: ParadaEstadoRespuesta)

// ---- Cola offline ----

/**
 * Lo que se guarda en `sync_queue` por cada entrega: la petición tal cual se enviará y los datos
 * del proveedor ya identificado, para mostrarla en el historial sin conexión. Si viene de una
 * parada de recorrido, [parada] dice dónde registrarla.
 */
@Serializable
data class EntregaLocalPayload(
    val request: AcopiadorEntregaRequest,
    val proveedor_id: Long? = null,
    val proveedor_nombre: String? = null,
    val proveedor_dni: String? = null,
    val zona_nombre: String? = null,
    val parada: ParadaRef? = null,
)

@Serializable
data class ParadaRef(val recorrido_id: Long, val parada_id: Long, val proveedor_id: Long)

@Serializable
data class NoEntregoPayload(val recorrido_id: Long, val parada_id: Long, val motivo: String, val fecha_hora_evento: String)

@Serializable
data class RecorridoRefPayload(val recorrido_id: Long, val fecha_hora_evento: String? = null)

@Serializable
data class ComunicadoRefPayload(val comunicado_id: Long)
