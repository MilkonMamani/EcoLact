package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
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
import pe.edu.upeu.ecolactmobile.core.ui.components.ChipTone
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiCard
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiRow
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.components.StatusChip
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.LiquidacionesListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorPagosViewModel(repository: ProveedorRepository) :
    ResourceViewModel<LiquidacionesListResponse>({ repository.liquidaciones() })

private fun estadoTone(estado: String) = when (estado) {
    "PAGADO" -> ChipTone.EXITO
    "PENDIENTE" -> ChipTone.ADVERTENCIA
    else -> ChipTone.NEUTRAL
}

@Composable
fun ProveedorPagosScreen(viewModel: ProveedorPagosViewModel, onVerLiquidacion: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                item {
                    if (s.fromCache) OfflineBanner(s.cachedAtIso)
                }
                data.resumen?.let { resumen ->
                    item {
                        KpiRow {
                            KpiCard("💰", "S/ ${resumen.por_cobrar}", "Pendiente de pago", Modifier.weight(1f))
                            KpiCard("✅", "S/ ${resumen.pagado}", "Pagado este mes", Modifier.weight(1f))
                        }
                    }
                }
                item { SectionTitle("Historial", Modifier.fillMaxWidth()) }
                if (data.filas.isEmpty()) {
                    item { EmptyBox("Aún no tienes liquidaciones") }
                } else {
                    items(data.filas, key = { it.id }) { liquidacion ->
                        ListItemCard(onClick = { onVerLiquidacion(liquidacion.id) }) {
                            Text("S/ ${liquidacion.total_pagar}", style = MaterialTheme.typography.titleMedium)
                            liquidacion.fecha_calculo?.let {
                                Text(it, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                            }
                            StatusChip(liquidacion.estado, estadoTone(liquidacion.estado), Modifier.padding(top = 4.dp))
                        }
                    }
                }
            }
        }
    }
}
