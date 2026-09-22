package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import pe.edu.upeu.ecolactmobile.core.sync.SyncQueueRepository
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.SyncStatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.LotePendienteView
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository

class LotePendienteDetalleViewModel(private val repository: PlantaRepository, private val localId: String) : ScreenModel() {
    private val _item = MutableStateFlow<LotePendienteView?>(null)
    val item: StateFlow<LotePendienteView?> = _item.asStateFlow()

    private val _deleted = MutableStateFlow(false)
    val deleted: StateFlow<Boolean> = _deleted.asStateFlow()

    init {
        _item.value = repository.lotesPendientesDeSincronizar().firstOrNull { it.localId == localId }
    }

    fun eliminar(syncQueue: SyncQueueRepository) {
        syncQueue.delete(localId)
        _deleted.value = true
    }
}

@Composable
fun LotePendienteDetalleScreen(viewModel: LotePendienteDetalleViewModel, syncQueue: SyncQueueRepository, onEliminado: () -> Unit) {
    val item by viewModel.item.collectAsState()
    val deleted by viewModel.deleted.collectAsState()

    LaunchedEffect(deleted) {
        if (deleted) onEliminado()
    }

    val current = item
    if (current == null) {
        EmptyBox("Este registro ya no está disponible")
        return
    }

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("${current.payload.litros_utilizados} L", style = MaterialTheme.typography.titleLarge)
        SyncStatusChip(current.status)
        current.errorMessage?.let { Text(it, color = EcoLactColors.Rojo) }
        current.payload.observacion?.let { Text(it, color = EcoLactColors.TextoSecundario) }

        if (current.status == "PENDING" || current.status == "NEEDS_REVIEW") {
            OutlinedButton(onClick = { viewModel.eliminar(syncQueue) }, modifier = Modifier.fillMaxWidth()) {
                Text("Eliminar registro", color = EcoLactColors.Rojo)
            }
        }
    }
}
