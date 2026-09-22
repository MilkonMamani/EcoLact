package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadProveedoresListResponse
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository

class CalidadProveedoresViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<CalidadProveedoresListResponse>>(UiState.Loading)
    val state: StateFlow<UiState<CalidadProveedoresListResponse>> = _state.asStateFlow()

    private val _q = MutableStateFlow("")
    val q: StateFlow<String> = _q.asStateFlow()

    init { load() }

    fun onQChange(value: String) {
        _q.value = value
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = repository.proveedores(q = _q.value.ifBlank { null })) {
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
fun CalidadProveedoresScreen(viewModel: CalidadProveedoresViewModel, onVerProveedor: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val q by viewModel.q.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        OutlinedTextField(
            value = q,
            onValueChange = viewModel::onQChange,
            label = { Text("Nombre o DNI") },
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
                                    Text(proveedor.nombre, style = MaterialTheme.typography.bodyLarge)
                                    Text("${proveedor.zonaNombre ?: ""} · DNI ${proveedor.dni}", color = EcoLactColors.TextoSecundario)
                                    if (proveedor.advertencias > 0) {
                                        Text("⚠ ${proveedor.advertencias} advertencias", color = EcoLactColors.Amarillo)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
