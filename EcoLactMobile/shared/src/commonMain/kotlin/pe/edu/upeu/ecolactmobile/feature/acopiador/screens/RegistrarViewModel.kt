package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorEntregaRequest
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorProveedorFila
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.RegistrarEntregaResult

enum class RegistrarStep { IDENTIFICAR, CANTIDAD, CONFIRMAR, RESULTADO }
enum class MetodoIdentificacion { QR, DNI }

data class RegistrarUiState(
    val step: RegistrarStep = RegistrarStep.IDENTIFICAR,
    val metodo: MetodoIdentificacion? = null,
    val identificador: String = "",
    val dniInput: String = "",
    val proveedorPreview: AcopiadorProveedorFila? = null,
    val buscandoProveedor: Boolean = false,
    val busquedaError: String? = null,
    val mostrandoScanner: Boolean = false,
    val litros: String = "",
    val observacion: String = "",
    val submitting: Boolean = false,
    val errorMensaje: String? = null,
    val resultado: RegistrarEntregaResult? = null,
)

/**
 * El backend resuelve identificación + registro en una sola llamada (acopiador.md sección 4):
 * no hay endpoint de "buscar proveedor" — para DNI, se usa `GET /acopiador/proveedores?buscar=`
 * (ya acotado a la zona del acopiador) solo para mostrar el nombre antes de confirmar. Si el DNI
 * no aparece ahí, no podemos saber si no existe o si es de otra zona (esa distinción no la da
 * este endpoint) — se muestra un mensaje genérico en vez de inventar el nombre de otra zona.
 */
class RegistrarViewModel(private val repository: AcopiadorRepository) : ScreenModel() {
    private val _state = MutableStateFlow(RegistrarUiState())
    val state: StateFlow<RegistrarUiState> = _state.asStateFlow()

    fun onAbrirScanner() = _state.update { it.copy(mostrandoScanner = true) }
    fun onCerrarScanner() = _state.update { it.copy(mostrandoScanner = false) }

    fun onQrEscaneado(token: String) {
        _state.update {
            it.copy(
                metodo = MetodoIdentificacion.QR,
                identificador = token,
                mostrandoScanner = false,
                proveedorPreview = null,
                step = RegistrarStep.CANTIDAD,
            )
        }
    }

    fun onDniInputChange(value: String) = _state.update { it.copy(dniInput = value, busquedaError = null) }

    fun onBuscarDni() {
        val dni = _state.value.dniInput.trim()
        if (dni.length != 8 || dni.any { !it.isDigit() }) {
            _state.update { it.copy(busquedaError = "El DNI debe tener exactamente 8 dígitos") }
            return
        }
        scope.launch {
            _state.update { it.copy(buscandoProveedor = true, busquedaError = null) }
            try {
                val outcome = repository.proveedores(buscar = dni)
                val filas = when (outcome) {
                    is CacheOutcome.Fresh -> outcome.data.filas
                    is CacheOutcome.Stale -> outcome.data.filas
                }
                val match = filas.firstOrNull { it.dni == dni }
                if (match != null) {
                    _state.update {
                        it.copy(
                            buscandoProveedor = false,
                            proveedorPreview = match,
                            metodo = MetodoIdentificacion.DNI,
                            identificador = dni,
                        )
                    }
                } else {
                    _state.update {
                        it.copy(buscandoProveedor = false, busquedaError = "No se encontró ese DNI entre los proveedores de su zona")
                    }
                }
            } catch (e: Exception) {
                _state.update { it.copy(buscandoProveedor = false, busquedaError = e.message ?: "No se pudo buscar el proveedor") }
            }
        }
    }

    fun onContinuarDesdeIdentificacion() = _state.update { it.copy(step = RegistrarStep.CANTIDAD) }

    fun onLitrosChange(value: String) = _state.update { it.copy(litros = value, errorMensaje = null) }
    fun onObservacionChange(value: String) = _state.update { it.copy(observacion = value) }

    fun onContinuarDesdeCantidad() {
        val litros = _state.value.litros.toDoubleOrNull()
        if (litros == null || litros <= 0) {
            _state.update { it.copy(errorMensaje = "Ingrese una cantidad de litros válida") }
            return
        }
        _state.update { it.copy(step = RegistrarStep.CONFIRMAR, errorMensaje = null) }
    }

    fun onVolverACantidad() = _state.update { it.copy(step = RegistrarStep.CANTIDAD) }

    fun onConfirmar() {
        val current = _state.value
        val metodo = current.metodo ?: return
        val litros = current.litros.toDoubleOrNull() ?: return
        scope.launch {
            _state.update { it.copy(submitting = true, errorMensaje = null) }
            val request = AcopiadorEntregaRequest(
                metodo_identificacion = metodo.name,
                identificador = current.identificador,
                litros = litros,
                observacion = current.observacion.ifBlank { null },
            )
            val resultado = repository.registrarEntrega(request)
            _state.update { it.copy(submitting = false, resultado = resultado, step = RegistrarStep.RESULTADO) }
        }
    }

    fun onNuevoRegistro() {
        _state.value = RegistrarUiState()
    }
}
