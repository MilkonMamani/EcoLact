package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Column
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadProveedorDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.components.ResultadoChip
import pe.edu.upeu.ecolactmobile.feature.calidad.components.diaMesHora
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

class CalidadProveedorDetalleViewModel(repository: CalidadRepository, proveedorId: Long) :
    ResourceViewModel<CalidadProveedorDetalleResponse>({ repository.proveedorDetalle(proveedorId) })

@Composable
fun CalidadProveedorDetalleScreen(viewModel: CalidadProveedorDetalleViewModel) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val data = s.data
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido()) {
                    Text(data.proveedor.nombre_completo, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                    Text("DNI ${data.proveedor.dni}", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                    if (data.proveedor.advertencias_count > 0) StatusChip("${data.proveedor.advertencias_count} advertencias", Tono.ATENCION)
                    if (s.fromCache) DatosGuardadosNote(s.cachedAtIso)
                }
            }
            if (data.analisis.isNotEmpty()) {
                item { SectionHeader("Últimos análisis") }
                item {
                    SummaryCard {
                        data.analisis.forEachIndexed { i, analisis ->
                            SummaryRowSlot(diaMesHora(analisis.fecha_analisis), primera = i == 0) { ResultadoChip(analisis.resultado) }
                        }
                    }
                }
            }
        }
    }
}
