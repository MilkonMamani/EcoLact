package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
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
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.EntregasListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

/** Filtro soportado por el backend: `estado` (PENDIENTE_ANALISIS | ANALIZADA). No hay tabs de periodo ni búsqueda en proveedor.md para este endpoint. */
enum class EntregaFiltro(val label: String, val estadoParam: String?) {
    TODAS("Todas", null),
    PENDIENTES("Pendientes", "PENDIENTE_ANALISIS"),
    ANALIZADAS("Analizadas", "ANALIZADA"),
}

class ProveedorEntregasViewModel(private val repository: ProveedorRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<EntregasListResponse>>(UiState.Loading)
    val state: StateFlow<UiState<EntregasListResponse>> = _state.asStateFlow()

    var filtro = MutableStateFlow(EntregaFiltro.TODAS)
        private set

    init {
        load()
    }

    fun onFiltroChange(filtro: EntregaFiltro) {
        this.filtro.value = filtro
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = repository.entregas(filtro.value.estadoParam)) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(outcome.data, fromCache = false)
                    is CacheOutcome.Stale -> _state.value =
                        UiState.Success(outcome.data, fromCache = true, cachedAtIso = outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudieron cargar las entregas")
            }
        }
    }
}

@Composable
fun ProveedorEntregasScreen(viewModel: ProveedorEntregasViewModel, onVerEntrega: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtroActual by viewModel.filtro.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            EntregaFiltro.entries.forEach { filtro ->
                FilterChip(
                    selected = filtro == filtroActual,
                    onClick = { viewModel.onFiltroChange(filtro) },
                    label = { Text(filtro.label) },
                )
            }
        }

        when (val s = state) {
            is UiState.Loading -> LoadingBox()
            is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load)
            is UiState.Success -> {
                Column(modifier = Modifier.padding(top = 12.dp)) {
                    if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                    if (s.data.filas.isEmpty()) {
                        EmptyBox("No tienes entregas registradas")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.data.filas, key = { it.id }) { entrega ->
                                ListItemCard(onClick = { onVerEntrega(entrega.id) }) {
                                    Text(entrega.fecha_hora, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                                    Text("${entrega.litros} L", style = MaterialTheme.typography.titleMedium)
                                    ResultadoCalidadChip(entrega.resultado, Modifier.padding(top = 4.dp))
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
