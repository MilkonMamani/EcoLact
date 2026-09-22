package pe.edu.upeu.ecolactmobile.feature.auth

import pe.edu.upeu.ecolactmobile.core.session.Role
import pe.edu.upeu.ecolactmobile.core.session.SessionManager
import pe.edu.upeu.ecolactmobile.core.session.UserSession

class AuthRepository(
    private val authApi: AuthApi,
    private val sessionManager: SessionManager,
) {
    /** Login siempre requiere conexión: no tiene sentido offline (no hay token todavía que reutilizar). */
    suspend fun login(username: String, password: String): UserSession {
        val response = authApi.login(username, password)
        val session = UserSession(
            token = response.token,
            userId = response.user.id,
            username = response.user.username,
            role = Role.fromApiValue(response.user.role),
            estado = response.user.estado,
            debeCambiarPassword = response.user.debe_cambiar_password,
            ultimoAcceso = response.user.ultimo_acceso,
        )
        sessionManager.save(session)
        return session
    }

    /** Limpia la sesión local aunque el logout remoto falle (ej. sin conexión): es una decisión del usuario, no depende del servidor. */
    suspend fun logout() {
        runCatching { authApi.logout() }
        sessionManager.clear()
    }
}
