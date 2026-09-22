package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class ProductoRow(
    val id: Long,
    val nombre: String,
    val unidadMedida: String?,
    val cantidadActual: Double,
    val sinStock: Boolean,
    val estado: String?,
)

class ProductoLocalStore(database: EcoLactDatabase) {
    private val queries = database.productoQueries

    fun upsert(row: ProductoRow) {
        queries.upsertProducto(row.id, row.nombre, row.unidadMedida, row.cantidadActual, row.sinStock, row.estado, nowIso())
    }

    fun replaceAll(rows: List<ProductoRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Producto> = queries.selectAll().executeAsList()
    fun byId(id: Long): Producto? = queries.selectById(id).executeAsOneOrNull()
}
