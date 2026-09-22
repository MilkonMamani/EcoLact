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
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.components.StatusChip
import pe.edu.upeu.ecolactmobile.core.ui.components.ChipTone
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.LiquidacionDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorLiquidacionDetalleViewModel(repository: ProveedorRepository, liquidacionId: Long) :
    ResourceViewModel<LiquidacionDetalleResponse>({ repository.liquidacionDetalle(liquidacionId) })

@Composable
fun ProveedorLiquidacionDetalleScreen(viewModel: ProveedorLiquidacionDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            val liq = data.liquidacion
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)

                        Text("Litros entregados", color = EcoLactColors.TextoSecundario)
                        Text("${liq.total_litros_entregados} L", style = MaterialTheme.typography.titleMedium)

                        Text("Precio aplicado", color = EcoLactColors.TextoSecundario)
                        Text("S/ ${liq.precio_litro_aplicado}/L", style = MaterialTheme.typography.bodyLarge)

                        Text("Subtotal", color = EcoLactColors.TextoSecundario)
                        Text("S/ ${liq.subtotal}", style = MaterialTheme.typography.bodyLarge)

                        if (liq.total_ajustes != 0.0) {
                            Text("Ajustes de calidad", color = EcoLactColors.TextoSecundario)
                            Text("S/ ${liq.total_ajustes}", style = MaterialTheme.typography.bodyLarge)
                        }

                        Text("TOTAL", color = EcoLactColors.TextoSecundario)
                        Text("S/ ${liq.total_pagar}", style = MaterialTheme.typography.titleLarge)

                        StatusChip(
                            liq.estado,
                            if (liq.estado == "PAGADO") ChipTone.EXITO else ChipTone.ADVERTENCIA,
                        )

                        if (data.incidencia != null) {
                            StatusChip("⚠ Con incidencia", ChipTone.ERROR)
                        }
                    }
                }

                if (data.entregas.isNotEmpty()) {
                    item { SectionTitle("Entregas incluidas") }
                    items(data.entregas, key = { it.id }) { entrega ->
                        Text("${entrega.fecha_hora}   ${entrega.litros} L")
                    }
                }

                if (data.ajustes.isNotEmpty()) {
                    item { SectionTitle("Ajustes") }
                    items(data.ajustes) { ajuste ->
                        Text("${ajuste.descripcion ?: ajuste.tipo}: S/ ${ajuste.monto}")
                    }
                }

                if (data.pagos.isNotEmpty()) {
                    item { SectionTitle("Pagos") }
                    items(data.pagos) { pago ->
                        Text("${pago.fecha_pago ?: "—"}   S/ ${pago.monto}   ${pago.estado}")
                    }
                }
            }
        }
    }
}
