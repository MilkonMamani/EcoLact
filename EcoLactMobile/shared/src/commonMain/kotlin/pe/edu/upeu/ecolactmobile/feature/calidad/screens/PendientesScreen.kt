package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.AnalisisSyncPendiente
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.PendienteEntregaFila

data class PendienteRowState(
    val fila: PendienteEntregaFila,
    val borradorLocal: Boolean,
    val syncPendiente: AnalisisSyncPendiente?,
)

class CalidadPendientesViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<List<PendienteRowState>>>(UiState.Loading)
    val state: StateFlow<UiState<List<PendienteRowState>>> = _state.asStateFlow()

    init { load() }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                val syncPendientes = repository.analisisPendientesDeSincronizar()
                val syncByEntrega = syncPendientes.associateBy { it.entregaId }
                when (val outcome = repository.pendientes()) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(mapRows(outcome.data.filas, syncByEntrega), false)
                    is CacheOutcome.Stale ->
                        _state.value = UiState.Success(mapRows(outcome.data.filas, syncByEntrega), true, outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudieron cargar los pendientes")
            }
        }
    }

    private fun mapRows(filas: List<PendienteEntregaFila>, syncByEntrega: Map<Long, AnalisisSyncPendiente>) =
        filas.map { fila ->
            PendienteRowState(
                fila = fila,
                borradorLocal = repository.obtenerBorradorLocal(fila.id) != null,
                syncPendiente = syncByEntrega[fila.id],
            )
        }
}

@Composable
fun CalidadPendientesScreen(viewModel: CalidadPendientesViewModel, onAnalizar: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load)
        is UiState.Success -> {
            Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                if (s.data.isEmpty()) {
                    EmptyBox("No hay entregas pendientes de análisis")
                } else {
                    LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(s.data, key = { it.fila.id }) { row ->
                            ListItemCard(onClick = { onAnalizar(row.fila.id) }) {
                                Text(row.fila.proveedorNombre ?: "Proveedor", style = MaterialTheme.typography.titleMedium)
                                Text("${row.fila.litros} L · ${row.fila.zonaNombre ?: ""}", color = EcoLactColors.TextoSecundario)
                                Text(row.fila.fecha, color = EcoLactColors.TextoSecundario)
                                when {
                                    row.syncPendiente != null -> SyncStatusChip(row.syncPendiente.status, Modifier.padding(top = 4.dp))
                                    row.borradorLocal -> Text("🧪 Análisis iniciado · guardado localmente", color = EcoLactColors.AzulInstitucional)
                                    else -> Text("🟡 Pendiente de análisis", color = EcoLactColors.Amarillo)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
