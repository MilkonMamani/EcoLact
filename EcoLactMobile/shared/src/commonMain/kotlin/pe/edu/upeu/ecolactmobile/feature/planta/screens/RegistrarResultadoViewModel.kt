package pe.edu.upeu.ecolactmobile.feature.planta.screens

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProductoActivo
import pe.edu.upeu.ecolactmobile.feature.planta.RegistrarResultadoOpResult
import pe.edu.upeu.ecolactmobile.feature.planta.RegistrarResultadoRequest

/**
 * CORREGIDO (Etapa 8): sin insumos manuales — el backend los calcula solos a partir de la receta
 * del producto y se muestran en LoteDetalleScreen al volver (ver PlantaModels.kt).
 */
data class RegistrarResultadoUiState(
    val cargando: Boolean = true,
    val error: String? = null,
    val productos: List<ProductoActivo> = emptyList(),
    val productoSeleccionadoId: Long? = null,
    val cantidadProducida: String = "",
    val enviando: Boolean = false,
    val resultado: RegistrarResultadoOpResult? = null,
)

class RegistrarResultadoViewModel(private val repository: PlantaRepository, private val loteId: Long) : ScreenModel() {
    private val _state = MutableStateFlow(RegistrarResultadoUiState())
    val state: StateFlow<RegistrarResultadoUiState> = _state.asStateFlow()

    init { cargar() }

    fun cargar() {
        scope.launch {
            _state.update { it.copy(cargando = true, error = null) }
            try {
                val outcome = repository.loteDetalle(loteId)
                val data = when (outcome) {
                    is CacheOutcome.Fresh -> outcome.data
                    is CacheOutcome.Stale -> outcome.data
                }
                _state.update { it.copy(cargando = false, productos = data.productos_activos) }
            } catch (e: Exception) {
                _state.update { it.copy(cargando = false, error = e.message ?: "No se pudo cargar la información del lote") }
            }
        }
    }

    fun onProductoSeleccionado(id: Long) = _state.update { it.copy(productoSeleccionadoId = id) }
    fun onCantidadProducidaChange(value: String) = _state.update { it.copy(cantidadProducida = value) }

    fun onConfirmar() {
        val current = _state.value
        val productoId = current.productoSeleccionadoId
        val cantidad = current.cantidadProducida.toDoubleOrNull()
        if (productoId == null || cantidad == null || cantidad <= 0) {
            _state.update { it.copy(error = "Seleccione un producto e ingrese la cantidad obtenida") }
            return
        }
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            val resultado = repository.registrarResultado(loteId, RegistrarResultadoRequest(productoId, cantidad))
            _state.update { it.copy(enviando = false, resultado = resultado) }
        }
    }
}
