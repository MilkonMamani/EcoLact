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
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorComunicado
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository

class AcopiadorComunicadosViewModel(repository: AcopiadorRepository) :
    ResourceViewModel<List<AcopiadorComunicado>>({ repository.comunicados() })

@Composable
fun AcopiadorComunicadosScreen(viewModel: AcopiadorComunicadosViewModel, onVerComunicado: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                if (s.data.isEmpty()) {
                    EmptyBox("No hay comunicados")
                } else {
                    LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(s.data, key = { it.id }) { comunicado ->
                            ListItemCard(onClick = { onVerComunicado(comunicado.id) }) {
                                Text(comunicado.titulo, style = MaterialTheme.typography.titleMedium)
                                Text(comunicado.fecha_publicacion, color = EcoLactColors.TextoSecundario)
                            }
                        }
                    }
                }
            }
        }
    }
}

class AcopiadorComunicadoDetalleViewModel(repository: AcopiadorRepository, comunicadoId: Long) :
    ResourceViewModel<AcopiadorComunicado>({ repository.comunicadoDetalle(comunicadoId) })

@Composable
fun AcopiadorComunicadoDetalleScreen(viewModel: AcopiadorComunicadoDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text(data.titulo, style = MaterialTheme.typography.titleLarge)
                Text(data.fecha_publicacion, color = EcoLactColors.TextoSecundario)
                data.contenido?.let { Text(it, style = MaterialTheme.typography.bodyLarge) }
            }
        }
    }
}
