package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import pe.edu.upeu.ecolactmobile.core.sync.ConnectivityObserver
import pe.edu.upeu.ecolactmobile.core.sync.SyncManager
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.LotePendienteView
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ResultadoPendienteView

class PlantaSincronizacionViewModel(
    private val repository: PlantaRepository,
    private val syncManager: SyncManager,
    connectivityObserver: ConnectivityObserver,
) : ScreenModel() {
    val isOnline: StateFlow<Boolean> = connectivityObserver.isOnline

    private val _lotes = MutableStateFlow<List<LotePendienteView>>(emptyList())
    val lotes: StateFlow<List<LotePendienteView>> = _lotes.asStateFlow()

    private val _resultados = MutableStateFlow<List<ResultadoPendienteView>>(emptyList())
    val resultados: StateFlow<List<ResultadoPendienteView>> = _resultados.asStateFlow()

    private val _sincronizando = MutableStateFlow(false)
    val sincronizando: StateFlow<Boolean> = _sincronizando.asStateFlow()

    init { load() }

    fun load() {
        _lotes.value = repository.lotesPendientesDeSincronizar()
        _resultados.value = repository.resultadosPendientesDeSincronizar()
    }

    fun sincronizarAhora() {
        scope.launch {
            _sincronizando.value = true
            syncManager.syncNow()
            load()
            _sincronizando.value = false
        }
    }
}

@Composable
fun PlantaSincronizacionScreen(viewModel: PlantaSincronizacionViewModel) {
    val isOnline by viewModel.isOnline.collectAsState()
    val lotes by viewModel.lotes.collectAsState()
    val resultados by viewModel.resultados.collectAsState()
    val sincronizando by viewModel.sincronizando.collectAsState()

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text(if (isOnline) "● En línea" else "● Sin conexión", style = MaterialTheme.typography.titleMedium)
        Text("${lotes.size + resultados.size} operaciones pendientes", color = EcoLactColors.TextoSecundario)

        Button(
            onClick = viewModel::sincronizarAhora,
            enabled = isOnline && !sincronizando,
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (sincronizando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            else Text("Sincronizar ahora")
        }

        if (lotes.isEmpty() && resultados.isEmpty()) {
            EmptyBox("Sin operaciones pendientes")
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(lotes, key = { "lote-${it.localId}" }) { lote ->
                    ListItemCard {
                        Text("Iniciar lote: ${lote.payload.litros_utilizados} L", style = MaterialTheme.typography.bodyLarge)
                        SyncStatusChip(lote.status, Modifier.padding(top = 4.dp))
                        lote.errorMessage?.let { Text(it, color = EcoLactColors.Rojo) }
                    }
                }
                items(resultados, key = { "resultado-${it.localId}" }) { resultado ->
                    ListItemCard {
                        Text("Resultado del lote #${resultado.loteId}", style = MaterialTheme.typography.bodyLarge)
                        SyncStatusChip(resultado.status, Modifier.padding(top = 4.dp))
                        resultado.errorMessage?.let { Text(it, color = EcoLactColors.Rojo) }
                    }
                }
            }
        }
    }
}
