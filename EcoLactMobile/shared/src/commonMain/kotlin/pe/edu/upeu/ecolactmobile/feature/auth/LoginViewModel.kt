package pe.edu.upeu.ecolactmobile.feature.auth

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.network.ApiException
import pe.edu.upeu.ecolactmobile.core.session.UserSession
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel

data class LoginUiState(
    val username: String = "",
    val password: String = "",
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
)

class LoginViewModel(private val authRepository: AuthRepository) : ScreenModel() {
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onUsernameChange(value: String) {
        _uiState.update { it.copy(username = value, errorMessage = null) }
    }

    fun onPasswordChange(value: String) {
        _uiState.update { it.copy(password = value, errorMessage = null) }
    }

    fun submit(onSuccess: (UserSession) -> Unit) {
        val state = _uiState.value
        if (state.username.isBlank() || state.password.isBlank()) {
            _uiState.update { it.copy(errorMessage = "Ingrese usuario y contraseña") }
            return
        }
        scope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            try {
                val session = authRepository.login(state.username.trim(), state.password)
                _uiState.update { it.copy(isLoading = false) }
                onSuccess(session)
            } catch (e: ApiException) {
                _uiState.update { it.copy(isLoading = false, errorMessage = loginErrorMessage(e)) }
            } catch (e: Exception) {
                _uiState.update {
                    it.copy(isLoading = false, errorMessage = "No se pudo conectar. Verifique su conexión a internet.")
                }
            }
        }
    }

    private fun loginErrorMessage(e: ApiException): String = when (e) {
        is ApiException.Unauthorized -> "Usuario o contraseña incorrectos"
        is ApiException.Validation -> e.message
        is ApiException.Forbidden -> "Esta cuenta no tiene acceso"
        else -> e.message ?: "Ocurrió un error inesperado"
    }
}
