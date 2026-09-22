package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

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
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.SeguimientoDetalleResponse

class SeguimientoDetalleViewModel(repository: AcopiadorRepository, entregaId: Long) :
    ResourceViewModel<SeguimientoDetalleResponse>({ repository.seguimientoDetalle(entregaId) })

@Composable
fun SeguimientoDetalleScreen(viewModel: SeguimientoDetalleViewModel) {
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
                Text("Estado de la entrega: ${data.estado}", style = MaterialTheme.typography.titleMedium)
                if (data.analisis != null) {
                    ResultadoCalidadChip(data.analisis.resultado)
                    data.analisis.fecha_analisis?.let { Text("Analizado: $it", color = EcoLactColors.TextoSecundario) }
                    data.analisis.observacion?.let { Text(it) }
                } else {
                    Text("Todavía no tiene análisis registrado.", color = EcoLactColors.TextoSecundario)
                }
            }
        }
    }
}
