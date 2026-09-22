package pe.edu.upeu.ecolactmobile.feature.planta.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.planta.IniciarProduccionRequest
import pe.edu.upeu.ecolactmobile.feature.planta.IniciarProduccionResult
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository

enum class IniciarStep { FORMULARIO, RESULTADO }

/**
 * CORREGIDO (Etapa 8): el acopio no separa la leche por entrega (ver PlantaModels.kt) — ya no se
 * seleccionan entregas, solo se ingresa el total de litros a usar, validado contra el acumulado
 * disponible (`litrosDisponibles`, de solo lectura informativa).
 */
data class IniciarProduccionUiState(
    val step: IniciarStep = IniciarStep.FORMULARIO,
    val cargando: Boolean = true,
    val error: String? = null,
    val litrosDisponibles: Double = 0.0,
    val litrosUtilizados: String = "",
    val observacion: String = "",
    val enviando: Boolean = false,
    val resultado: IniciarProduccionResult? = null,
)

class IniciarProduccionViewModel(private val repository: PlantaRepository) : ScreenModel() {
    private val _state = MutableStateFlow(IniciarProduccionUiState())
    val state: StateFlow<IniciarProduccionUiState> = _state.asStateFlow()

    init { cargar() }

    fun cargar() {
        scope.launch {
            _state.update { it.copy(cargando = true, error = null) }
            try {
                val outcome = repository.lecheDisponible()
                val litros = when (outcome) {
                    is CacheOutcome.Fresh -> outcome.data
                    is CacheOutcome.Stale -> outcome.data
                }
                _state.update { it.copy(cargando = false, litrosDisponibles = litros.litros_disponibles) }
            } catch (e: Exception) {
                _state.update { it.copy(cargando = false, error = e.message ?: "No se pudo cargar la leche disponible") }
            }
        }
    }

    fun onLitrosUtilizadosChange(value: String) = _state.update { it.copy(litrosUtilizados = value, error = null) }
    fun onObservacionChange(value: String) = _state.update { it.copy(observacion = value) }

    fun onConfirmar() {
        val current = _state.value
        val litros = current.litrosUtilizados.toDoubleOrNull()
        if (litros == null || litros <= 0) {
            _state.update { it.copy(error = "Ingrese los litros a utilizar") }
            return
        }
        if (litros > current.litrosDisponibles) {
            _state.update { it.copy(error = "No hay suficiente leche disponible (${current.litrosDisponibles} L)") }
            return
        }
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            val resultado = repository.iniciarProduccion(
                IniciarProduccionRequest(litros, observacion = current.observacion.ifBlank { null }),
            )
            _state.update { it.copy(enviando = false, resultado = resultado, step = IniciarStep.RESULTADO) }
        }
    }
}
