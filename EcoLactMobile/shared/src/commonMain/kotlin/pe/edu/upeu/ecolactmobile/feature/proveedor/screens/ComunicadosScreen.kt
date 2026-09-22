package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

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
import pe.edu.upeu.ecolactmobile.feature.proveedor.ComunicadosListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorComunicadosViewModel(repository: ProveedorRepository) :
    ResourceViewModel<ComunicadosListResponse>({ repository.comunicados() })

@Composable
fun ProveedorComunicadosScreen(viewModel: ProveedorComunicadosViewModel, onVerComunicado: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso, Modifier.padding(bottom = 12.dp))
                if (data.filas.isEmpty()) {
                    EmptyBox("No hay comunicados")
                } else {
                    LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(data.filas, key = { it.id }) { comunicado ->
                            ListItemCard(onClick = { onVerComunicado(comunicado.id) }) {
                                Text(
                                    comunicado.titulo,
                                    style = MaterialTheme.typography.titleMedium,
                                    color = if (comunicado.leido == false) EcoLactColors.TextoPrincipal else EcoLactColors.TextoSecundario,
                                )
                                Text(comunicado.fecha_publicacion, color = EcoLactColors.TextoSecundario)
                                if (comunicado.leido == false) {
                                    Text("● Nuevo", color = EcoLactColors.AzulInstitucional)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
