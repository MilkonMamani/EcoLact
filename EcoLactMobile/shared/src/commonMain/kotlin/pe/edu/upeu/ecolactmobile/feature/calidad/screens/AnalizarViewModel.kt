package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.components.ImagenAdjunta
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.ConfirmarAnalisisResult
import pe.edu.upeu.ecolactmobile.feature.calidad.ParametroActivo

enum class AnalizarStep { EVIDENCIA, PARAMETROS, CONFIRMAR, RESULTADO }

data class AnalizarUiState(
    val step: AnalizarStep = AnalizarStep.EVIDENCIA,
    val cargando: Boolean = true,
    val error: String? = null,
    val parametros: List<ParametroActivo> = emptyList(),
    val valores: Map<String, String> = emptyMap(),
    val observacion: String = "",
    val imagen: ImagenAdjunta? = null,
    val soloLectura: Boolean = false,
    val enviando: Boolean = false,
    val resultado: ConfirmarAnalisisResult? = null,
)

/**
 * El backend no expone rangos/reglas de calidad a este cliente (calidad.md no da ese contrato) ni
 * calcula el resultado hasta `finalizar`, así que aquí no se muestra ningún "✓ dentro de rango"
 * inventado en el paso de Parámetros — eso solo se sabe después de confirmar.
 */
class AnalizarViewModel(private val repository: CalidadRepository, private val entregaId: Long) : ScreenModel() {
    private val _state = MutableStateFlow(AnalizarUiState())
    val state: StateFlow<AnalizarUiState> = _state.asStateFlow()

    init { cargar() }

    fun cargar() {
        scope.launch {
            _state.update { it.copy(cargando = true, error = null) }
            try {
                val outcome = repository.analisisPrevio(entregaId)
                val previo = when (outcome) {
                    is CacheOutcome.Fresh -> outcome.data
                    is CacheOutcome.Stale -> outcome.data
                }
                val borradorLocal = repository.obtenerBorradorLocal(entregaId)
                val confirmadoServidor = previo.analisis?.estado == "CONFIRMADO"
                _state.update {
                    it.copy(
                        cargando = false,
                        parametros = previo.parametros,
                        observacion = borradorLocal?.observacion ?: previo.analisis?.observacion.orEmpty(),
                        valores = borradorLocal?.valores?.mapValues { (_, v) -> v.toString() } ?: emptyMap(),
                        soloLectura = confirmadoServidor,
                        resultado = if (confirmadoServidor) ConfirmarAnalisisResult.Confirmado(previo.analisis) else null,
                        step = if (confirmadoServidor) AnalizarStep.RESULTADO else AnalizarStep.EVIDENCIA,
                    )
                }
            } catch (e: Exception) {
                _state.update { it.copy(cargando = false, error = e.message ?: "No se pudo cargar la entrega") }
            }
        }
    }

    fun onImagenSeleccionada(imagen: ImagenAdjunta) = _state.update { it.copy(imagen = imagen) }

    fun onContinuarDesdeEvidencia() = _state.update { it.copy(step = AnalizarStep.PARAMETROS) }

    fun onValorChange(codigo: String, value: String) = _state.update { it.copy(valores = it.valores + (codigo to value)) }

    fun onObservacionChange(value: String) = _state.update { it.copy(observacion = value) }

    fun onContinuarDesdeParametros() {
        guardarBorrador()
        _state.update { it.copy(step = AnalizarStep.CONFIRMAR) }
    }

    fun onVolverAParametros() = _state.update { it.copy(step = AnalizarStep.PARAMETROS) }

    private fun guardarBorrador() {
        repository.guardarBorradorLocal(entregaId, valoresNumericos(), _state.value.observacion.ifBlank { null })
    }

    private fun valoresNumericos(): Map<String, Double> =
        _state.value.valores.mapNotNull { (codigo, texto) -> texto.toDoubleOrNull()?.let { codigo to it } }.toMap()

    fun onConfirmar() {
        val current = _state.value
        scope.launch {
            _state.update { it.copy(enviando = true) }
            val resultado = repository.confirmarAnalisis(
                entregaId,
                valoresNumericos(),
                current.observacion.ifBlank { null },
                current.imagen,
            )
            _state.update { it.copy(enviando = false, resultado = resultado, step = AnalizarStep.RESULTADO) }
        }
    }
}
