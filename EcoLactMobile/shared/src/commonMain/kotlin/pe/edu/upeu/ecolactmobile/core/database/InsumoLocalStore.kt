package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class InsumoRow(
    val id: Long,
    val nombre: String,
    val unidadMedida: String?,
    val stockActual: Double,
    val stockMinimo: Double?,
    val estado: String?,
    val estaBajoMinimo: Boolean,
)

class InsumoLocalStore(database: EcoLactDatabase) {
    private val queries = database.insumoQueries

    fun upsert(row: InsumoRow) {
        queries.upsertInsumo(row.id, row.nombre, row.unidadMedida, row.stockActual, row.stockMinimo, row.estado, row.estaBajoMinimo, nowIso())
    }

    fun replaceAll(rows: List<InsumoRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Insumo> = queries.selectAll().executeAsList()
    fun byId(id: Long): Insumo? = queries.selectById(id).executeAsOneOrNull()
    fun bajoMinimo(): List<Insumo> = queries.selectBajoMinimo().executeAsList()
}
