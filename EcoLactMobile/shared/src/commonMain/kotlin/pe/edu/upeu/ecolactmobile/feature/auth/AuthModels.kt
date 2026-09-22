package pe.edu.upeu.ecolactmobile.feature.auth

import kotlinx.serialization.Serializable

@Serializable
data class LoginRequestBody(
    val username: String,
    val password: String,
)

/** Shape exacto documentado en administrador.md sección 1 (igual para los 6 roles). */
@Serializable
data class LoginResponse(
    val token: String,
    val user: LoginUser,
)

@Serializable
data class LoginUser(
    val id: Long,
    val username: String,
    val role: String,
    val estado: String,
    val debe_cambiar_password: Boolean = false,
    val ultimo_acceso: String? = null,
)
