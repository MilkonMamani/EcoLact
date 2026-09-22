package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

data class ComunicadoRow(
    val id: Long,
    val titulo: String,
    val contenido: String?,
    val tipo: String,
    val alcance: String?,
    val fechaPublicacion: String,
    val fechaEvento: String?,
    val estado: String?,
    val leido: Boolean?,
)

class ComunicadoLocalStore(database: EcoLactDatabase) {
    private val queries = database.comunicadoQueries

    fun upsert(row: ComunicadoRow) {
        queries.upsertComunicado(row.id, row.titulo, row.contenido, row.tipo, row.alcance, row.fechaPublicacion, row.fechaEvento, row.estado, row.leido, nowIso())
    }

    fun replaceAll(rows: List<ComunicadoRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Comunicado> = queries.selectAll().executeAsList()
    fun byId(id: Long): Comunicado? = queries.selectById(id).executeAsOneOrNull()
}
