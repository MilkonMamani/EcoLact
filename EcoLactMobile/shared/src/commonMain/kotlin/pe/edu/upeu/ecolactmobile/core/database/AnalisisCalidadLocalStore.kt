package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class AnalisisCalidadRow(
    val id: Long,
    val entregaId: Long,
    val imagenPath: String?,
    val estado: String,
    val resultado: String?,
    val fechaAnalisis: String?,
    val confirmadoPorUserId: Long?,
    val observacion: String?,
)

data class AnalisisCalidadDetalleRow(
    val parametroId: Long,
    val parametroCodigo: String,
    val parametroNombre: String,
    val unidad: String?,
    val valor: Double,
    val origen: String,
    val cumple: Boolean?,
)

class AnalisisCalidadLocalStore(database: EcoLactDatabase) {
    private val queries = database.analisisCalidadQueries

    fun upsert(row: AnalisisCalidadRow, detalles: List<AnalisisCalidadDetalleRow> = emptyList()) {
        queries.transaction {
            queries.upsertAnalisis(
                row.id, row.entregaId, row.imagenPath, row.estado, row.resultado,
                row.fechaAnalisis, row.confirmadoPorUserId, row.observacion, nowIso(),
            )
            queries.deleteDetallesByAnalisis(row.id)
            detalles.forEach { d ->
                queries.insertDetalle(row.id, d.parametroId, d.parametroCodigo, d.parametroNombre, d.unidad, d.valor, d.origen, d.cumple)
            }
        }
    }

    fun replaceAll(rows: List<AnalisisCalidadRow>) {
        queries.transaction {
            queries.deleteAnalisisAll()
            rows.forEach { r -> queries.upsertAnalisis(r.id, r.entregaId, r.imagenPath, r.estado, r.resultado, r.fechaAnalisis, r.confirmadoPorUserId, r.observacion, nowIso()) }
        }
    }

    fun byId(id: Long): Analisis_calidad? = queries.selectAnalisisById(id).executeAsOneOrNull()
    fun byEntrega(entregaId: Long): Analisis_calidad? = queries.selectAnalisisByEntrega(entregaId).executeAsOneOrNull()
    fun all(): List<Analisis_calidad> = queries.selectAnalisisAll().executeAsList()
    fun detalles(analisisId: Long): List<Analisis_calidad_detalle> = queries.selectDetallesByAnalisis(analisisId).executeAsList()
}
