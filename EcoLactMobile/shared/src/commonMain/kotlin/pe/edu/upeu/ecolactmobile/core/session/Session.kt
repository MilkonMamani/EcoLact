package pe.edu.upeu.ecolactmobile.core.session

/** Nombrado UserSession (no Session) para no chocar con la fila `Session` que genera SQLDelight para la tabla `session`. */
data class UserSession(
    val token: String,
    val userId: Long,
    val username: String,
    val role: Role,
    val estado: String,
    val debeCambiarPassword: Boolean,
    val ultimoAcceso: String?,
)
