package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.HistorialListResponse

enum class ResultadoFiltro(val label: String, val param: String?) {
    TODAS("Todas", null),
    ACEPTADAS("Aceptadas", "ACEPTADA"),
    OBSERVADAS("Observadas", "OBSERVADA"),
    RECHAZADAS("Rechazadas", "RECHAZADA"),
}

class CalidadHistorialViewModel(private val repository: CalidadRepository) : ScreenModel() {
    private val _state = MutableStateFlow<UiState<HistorialListResponse>>(UiState.Loading)
    val state: StateFlow<UiState<HistorialListResponse>> = _state.asStateFlow()

    val filtro = MutableStateFlow(ResultadoFiltro.TODAS)

    init { load() }

    fun onFiltroChange(value: ResultadoFiltro) {
        filtro.value = value
        load()
    }

    fun load() {
        scope.launch {
            _state.value = UiState.Loading
            try {
                when (val outcome = repository.historial(resultado = filtro.value.param)) {
                    is CacheOutcome.Fresh -> _state.value = UiState.Success(outcome.data, false)
                    is CacheOutcome.Stale -> _state.value = UiState.Success(outcome.data, true, outcome.cachedAtIso)
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "No se pudo cargar el historial")
            }
        }
    }
}

@Composable
fun CalidadHistorialScreen(viewModel: CalidadHistorialViewModel, onVerDetalle: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtroActual by viewModel.filtro.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            ResultadoFiltro.entries.forEach { filtro ->
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
                        EmptyBox("No hay análisis en este filtro")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.data.filas, key = { it.id }) { fila ->
                                ListItemCard(onClick = { onVerDetalle(fila.id) }) {
                                    Text(fila.proveedorNombre ?: "Proveedor", style = MaterialTheme.typography.titleMedium)
                                    Text("${fila.zonaNombre ?: ""} · ${fila.fecha}", color = EcoLactColors.TextoSecundario)
                                    ResultadoCalidadChip(fila.resultado, Modifier.padding(top = 4.dp))
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
