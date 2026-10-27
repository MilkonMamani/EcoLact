package pe.edu.upeu.ecolactmobile.feature.planta

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import app.cash.sqldelight.coroutines.mapToOneOrNull
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase
import pe.edu.upeu.ecolactmobile.core.database.Planta_compra_borrador
import pe.edu.upeu.ecolactmobile.core.network.ApiJson
import pe.edu.upeu.ecolactmobile.core.util.nowIso

/**
 * Ciclo de una compra en el teléfono. El OCR nunca confirma: de PENDIENTE_OCR se pasa a
 * REQUIERE_REVISION y solo la persona la lleva a PENDIENTE_SYNC con "Confirmar compra".
 */
enum class EstadoBorrador(val label: String) {
    PENDIENTE_OCR("Foto guardada · se leerá con conexión"),
    REQUIERE_REVISION("Por revisar"),
    PENDIENTE_SYNC("Pendiente de envío"),
    ;

    companion object {
        fun from(valor: String?): EstadoBorrador = entries.firstOrNull { it.name == valor } ?: REQUIERE_REVISION
    }
}

enum class OrigenCompra { BOLETA, MANUAL }

/** Una línea de la compra: lo que dijo la boleta ([cantidad] + [unidadOcr]) y la presentación elegida. */
@Serializable
data class LineaBorrador(
    val textoOcr: String? = null,
    val unidadOcr: String? = null,
    val insumoId: Long? = null,
    val presentacionId: Long? = null,
    val cantidad: Double? = null,
) {
    val completa: Boolean get() = insumoId != null && presentacionId != null && (cantidad ?: 0.0) > 0.0
}

@Serializable
data class BorradorCompra(
    val proveedor: String? = null,
    val numeroComprobante: String? = null,
    val fecha: String? = null,
    val total: Double? = null,
    val lineas: List<LineaBorrador> = emptyList(),
) {
    val listaParaConfirmar: Boolean get() = lineas.isNotEmpty() && lineas.all { it.completa }

    fun toRequest(imagenBoleta: String?, fechaHoraEvento: String? = null): CompraRequest = CompraRequest(
        lineas = lineas.filter { it.completa }.map { CompraLineaRequest(it.insumoId!!, it.presentacionId!!, it.cantidad!!) },
        proveedor_comercial = proveedor?.takeIf { it.isNotBlank() },
        numero_comprobante = numeroComprobante?.takeIf { it.isNotBlank() },
        fecha = fecha?.takeIf { it.isNotBlank() },
        total = total,
        imagen_boleta = imagenBoleta,
        fecha_hora_evento = fechaHoraEvento,
    )

    companion object {
        /**
         * Propuesta del OCR → borrador editable. La boleta dice "2 sacos" (sin el peso): el servidor
         * ya cruzó ese texto con las presentaciones configuradas; si no pudo decidir, la línea queda
         * sin presentación para que la persona la elija.
         */
        fun desdeOcr(ocr: BoletaOcrResponse): BorradorCompra = BorradorCompra(
            proveedor = ocr.cabecera?.proveedor,
            numeroComprobante = ocr.cabecera?.numero_comprobante,
            fecha = ocr.cabecera?.fecha,
            total = ocr.cabecera?.total,
            lineas = ocr.lineas.map { l ->
                LineaBorrador(
                    textoOcr = l.texto ?: l.descripcion,
                    unidadOcr = l.presentacion_detectada?.lowercase(),
                    insumoId = l.insumo_id,
                    presentacionId = l.presentacion_id,
                    cantidad = l.cantidad.takeIf { it > 0.0 },
                )
            },
        )
    }
}

class BorradorLocal(
    val id: String,
    val origen: OrigenCompra,
    val estado: EstadoBorrador,
    val foto: ByteArray?,
    val fotoMime: String?,
    val imagenBoleta: String?,
    val datos: BorradorCompra,
    val mensaje: String?,
    val creadoEn: String,
)

data class BorradorResumen(val id: String, val origen: OrigenCompra, val estado: EstadoBorrador, val datos: BorradorCompra, val mensaje: String?, val creadoEn: String)

/** Tabla `planta_compra_borrador` (misma SQLite de la app, ver PlantaCompraBorrador.sq). */
class PlantaCompraStore(database: EcoLactDatabase) {
    private val queries = database.plantaCompraBorradorQueries

    fun crear(id: String, origen: OrigenCompra, estado: EstadoBorrador, foto: ByteArray?, mime: String?, datos: BorradorCompra, imagenBoleta: String? = null, mensaje: String? = null) {
        val ahora = nowIso()
        queries.insertBorrador(id, origen.name, estado.name, foto, mime, imagenBoleta, ApiJson.encodeToString(datos), mensaje, ahora, ahora)
    }

    fun guardar(id: String, datos: BorradorCompra, estado: EstadoBorrador, mensaje: String? = null) =
        queries.guardarDatos(ApiJson.encodeToString(datos), estado.name, mensaje, nowIso(), id)

    /**
     * Resultado del OCR de una foto tomada sin conexión. La ruta de la foto siempre se guarda; las
     * líneas propuestas solo reemplazan las del borrador si todavía estaba PENDIENTE_OCR (si la
     * persona ya las completó a mano, no se pisa su trabajo). Nunca confirma la compra.
     */
    fun marcarOcrProcesado(id: String, imagenBoleta: String?, propuesta: BorradorCompra, mensaje: String?) {
        queries.transaction {
            queries.guardarImagenBoleta(imagenBoleta, nowIso(), id)
            queries.marcarOcrProcesado(imagenBoleta, ApiJson.encodeToString(propuesta), mensaje, nowIso(), id)
        }
    }

    fun obtener(id: String): BorradorLocal? = queries.selectBorrador(id).executeAsOneOrNull()?.toLocal()

    fun observe(id: String): Flow<BorradorLocal?> =
        queries.selectBorrador(id).asFlow().mapToOneOrNull(Dispatchers.Default).map { it?.toLocal() }

    fun observeResumen(): Flow<List<BorradorResumen>> =
        queries.selectResumen().asFlow().mapToList(Dispatchers.Default).map { filas ->
            filas.map { f -> BorradorResumen(f.id, origenDe(f.origen), EstadoBorrador.from(f.estado), datosDe(f.datos_json), f.mensaje, f.created_at) }
        }

    fun eliminar(id: String) = queries.deleteBorrador(id)

    private fun Planta_compra_borrador.toLocal() = BorradorLocal(
        id = id,
        origen = origenDe(origen),
        estado = EstadoBorrador.from(estado),
        foto = foto,
        fotoMime = foto_mime,
        imagenBoleta = imagen_boleta,
        datos = datosDe(datos_json),
        mensaje = mensaje,
        creadoEn = created_at,
    )

    private fun origenDe(valor: String) = OrigenCompra.entries.firstOrNull { it.name == valor } ?: OrigenCompra.MANUAL

    private fun datosDe(json: String): BorradorCompra = runCatching { ApiJson.decodeFromString<BorradorCompra>(json) }.getOrDefault(BorradorCompra())
}
