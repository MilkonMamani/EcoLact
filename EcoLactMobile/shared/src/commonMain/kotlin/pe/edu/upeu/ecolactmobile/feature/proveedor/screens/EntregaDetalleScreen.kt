package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.proveedor.EntregaDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoEntrega
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoResultado

class ProveedorEntregaDetalleViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>, entregaId: Long) :
    LocalFirstViewModel<EntregaDetalleResponse>(
        source = repository.observeEntregaDetalle(entregaId),
        isOnline = isOnline,
        refresher = { repository.refreshEntregaDetalle(entregaId) },
        refreshOnStart = true,
    )

@Composable
fun ProveedorEntregaDetalleScreen(viewModel: ProveedorEntregaDetalleViewModel, onVerAnalisis: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val entrega = s.data.entrega
        val analisis = s.data.analisis
        val estado = estadoEntrega(entrega.estado, analisis?.resultado)

        ProveedorPantalla {
            item {
                SummaryCard {
                    SummaryRow("Fecha", formatFecha(entrega.fecha_hora), primera = true)
                    SummaryRow("Hora", formatHora(entrega.fecha_hora))
                    SummaryRow("Litros", formatLitros(entrega.litros), destacado = true)
                    entrega.zona_nombre?.let { SummaryRow("Zona", it) }
                    entrega.acopiador_nombre?.let { SummaryRow("Acopiador", it) }
                    SummaryRowSlot("Estado") { StatusChip(estado.label, estado.tono) }
                }
            }

            if (analisis != null) {
                item {
                    Column(Modifier.anchoContenido()) {
                        SectionHeader("Resultado de calidad")
                        val resultado = estadoResultado(analisis.resultado)
                        SummaryCard {
                            SummaryRowSlot("Resultado", primera = true) { StatusChip(resultado.label, resultado.tono) }
                            SummaryRow("Analizado", formatFecha(analisis.fecha_analisis))
                        }
                        OutlinedButton(
                            onClick = { onVerAnalisis(analisis.id) },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.outlinedButtonColors(contentColor = EcoLactColors.VerdeOscuro),
                            modifier = Modifier.fillMaxWidth().padding(top = 12.dp).heightIn(min = 48.dp),
                        ) {
                            Text("Ver análisis")
                        }
                    }
                }
            }

        }
    }
}
