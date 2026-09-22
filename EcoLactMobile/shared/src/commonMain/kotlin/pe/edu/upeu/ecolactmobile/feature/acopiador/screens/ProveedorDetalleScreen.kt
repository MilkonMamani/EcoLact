package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
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
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorProveedorDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository

class AcopiadorProveedorDetalleViewModel(repository: AcopiadorRepository, proveedorId: Long) :
    ResourceViewModel<AcopiadorProveedorDetalleResponse>({ repository.proveedorDetalle(proveedorId) })

@Composable
fun AcopiadorProveedorDetalleScreen(viewModel: AcopiadorProveedorDetalleViewModel, onRegistrarEntrega: () -> Unit) {
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
                Text(data.proveedor.nombre_completo, style = MaterialTheme.typography.titleLarge)
                Text("DNI: ${data.proveedor.dni}", color = EcoLactColors.TextoSecundario)
                data.proveedor.telefono?.let { Text("Teléfono: $it", color = EcoLactColors.TextoSecundario) }
                Text("Estado: ${data.proveedor.estado}", color = EcoLactColors.TextoSecundario)

                Column {
                    Text("RESUMEN", color = EcoLactColors.TextoSecundario)
                    Text("${data.resumen.entregas_totales} entregas · ${data.resumen.litros_entregados} L")
                    data.resumen.ultima_entrega?.let { Text("Última entrega: $it", color = EcoLactColors.TextoSecundario) }
                    ResultadoCalidadChip(data.ultimo_analisis_resultado, Modifier.padding(top = 4.dp))
                }

                Button(
                    onClick = onRegistrarEntrega,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                ) {
                    Text("Registrar entrega")
                }
            }
        }
    }
}
