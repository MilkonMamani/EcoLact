package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.feature.calidad.AnalisisSyncPendiente
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository

class CalidadSincronizacionViewModel(
    private val repository: CalidadRepository,
    private val syncManager: SyncManager,
    connectivityObserver: ConnectivityObserver,
) : ScreenModel() {
    val isOnline: StateFlow<Boolean> = connectivityObserver.isOnline

    private val _pendientes = MutableStateFlow<List<AnalisisSyncPendiente>>(emptyList())
    val pendientes: StateFlow<List<AnalisisSyncPendiente>> = _pendientes.asStateFlow()

    private val _sincronizando = MutableStateFlow(false)
    val sincronizando: StateFlow<Boolean> = _sincronizando.asStateFlow()

    init { load() }

    fun load() {
        _pendientes.value = repository.analisisPendientesDeSincronizar()
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
fun CalidadSincronizacionScreen(viewModel: CalidadSincronizacionViewModel) {
    val isOnline by viewModel.isOnline.collectAsState()
    val pendientes by viewModel.pendientes.collectAsState()
    val sincronizando by viewModel.sincronizando.collectAsState()

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text(if (isOnline) "● En línea" else "● Sin conexión", style = MaterialTheme.typography.titleMedium)
        Text("${pendientes.size} análisis pendientes de sincronizar", color = EcoLactColors.TextoSecundario)

        Button(
            onClick = viewModel::sincronizarAhora,
            enabled = isOnline && !sincronizando,
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (sincronizando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            else Text("Sincronizar ahora")
        }

        if (pendientes.isEmpty()) {
            EmptyBox("Todo sincronizado")
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(pendientes, key = { it.localId }) { pendiente ->
                    ListItemCard {
                        Text("Entrega #${pendiente.entregaId}", style = MaterialTheme.typography.bodyLarge)
                        SyncStatusChip(pendiente.status, Modifier.padding(top = 4.dp))
                        pendiente.errorMessage?.let { Text(it, color = EcoLactColors.Rojo) }
                    }
                }
            }
        }
    }
}
