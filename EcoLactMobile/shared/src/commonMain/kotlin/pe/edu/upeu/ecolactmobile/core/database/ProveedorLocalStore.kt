package pe.edu.upeu.ecolactmobile.core.database

import pe.edu.upeu.ecolactmobile.core.util.nowIso

/** Fila a persistir localmente — cada feature mapea su propio DTO (AcopiadorProveedorFila, CalidadProveedorFila, ProveedorPerfil, ...) a esto. */
data class ProveedorRow(
    val id: Long,
    val dni: String,
    val nombres: String,
    val apellidos: String,
    val nombreCompleto: String,
    val telefono: String?,
    val zonaId: Long?,
    val zonaNombre: String?,
    val estado: String,
    val qrToken: String?,
)

class ProveedorLocalStore(database: EcoLactDatabase) {
    private val queries = database.proveedorQueries

    fun upsert(row: ProveedorRow) {
        queries.upsertProveedor(
            row.id, row.dni, row.nombres, row.apellidos, row.nombreCompleto,
            row.telefono, row.zonaId, row.zonaNombre, row.estado, row.qrToken, nowIso(),
        )
    }

    /** Reemplaza el contenido local por el listado recién traído del servidor (evita filas obsoletas si algo se borró allá). */
    fun replaceAll(rows: List<ProveedorRow>) {
        queries.transaction {
            queries.deleteAll()
            rows.forEach { upsert(it) }
        }
    }

    fun all(): List<Proveedor> = queries.selectAll().executeAsList()
    fun byId(id: Long): Proveedor? = queries.selectById(id).executeAsOneOrNull()
    fun byDni(dni: String): Proveedor? = queries.selectByDni(dni).executeAsOneOrNull()
    fun search(texto: String): List<Proveedor> = queries.searchByNombreOrDni(texto, texto).executeAsList()
}
