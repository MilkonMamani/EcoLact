package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
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
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiCard
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiRow
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorDashboard
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.PendingEntregaView

data class AcopiadorInicioData(val dashboard: AcopiadorDashboard, val pendientes: List<PendingEntregaView>)

class AcopiadorInicioViewModel(private val repository: AcopiadorRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<AcopiadorInicioData>>(UiState.Loading)
    val state: StateFlow<UiState<AcopiadorInicioData>> = _state.asStateFlow()

    init { load() }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                val pendientes = repository.entregasPendientes()
                when (val outcome = repository.dashboard()) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(AcopiadorInicioData(outcome.data, pendientes), fromCache = false)
                    is CacheOutcome.Stale -> _state.value =
                        UiState.Success(AcopiadorInicioData(outcome.data, pendientes), fromCache = true, cachedAtIso = outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudo cargar el inicio")
            }
        }
    }
}

@Composable
fun AcopiadorInicioScreen(
    viewModel: AcopiadorInicioViewModel,
    onRegistrar: () -> Unit,
    onVerEntregaPendiente: (String) -> Unit,
    onVerEntrega: (Long) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load)
        is UiState.Success -> {
            val data = s.data
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)

                        KpiRow {
                            KpiCard("🥛", "${data.dashboard.kpis.litros} L", "Recolectados hoy", Modifier.weight(1f))
                            KpiCard("🚚", "${data.dashboard.kpis.entregas}", "Entregas hoy", Modifier.weight(1f))
                        }

                        if (data.pendientes.isNotEmpty()) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .background(EcoLactColors.AmarilloClaro, RoundedCornerShape(12.dp))
                                    .padding(12.dp),
                            ) {
                                Text(
                                    "↻ ${data.pendientes.size} registros pendientes de sincronización",
                                    color = EcoLactColors.TextoPrincipal,
                                )
                            }
                        }

                        Button(
                            onClick = onRegistrar,
                            modifier = Modifier.fillMaxWidth().height(72.dp),
                            shape = RoundedCornerShape(16.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                        ) {
                            Text("＋ REGISTRAR ENTREGA", style = MaterialTheme.typography.titleMedium)
                        }
                    }
                }

                if (data.pendientes.isNotEmpty()) {
                    item { SectionTitle("Pendientes de sincronización") }
                    items(data.pendientes, key = { it.localId }) { pendiente ->
                        ListItemCard(onClick = { onVerEntregaPendiente(pendiente.localId) }) {
                            Text("${pendiente.request.litros} L", style = MaterialTheme.typography.titleMedium)
                            SyncStatusChip(pendiente.status, Modifier.padding(top = 4.dp))
                        }
                    }
                }

                if (data.dashboard.ultimas_entregas.isNotEmpty()) {
                    item { SectionTitle("Últimas entregas") }
                    items(data.dashboard.ultimas_entregas, key = { it.id }) { entrega ->
                        ListItemCard(onClick = { onVerEntrega(entrega.id) }) {
                            Text("${entrega.litros} L", style = MaterialTheme.typography.titleMedium)
                            Text(entrega.fecha_hora, color = EcoLactColors.TextoSecundario)
                            val resultado = data.dashboard.resultados_por_entrega[entrega.id.toString()]
                            ResultadoCalidadChip(resultado, Modifier.padding(top = 4.dp))
                        }
                    }
                }
            }
        }
    }
}
