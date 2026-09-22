package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.CrearInsumoRequest
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository

data class NuevoInsumoUiState(
    val nombre: String = "",
    val unidadMedida: String = "",
    val stockMinimo: String = "",
    val stockInicial: String = "",
    val enviando: Boolean = false,
    val error: String? = null,
    val creado: Boolean = false,
)

class NuevoInsumoViewModel(private val repository: PlantaRepository) : ScreenModel() {
    private val _state = MutableStateFlow(NuevoInsumoUiState())
    val state: StateFlow<NuevoInsumoUiState> = _state.asStateFlow()

    fun onNombreChange(v: String) = _state.update { it.copy(nombre = v) }
    fun onUnidadChange(v: String) = _state.update { it.copy(unidadMedida = v) }
    fun onStockMinimoChange(v: String) = _state.update { it.copy(stockMinimo = v) }
    fun onStockInicialChange(v: String) = _state.update { it.copy(stockInicial = v) }

    fun crear() {
        val current = _state.value
        val stockMinimo = current.stockMinimo.toDoubleOrNull()
        if (current.nombre.isBlank() || current.unidadMedida.isBlank() || stockMinimo == null) {
            _state.update { it.copy(error = "Complete nombre, unidad y stock mínimo") }
            return
        }
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            repository.crearInsumo(CrearInsumoRequest(current.nombre, current.unidadMedida, stockMinimo, current.stockInicial.toDoubleOrNull()))
                .onSuccess { _state.update { it.copy(enviando = false, creado = true) } }
                .onFailure { e -> _state.update { it.copy(enviando = false, error = e.message ?: "No se pudo registrar el insumo") } }
        }
    }
}

@Composable
fun NuevoInsumoScreen(viewModel: NuevoInsumoViewModel, onCreado: () -> Unit) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(state.creado) {
        if (state.creado) onCreado()
    }

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        OutlinedTextField(state.nombre, viewModel::onNombreChange, label = { Text("Nombre") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(state.unidadMedida, viewModel::onUnidadChange, label = { Text("Unidad de medida") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(state.stockMinimo, viewModel::onStockMinimoChange, label = { Text("Stock mínimo") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(state.stockInicial, viewModel::onStockInicialChange, label = { Text("Stock inicial (opcional)") }, modifier = Modifier.fillMaxWidth())

        state.error?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::crear,
            enabled = !state.enviando,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.enviando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            else Text("Registrar insumo")
        }
    }
}
