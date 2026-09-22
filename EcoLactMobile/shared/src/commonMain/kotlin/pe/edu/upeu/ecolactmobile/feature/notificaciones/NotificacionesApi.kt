package pe.edu.upeu.ecolactmobile.feature.notificaciones

import io.ktor.client.HttpClient
import io.ktor.client.request.get
import io.ktor.client.request.patch
import kotlinx.serialization.Serializable
import pe.edu.upeu.ecolactmobile.core.network.requireSuccess
import pe.edu.upeu.ecolactmobile.core.network.toEnvelopeData

/** Compartido por los 6 roles (ver administrador.md sección 7). */
@Serializable
data class NotificacionItem(
    val id: Long,
    val tipo: String,
    val titulo: String,
    val mensaje: String,
    val referencia_tipo: String? = null,
    val referencia_id: Long? = null,
    val leido: Boolean = false,
    val fecha_lectura: String? = null,
)

class NotificacionesApi(private val httpClient: HttpClient) {
    suspend fun listar(): List<NotificacionItem> =
        httpClient.get("notificaciones").toEnvelopeData()

    suspend fun marcarLeida(id: Long) {
        httpClient.patch("notificaciones/$id/leida").requireSuccess()
    }
}
