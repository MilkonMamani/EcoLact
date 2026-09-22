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
import pe.edu.upeu.ecolactmobile.core.ui.components.EmptyBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiCard
import pe.edu.upeu.ecolactmobile.core.ui.components.KpiRow
import pe.edu.upeu.ecolactmobile.core.ui.components.ListItemCard
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.OfflineBanner
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.ComprasListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorMisComprasViewModel(repository: ProveedorRepository) :
    ResourceViewModel<ComprasListResponse>({ repository.compras() })

@Composable
fun ProveedorMisComprasScreen(viewModel: ProveedorMisComprasViewModel, onVerCompra: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (s.fromCache) OfflineBanner(s.cachedAtIso)
                        KpiRow {
                            KpiCard("🛒", "${data.kpis.compras}", "Compras", Modifier.weight(1f))
                            KpiCard("💰", "S/ ${data.kpis.total_comprado}", "Total comprado", Modifier.weight(1f))
                        }
                    }
                }
                if (data.filas.isEmpty()) {
                    item { EmptyBox("No tienes compras registradas") }
                } else {
                    items(data.filas, key = { it.id }) { compra ->
                        ListItemCard(onClick = { onVerCompra(compra.id) }) {
                            Text(compra.producto_nombre ?: "Producto #${compra.producto_id}", style = MaterialTheme.typography.bodyLarge)
                            Text(
                                "${compra.cantidad} × S/ ${compra.importe_total}",
                                color = EcoLactColors.TextoSecundario,
                            )
                            Text(compra.fecha, color = EcoLactColors.TextoSecundario)
                        }
                    }
                }
            }
        }
    }
}
