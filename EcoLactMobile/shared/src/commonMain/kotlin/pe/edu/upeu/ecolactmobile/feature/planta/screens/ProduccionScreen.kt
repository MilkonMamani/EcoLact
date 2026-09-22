package pe.edu.upeu.ecolactmobile.feature.planta.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.LotePendienteView
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProduccionListFila

enum class ProduccionFiltro(val label: String, val param: String?) {
    TODOS("Todos", null),
    EN_PROCESO("Activos", "EN_PROCESO"),
    FINALIZADOS("Finalizados", "FINALIZADO"),
}

data class ProduccionData(val pendientes: List<LotePendienteView>, val filas: List<ProduccionListFila>)

class PlantaProduccionViewModel(private val repository: PlantaRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<ProduccionData>>(UiState.Loading)
    val state: StateFlow<UiState<ProduccionData>> = _state.asStateFlow()

    val filtro = MutableStateFlow(ProduccionFiltro.TODOS)

    init { load() }

    fun onFiltroChange(value: ProduccionFiltro) {
        filtro.value = value
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                val pendientes = repository.lotesPendientesDeSincronizar()
                when (val outcome = repository.produccion(filtro.value.param)) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(ProduccionData(pendientes, outcome.data.filas), false)
                    is CacheOutcome.Stale ->
                        _state.value = UiState.Success(ProduccionData(pendientes, outcome.data.filas), true, outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudo cargar la producción")
            }
        }
    }
}

@Composable
fun PlantaProduccionScreen(
    viewModel: PlantaProduccionViewModel,
    onVerLote: (Long) -> Unit,
    onVerLotePendiente: (String) -> Unit,
) {
    val state by viewModel.state.collectAsState()
    val filtroActual by viewModel.filtro.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            ProduccionFiltro.entries.forEach { filtro ->
                FilterChip(selected = filtro == filtroActual, onClick = { viewModel.onFiltroChange(filtro) }, label = { Text(filtro.label) })
            }
        }

        when (val s = state) {
            is UiState.Loading -> LoadingBox()
            is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load)
            is UiState.Success -> {
                Column(modifier = Modifier.padding(top = 12.dp)) {
                    if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                    if (s.data.pendientes.isEmpty() && s.data.filas.isEmpty()) {
                        EmptyBox("No hay lotes de producción")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.data.pendientes, key = { "pending-${it.localId}" }) { pendiente ->
                                ListItemCard(onClick = { onVerLotePendiente(pendiente.localId) }) {
                                    Text("Lote en proceso (${pendiente.payload.litros_utilizados} L)", style = MaterialTheme.typography.titleMedium)
                                    SyncStatusChip(pendiente.status, Modifier.padding(top = 4.dp))
                                }
                            }
                            items(s.data.filas, key = { it.id }) { fila ->
                                ListItemCard(onClick = { onVerLote(fila.id) }) {
                                    Text(fila.codigo, style = MaterialTheme.typography.titleMedium)
                                    Text("${fila.litros} L", color = EcoLactColors.TextoSecundario)
                                    Text("● ${fila.estado}", color = EcoLactColors.AzulInstitucional)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
