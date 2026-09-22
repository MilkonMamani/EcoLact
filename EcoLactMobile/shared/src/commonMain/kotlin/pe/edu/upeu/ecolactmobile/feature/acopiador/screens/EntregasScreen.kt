package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorEntrega
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.PendingEntregaView

data class AcopiadorEntregasData(val pendientes: List<PendingEntregaView>, val filas: List<AcopiadorEntrega>)

class AcopiadorEntregasViewModel(private val repository: AcopiadorRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<AcopiadorEntregasData>>(UiState.Loading)
    val state: StateFlow<UiState<AcopiadorEntregasData>> = _state.asStateFlow()

    init { load() }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                val pendientes = repository.entregasPendientes()
                when (val outcome = repository.entregas()) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(AcopiadorEntregasData(pendientes, outcome.data.filas), false)
                    is CacheOutcome.Stale -> _state.value =
                        UiState.Success(AcopiadorEntregasData(pendientes, outcome.data.filas), true, outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudieron cargar las entregas")
            }
        }
    }
}

@Composable
fun AcopiadorEntregasScreen(
    viewModel: AcopiadorEntregasViewModel,
    onVerEntrega: (Long) -> Unit,
    onVerEntregaPendiente: (String) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load)
        is UiState.Success -> {
            val data = s.data
            Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                if (data.pendientes.isEmpty() && data.filas.isEmpty()) {
                    EmptyBox("No tienes entregas registradas")
                } else {
                    LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(data.pendientes, key = { "pending-${it.localId}" }) { pendiente ->
                            ListItemCard(onClick = { onVerEntregaPendiente(pendiente.localId) }) {
                                Text("${pendiente.request.litros} L", style = MaterialTheme.typography.titleMedium)
                                SyncStatusChip(pendiente.status, Modifier.padding(top = 4.dp))
                            }
                        }
                        items(data.filas, key = { it.id }) { entrega ->
                            ListItemCard(onClick = { onVerEntrega(entrega.id) }) {
                                Text(entrega.fecha_hora, color = EcoLactColors.TextoSecundario)
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
