package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.ComunicadoDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

/** Abrir esta pantalla ya marca el comunicado como leído en el backend (efecto secundario del GET, ver proveedor.md 6). */
class ProveedorComunicadoDetalleViewModel(repository: ProveedorRepository, comunicadoId: Long) :
    ResourceViewModel<ComunicadoDetalleResponse>({ repository.comunicadoDetalle(comunicadoId) })

@Composable
fun ProveedorComunicadoDetalleScreen(viewModel: ProveedorComunicadoDetalleViewModel) {
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
                Text(data.contenido, style = MaterialTheme.typography.bodyLarge)
            }
        }
    }
}
