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
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.EntregaDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorEntregaDetalleViewModel(repository: ProveedorRepository, entregaId: Long) :
    ResourceViewModel<EntregaDetalleResponse>({ repository.entregaDetalle(entregaId) })

@Composable
fun ProveedorEntregaDetalleScreen(viewModel: ProveedorEntregaDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)

                Text("${data.entrega.litros} L", style = MaterialTheme.typography.titleLarge)
                Text(data.entrega.fecha_hora, color = EcoLactColors.TextoSecundario)
                Text("Estado: ${data.entrega.estado}", color = EcoLactColors.TextoSecundario)

                Column {
                    SectionTitle("Calidad")
                    if (data.analisis != null) {
                        ResultadoCalidadChip(data.analisis.resultado)
                        Text(
                            "Analizado: ${data.analisis.fecha_analisis}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = EcoLactColors.TextoSecundario,
                        )
                    } else {
                        ResultadoCalidadChip(null)
                    }
                }
            }
        }
    }
}
