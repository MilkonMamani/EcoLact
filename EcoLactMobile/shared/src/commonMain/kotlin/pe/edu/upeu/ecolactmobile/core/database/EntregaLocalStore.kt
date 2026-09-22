package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class EntregaRow(
    val id: Long,
    val proveedorId: Long,
    val proveedorNombre: String?,
    val proveedorDni: String?,
    val acopiadorId: Long?,
    val acopiadorNombre: String?,
    val zonaId: Long?,
    val zonaNombre: String?,
    val registradoPorUserId: Long?,
    val fechaHora: String,
    val litros: Double,
    val metodoIdentificacion: String,
    val estado: String,
    val observacion: String?,
    val resultado: String?,
)

class EntregaLocalStore(database: EcoLactDatabase) {
    private val queries = database.entregaQueries

    fun upsert(row: EntregaRow) {
        queries.upsertEntrega(
            row.id, row.proveedorId, row.proveedorNombre, row.proveedorDni, row.acopiadorId, row.acopiadorNombre,
            row.zonaId, row.zonaNombre, row.registradoPorUserId, row.fechaHora, row.litros, row.metodoIdentificacion,
            row.estado, row.observacion, row.resultado, nowIso(),
        )
    }

    fun replaceAll(rows: List<EntregaRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Entrega> = queries.selectAll().executeAsList()
    fun byId(id: Long): Entrega? = queries.selectById(id).executeAsOneOrNull()
    fun byEstado(estado: String): List<Entrega> = queries.selectByEstado(estado).executeAsList()
    fun byProveedor(proveedorId: Long): List<Entrega> = queries.selectByProveedor(proveedorId).executeAsList()
}
