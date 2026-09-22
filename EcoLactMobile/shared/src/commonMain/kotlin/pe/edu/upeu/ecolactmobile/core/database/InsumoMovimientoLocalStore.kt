package pe.edu.upeu.ecolactmobile.core.database

data class InsumoMovimientoRow(
    val id: Long,
    val insumoId: Long,
    val insumoNombre: String?,
    val tipo: String,
    val motivo: String?,
    val cantidad: Double,
    val fecha: String,
    val observacion: String?,
)

class InsumoMovimientoLocalStore(database: EcoLactDatabase) {
    private val queries = database.insumoMovimientoQueries

    fun upsert(row: InsumoMovimientoRow) {
        queries.upsertMovimiento(row.id, row.insumoId, row.insumoNombre, row.tipo, row.motivo, row.cantidad, row.fecha, row.observacion)
    }

    fun replaceAll(rows: List<InsumoMovimientoRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Insumo_movimiento> = queries.selectAll().executeAsList()
    fun byInsumo(insumoId: Long): List<Insumo_movimiento> = queries.selectByInsumo(insumoId).executeAsList()
}
