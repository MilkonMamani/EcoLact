package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadEntregaMuestreadaRequest
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadProveedorFila
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.RegistrarEntregaMuestreadaResult

enum class MuestreoStep { IDENTIFICAR, CANTIDAD, CONFIRMAR, RESULTADO }
enum class MuestreoMetodo { QR, DNI }

/**
 * Etapa 7: pantalla que materializa "el analista también puede acopiar" en el negocio real — solo
 * para el caso acotado de muestreo directo cuando se anticipa que el resultado va a ser RECHAZADA
 * (ver CalidadModels.kt). Mismo patrón de 4 pasos que AcopiadorRegistrarScreen.
 */
data class MuestreoUiState(
    val step: MuestreoStep = MuestreoStep.IDENTIFICAR,
    val metodo: MuestreoMetodo? = null,
    val identificador: String = "",
    val dniInput: String = "",
    val proveedorPreview: CalidadProveedorFila? = null,
    val buscandoProveedor: Boolean = false,
    val busquedaError: String? = null,
    val mostrandoScanner: Boolean = false,
    val litros: String = "",
    val observacion: String = "",
    val submitting: Boolean = false,
    val errorMensaje: String? = null,
    val resultado: RegistrarEntregaMuestreadaResult? = null,
)

class MuestreoViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow(MuestreoUiState())
    val state: StateFlow<MuestreoUiState> = _state.asStateFlow()

    fun onAbrirScanner() = _state.update { it.copy(mostrandoScanner = true) }
    fun onCerrarScanner() = _state.update { it.copy(mostrandoScanner = false) }

    fun onQrEscaneado(token: String) {
        _state.update {
            it.copy(metodo = MuestreoMetodo.QR, identificador = token, mostrandoScanner = false, proveedorPreview = null, step = MuestreoStep.CANTIDAD)
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
                val outcome = repository.proveedores(q = dni)
                val filas = when (outcome) {
                    is CacheOutcome.Fresh -> outcome.data.filas
                    is CacheOutcome.Stale -> outcome.data.filas
                }
                val match = filas.firstOrNull { it.dni == dni }
                if (match != null) {
                    _state.update { it.copy(buscandoProveedor = false, proveedorPreview = match, metodo = MuestreoMetodo.DNI, identificador = dni) }
                } else {
                    _state.update { it.copy(buscandoProveedor = false, busquedaError = "No se encontró ese DNI") }
                }
            } catch (e: Exception) {
                _state.update { it.copy(buscandoProveedor = false, busquedaError = e.message ?: "No se pudo buscar el proveedor") }
            }
        }
    }

    fun onContinuarDesdeIdentificacion() = _state.update { it.copy(step = MuestreoStep.CANTIDAD) }
    fun onLitrosChange(value: String) = _state.update { it.copy(litros = value, errorMensaje = null) }
    fun onObservacionChange(value: String) = _state.update { it.copy(observacion = value) }

    fun onContinuarDesdeCantidad() {
        val litros = _state.value.litros.toDoubleOrNull()
        if (litros == null || litros <= 0) {
            _state.update { it.copy(errorMensaje = "Ingrese una cantidad de litros válida") }
            return
        }
        _state.update { it.copy(step = MuestreoStep.CONFIRMAR, errorMensaje = null) }
    }

    fun onVolverACantidad() = _state.update { it.copy(step = MuestreoStep.CANTIDAD) }

    fun onConfirmar() {
        val current = _state.value
        val metodo = current.metodo ?: return
        val litros = current.litros.toDoubleOrNull() ?: return
        scope.launch {
            _state.update { it.copy(submitting = true, errorMensaje = null) }
            val request = CalidadEntregaMuestreadaRequest(
                metodo_identificacion = metodo.name,
                identificador = current.identificador,
                litros = litros,
                observacion = current.observacion.ifBlank { null },
            )
            val resultado = repository.registrarEntregaMuestreada(request)
            _state.update { it.copy(submitting = false, resultado = resultado, step = MuestreoStep.RESULTADO) }
        }
    }

    fun onNuevoRegistro() {
        _state.value = MuestreoUiState()
    }
}
