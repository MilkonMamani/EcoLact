package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class NotificacionRow(
    val id: Long,
    val tipo: String,
    val titulo: String,
    val mensaje: String,
    val referenciaTipo: String?,
    val referenciaId: Long?,
    val leido: Boolean,
    val fechaLectura: String?,
)

class NotificacionLocalStore(database: EcoLactDatabase) {
    private val queries = database.notificacionQueries

    fun upsert(row: NotificacionRow) {
        queries.upsertNotificacion(row.id, row.tipo, row.titulo, row.mensaje, row.referenciaTipo, row.referenciaId, row.leido, row.fechaLectura, nowIso())
    }

    fun replaceAll(rows: List<NotificacionRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Notificacion> = queries.selectAll().executeAsList()
    fun markLeida(id: Long) = queries.markLeida(nowIso(), id)
}
