package pe.edu.upeu.ecolactmobile.feature.calidad

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import app.cash.sqldelight.coroutines.mapToOneOrNull
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import pe.edu.upeu.ecolactmobile.core.database.Calidad_captura
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.util.nowIso

/** Foto del ticket + lo que propuso el OCR, guardado en el teléfono hasta confirmar el análisis. */
class CapturaLocal(
    val entregaId: Long,
    val muestreoId: Long?,
    val foto: ByteArray?,
    val fotoMime: String?,
    val ticketPath: String?,
    val estado: EstadoCaptura,
    /** Solo lo DETECTADO (código → valor). Lo que falta se muestra "No detectado", nunca 0. */
    val valoresDetectados: Map<String, Double>,
    val mensajeOcr: String?,
    val fechaCapturaLocal: String,
    val fechaSincronizacion: String?,
)

/** Tabla `calidad_captura` (misma SQLite de la app, ver CalidadCaptura.sq). */
class CalidadCapturaStore(database: EcoLactDatabase) {
    private val queries = database.calidadCapturaQueries

    /** Foto tomada sin conexión: queda PENDIENTE_OCR hasta que el servidor la lea. */
    fun guardarPendienteOcr(entregaId: Long, muestreoId: Long?, foto: ByteArray, mime: String, fechaCapturaLocal: String) {
        queries.upsertCaptura(
            entrega_id = entregaId,
            muestreo_id = muestreoId,
            foto = foto,
            foto_mime = mime,
            ticket_path = null,
            estado = EstadoCaptura.PENDIENTE_OCR.name,
            valores_json = null,
            mensaje_ocr = null,
            fecha_captura_local = fechaCapturaLocal,
            fecha_sincronizacion = null,
            updated_at = nowIso(),
        )
    }

    /** Foto leída en línea: directo a REQUIERE_REVISION (el analista revisa antes de confirmar). */
    fun guardarLeida(
        entregaId: Long,
        muestreoId: Long?,
        foto: ByteArray,
        mime: String,
        ocr: TicketOcrResponse,
        fechaCapturaLocal: String,
    ) {
        val ahora = nowIso()
        queries.upsertCaptura(
            entrega_id = entregaId,
            muestreo_id = muestreoId,
            foto = foto,
            foto_mime = mime,
            ticket_path = ocr.ticket_path,
            estado = EstadoCaptura.REQUIERE_REVISION.name,
            valores_json = ApiJson.encodeToString(ocr.valores.associate { it.codigo to it.valor }),
            mensaje_ocr = ocr.mensaje,
            fecha_captura_local = fechaCapturaLocal,
            fecha_sincronizacion = ahora,
            updated_at = ahora,
        )
    }

    /**
     * Resultado del OCR remoto de una foto tomada sin conexión. Solo actúa sobre una captura
     * todavía PENDIENTE_OCR y la deja en REQUIERE_REVISION: nunca la confirma.
     */
    fun marcarOcrProcesado(entregaId: Long, ticketPath: String?, valores: Map<String, Double>, mensaje: String?) {
        val ahora = nowIso()
        queries.marcarOcrProcesado(ticketPath, ApiJson.encodeToString(valores), mensaje, ahora, ahora, entregaId)
    }

    fun obtener(entregaId: Long): CapturaLocal? = queries.selectCaptura(entregaId).executeAsOneOrNull()?.toCaptura()

    fun observe(entregaId: Long): Flow<CapturaLocal?> =
        queries.selectCaptura(entregaId).asFlow().mapToOneOrNull(Dispatchers.Default).map { it?.toCaptura() }

    fun observeEstados(): Flow<Map<Long, EstadoCaptura>> =
        queries.selectCapturasResumen().asFlow().mapToList(Dispatchers.Default).map { filas ->
            filas.mapNotNull { f -> EstadoCaptura.from(f.estado)?.let { f.entrega_id to it } }.toMap()
        }

    fun eliminar(entregaId: Long) = queries.deleteCaptura(entregaId)

    private fun Calidad_captura.toCaptura() = CapturaLocal(
        entregaId = entrega_id,
        muestreoId = muestreo_id,
        foto = foto,
        fotoMime = foto_mime,
        ticketPath = ticket_path,
        estado = EstadoCaptura.from(estado) ?: EstadoCaptura.PENDIENTE_OCR,
        valoresDetectados = valores_json?.let { runCatching { ApiJson.decodeFromString<Map<String, Double>>(it) }.getOrNull() } ?: emptyMap(),
        mensajeOcr = mensaje_ocr,
        fechaCapturaLocal = fecha_captura_local,
        fechaSincronizacion = fecha_sincronizacion,
    )
}
