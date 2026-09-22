package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadProveedorDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository

class CalidadProveedorDetalleViewModel(repository: CalidadRepository, proveedorId: Long) :
    ResourceViewModel<CalidadProveedorDetalleResponse>({ repository.proveedorDetalle(proveedorId) })

@Composable
fun CalidadProveedorDetalleScreen(viewModel: CalidadProveedorDetalleViewModel) {
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
                if (data.proveedor.advertencias_count > 0) {
                    Text("⚠ ${data.proveedor.advertencias_count} advertencias", color = EcoLactColors.Amarillo)
                }

                if (data.analisis.isNotEmpty()) {
                    SectionTitle("Últimos análisis")
                    data.analisis.forEach { analisis ->
                        Text(analisis.fecha_analisis, color = EcoLactColors.TextoSecundario)
                        ResultadoCalidadChip(analisis.resultado, Modifier.padding(bottom = 4.dp))
                    }
                }
            }
        }
    }
}
