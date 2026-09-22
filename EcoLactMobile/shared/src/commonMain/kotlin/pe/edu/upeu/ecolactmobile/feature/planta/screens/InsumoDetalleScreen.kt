package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.InsumoDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.RegistrarMovimientoRequest

class InsumoDetalleViewModel(repository: PlantaRepository, insumoId: Long) :
    ResourceViewModel<InsumoDetalleResponse>({ repository.insumoDetalle(insumoId) })

data class MovimientoFormState(val tipo: String = "ENTRADA", val motivo: String = "", val cantidad: String = "", val enviando: Boolean = false, val error: String? = null)

class MovimientoFormViewModel(private val repository: PlantaRepository, private val insumoId: Long, private val onGuardado: () -> Unit) : ScreenModel() {
    private val _state = MutableStateFlow(MovimientoFormState())
    val state: StateFlow<MovimientoFormState> = _state.asStateFlow()

    fun onTipoChange(v: String) = _state.update { it.copy(tipo = v) }
    fun onMotivoChange(v: String) = _state.update { it.copy(motivo = v) }
    fun onCantidadChange(v: String) = _state.update { it.copy(cantidad = v) }

    fun registrar() {
        val cantidad = _state.value.cantidad.toDoubleOrNull()
        if (cantidad == null || cantidad <= 0 || _state.value.motivo.isBlank()) {
            _state.update { it.copy(error = "Ingrese motivo y una cantidad válida") }
            return
        }
        scope.launch {
            _state.update { it.copy(enviando = true, error = null) }
            repository.registrarMovimiento(insumoId, RegistrarMovimientoRequest(_state.value.tipo, _state.value.motivo, cantidad))
                .onSuccess { _state.update { MovimientoFormState() }; onGuardado() }
                .onFailure { e -> _state.update { it.copy(enviando = false, error = e.message ?: "No se pudo registrar el movimiento") } }
        }
    }
}

@Composable
fun InsumoDetalleScreen(viewModel: InsumoDetalleViewModel, formViewModel: MovimientoFormViewModel) {
    val state by viewModel.state.collectAsState()
    val form by formViewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val insumo = s.data.insumo
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text(insumo.nombre, style = MaterialTheme.typography.titleLarge)
                Text("${insumo.stock_actual} ${insumo.unidad_medida ?: ""}", style = MaterialTheme.typography.titleMedium)
                insumo.stock_minimo?.let { Text("Stock mínimo: $it", color = EcoLactColors.TextoSecundario) }

                if (s.data.movimientos.isNotEmpty()) {
                    SectionTitle("Movimientos recientes")
                    s.data.movimientos.forEach {
                        Text("${it.fecha} · ${it.tipo} ${it.cantidad} · ${it.motivo ?: ""}", color = EcoLactColors.TextoSecundario)
                    }
                }

                SectionTitle("Registrar movimiento")
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    FilterChip(selected = form.tipo == "ENTRADA", onClick = { formViewModel.onTipoChange("ENTRADA") }, label = { Text("Entrada") })
                    FilterChip(selected = form.tipo == "SALIDA", onClick = { formViewModel.onTipoChange("SALIDA") }, label = { Text("Salida") })
                }
                OutlinedTextField(form.motivo, formViewModel::onMotivoChange, label = { Text("Motivo") }, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(
                    form.cantidad,
                    formViewModel::onCantidadChange,
                    label = { Text("Cantidad") },
                    keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    modifier = Modifier.fillMaxWidth(),
                )
                form.error?.let { Text(it, color = EcoLactColors.Rojo) }
                Button(
                    onClick = formViewModel::registrar,
                    enabled = !form.enviando,
                    modifier = Modifier.fillMaxWidth().height(48.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                ) {
                    if (form.enviando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
                    else Text("Registrar movimiento")
                }
            }
        }
    }
}
