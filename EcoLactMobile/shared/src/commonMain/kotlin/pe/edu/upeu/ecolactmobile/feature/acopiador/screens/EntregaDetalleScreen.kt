package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorEntregaDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository

class AcopiadorEntregaDetalleViewModel(repository: AcopiadorRepository, entregaId: Long) :
    ResourceViewModel<AcopiadorEntregaDetalleResponse>({ repository.entregaDetalle(entregaId) })

/** El acopiador no ve los parámetros técnicos del análisis (acopiador.txt sección 6): solo el resultado, con un enlace a Seguimiento para más detalle. */
@Composable
fun AcopiadorEntregaDetalleScreen(viewModel: AcopiadorEntregaDetalleViewModel, onVerSeguimiento: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val entrega = s.data.entrega
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text("${entrega.litros} L", style = MaterialTheme.typography.titleLarge)
                Text(entrega.fecha_hora, color = EcoLactColors.TextoSecundario)
                Text("Método: ${entrega.metodo_identificacion}", color = EcoLactColors.TextoSecundario)
                Text("Estado: ${entrega.estado}", color = EcoLactColors.TextoSecundario)
                entrega.observacion?.let { Text("Observación: $it", color = EcoLactColors.TextoSecundario) }

                Column {
                    Text("CALIDAD", color = EcoLactColors.TextoSecundario)
                    ResultadoCalidadChip(s.data.analisis, Modifier.padding(top = 4.dp))
                    if (s.data.analisis != null) {
                        TextButton(onClick = { onVerSeguimiento(entrega.id) }) {
                            Text("Ver resultado completo →")
                        }
                    }
                }
            }
        }
    }
}
