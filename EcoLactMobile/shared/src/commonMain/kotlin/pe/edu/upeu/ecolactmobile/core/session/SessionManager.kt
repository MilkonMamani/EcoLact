package pe.edu.upeu.ecolactmobile.core.session

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import pe.edu.upeu.ecolactmobile.core.database.EcoLactDatabase

/**
 * Fuente única de verdad para la sesión autenticada: token Bearer + rol.
 * Persiste en SQLDelight para sobrevivir reinicios de la app, y expone un
 * StateFlow en memoria para que la navegación reaccione a login/logout.
 */
class SessionManager(database: EcoLactDatabase) {
    private val queries = database.sessionQueries

    private val _session = MutableStateFlow<UserSession?>(null)
    val session: StateFlow<UserSession?> = _session.asStateFlow()

    fun restore() {
        val row = queries.selectSession().executeAsOneOrNull()
        _session.value = row?.let {
            UserSession(
                token = it.token,
                userId = it.user_id,
                username = it.username,
                role = Role.fromApiValue(it.role),
                estado = it.estado,
                debeCambiarPassword = it.debe_cambiar_password != 0L,
                ultimoAcceso = it.ultimo_acceso,
            )
        }
    }

    fun save(userSession: UserSession) {
        queries.upsertSession(
            token = userSession.token,
            user_id = userSession.userId,
            username = userSession.username,
            role = userSession.role.name,
            estado = userSession.estado,
            debe_cambiar_password = if (userSession.debeCambiarPassword) 1L else 0L,
            ultimo_acceso = userSession.ultimoAcceso,
        )
        _session.value = userSession
    }

    fun clear() {
        queries.clearSession()
        _session.value = null
    }

    fun currentToken(): String? = _session.value?.token
}
