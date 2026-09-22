package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class LoteProduccionRow(
    val id: Long,
    val codigo: String,
    val estado: String,
    val fechaHoraInicio: String?,
    val fechaHoraFin: String?,
    val litrosLecheUtilizados: Double,
    val observacion: String?,
    /** JSON crudo de `insumos_utilizados`/`productos_obtenidos` — ver nota en LoteProduccion.sq. */
    val insumosUtilizadosJson: String?,
    val productosObtenidosJson: String?,
)

class LoteProduccionLocalStore(database: EcoLactDatabase) {
    private val queries = database.loteProduccionQueries

    fun upsert(row: LoteProduccionRow) {
        queries.upsertLote(
            row.id, row.codigo, row.estado, row.fechaHoraInicio, row.fechaHoraFin,
            row.litrosLecheUtilizados, row.observacion, row.insumosUtilizadosJson, row.productosObtenidosJson, nowIso(),
        )
    }

    fun replaceAll(rows: List<LoteProduccionRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Lote_produccion> = queries.selectAll().executeAsList()
    fun byId(id: Long): Lote_produccion? = queries.selectById(id).executeAsOneOrNull()
    fun byEstado(estado: String): List<Lote_produccion> = queries.selectByEstado(estado).executeAsList()
}
