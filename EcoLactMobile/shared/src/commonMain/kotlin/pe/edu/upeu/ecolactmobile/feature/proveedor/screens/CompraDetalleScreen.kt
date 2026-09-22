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
import pe.edu.upeu.ecolactmobile.core.ui.state.ResourceViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.CompraDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository

class ProveedorCompraDetalleViewModel(repository: ProveedorRepository, compraId: Long) :
    ResourceViewModel<CompraDetalleResponse>({ repository.compraDetalle(compraId) })

@Composable
fun ProveedorCompraDetalleScreen(viewModel: ProveedorCompraDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    when (val s = state) {
        is UiState.Loading -> LoadingBox()
        is UiState.Error -> ErrorBox(s.message, onRetry = viewModel::refresh)
        is UiState.Success -> {
            val data = s.data
            Column(
                modifier = Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                if (s.fromCache) OfflineBanner(s.cachedAtIso)
                Text(data.producto_nombre ?: "Producto #${data.producto_id}", style = MaterialTheme.typography.titleLarge)
                Text("${data.cantidad} unidades × S/ ${data.precio_unitario}", color = EcoLactColors.TextoSecundario)
                Text("Total: S/ ${data.importe_total}", style = MaterialTheme.typography.titleMedium)
                Text("Forma de pago: ${data.forma_pago}", color = EcoLactColors.TextoSecundario)
                Text(data.fecha, color = EcoLactColors.TextoSecundario)
                if (data.forma_pago == "DESCUENTO_PLANILLA" && data.liquidacion_relacionada_id != null) {
                    Text(
                        "Con cargo a la liquidación #${data.liquidacion_relacionada_id}",
                        color = EcoLactColors.AzulInstitucional,
                    )
                }
            }
        }
    }
}
