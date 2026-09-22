package pe.edu.upeu.ecolactmobile.feature.notificaciones

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors

@Composable
fun NotificacionesScreen(viewModel: NotificacionesViewModel, onBack: () -> Unit) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = { TopAppBar(title = { Text("Notificaciones") }) },
    ) { padding ->
        when (val s = state) {
            is UiState.Loading -> LoadingBox(Modifier.padding(padding))
            is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::load, modifier = Modifier.padding(padding))
            is UiState.Success -> {
                Column(modifier = Modifier.fillMaxSize().padding(padding).padding(16.dp)) {
                    if (s.fromCache) {
                        OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                    }
                    if (s.data.isEmpty()) {
                        EmptyBox("No tienes notificaciones")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.data, key = { it.id }) { item ->
                                ListItemCard(onClick = { viewModel.marcarLeida(item.id) }) {
                                    Text(
                                        item.titulo,
                                        style = MaterialTheme.typography.titleMedium,
                                        color = if (item.leido) EcoLactColors.TextoSecundario else EcoLactColors.TextoPrincipal,
                                    )
                                    Text(item.mensaje, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
