package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.cache.CacheOutcome
import pe.edu.upeu.ecolactmobile.core.ui.components.ChipTone
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.StatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorProveedoresListResponse
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository

class AcopiadorProveedoresViewModel(private val repository: AcopiadorRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<AcopiadorProveedoresListResponse>>(UiState.Loading)
    val state: StateFlow<UiState<AcopiadorProveedoresListResponse>> = _state.asStateFlow()

    private val _buscar = MutableStateFlow("")
    val buscar: StateFlow<String> = _buscar.asStateFlow()

    init { load() }

    fun onBuscarChange(value: String) {
        _buscar.value = value
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = repository.proveedores(buscar = _buscar.value.ifBlank { null })) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(outcome.data, false)
                    is CacheOutcome.Stale -> _state.value = UiState.Success(outcome.data, true, outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudieron cargar los proveedores")
            }
        }
    }
}

@Composable
fun AcopiadorProveedoresScreen(viewModel: AcopiadorProveedoresViewModel, onVerProveedor: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val buscar by viewModel.buscar.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        OutlinedTextField(
            value = buscar,
            onValueChange = viewModel::onBuscarChange,
            label = { Text("Buscar por nombre o DNI") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )

        when (val s = state) {
            is UiState.Loading -> LoadingBox()
            is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load)
            is UiState.Success -> {
                Column(modifier = Modifier.padding(top = 12.dp)) {
                    if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                    if (s.data.filas.isEmpty()) {
                        EmptyBox("No se encontraron proveedores")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.data.filas, key = { it.id }) { proveedor ->
                                ListItemCard(onClick = { onVerProveedor(proveedor.id) }) {
                                    Text(proveedor.nombre_completo, style = MaterialTheme.typography.bodyLarge)
                                    Text("DNI ${proveedor.dni}", color = EcoLactColors.TextoSecundario)
                                    StatusChip(
                                        proveedor.estado,
                                        if (proveedor.estado == "ACTIVO") ChipTone.EXITO else ChipTone.NEUTRAL,
                                        Modifier.padding(top = 4.dp),
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
