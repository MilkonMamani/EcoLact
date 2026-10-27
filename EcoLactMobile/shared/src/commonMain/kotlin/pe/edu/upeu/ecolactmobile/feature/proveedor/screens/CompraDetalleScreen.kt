package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.feature.proveedor.CompraDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.FORMA_PAGO_DESCUENTO
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow

class ProveedorCompraDetalleViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>, compraId: Long) :
    LocalFirstViewModel<CompraDetalleResponse>(
        source = repository.observeCompraDetalle(compraId),
        isOnline = isOnline,
        refresher = { repository.refreshCompraDetalle(compraId) },
        refreshOnStart = true,
    )

@Composable
fun ProveedorCompraDetalleScreen(viewModel: ProveedorCompraDetalleViewModel, onVerPago: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val data = s.data
        ProveedorPantalla {
            item {
                SummaryCard {
                    SummaryRow("Producto", data.producto_nombre ?: "Producto", primera = true)
                    SummaryRow("Cantidad", formatUnidades(data.cantidad))
                    SummaryRow("Precio unitario", formatSoles(data.precio_unitario))
                    SummaryRow("Total", formatSoles(data.importe_total), destacado = true)
                    SummaryRow("Forma de pago", formaPagoLabel(data.forma_pago))
                    SummaryRow("Fecha", formatFecha(data.fecha))
                }
            }
            val liquidacionId = data.liquidacion_relacionada_id
            if (data.forma_pago == FORMA_PAGO_DESCUENTO && liquidacionId != null) {
                item {
                    ProviderListItem(
                        primera = true,
                        ultima = true,
                        onClick = { onVerPago(liquidacionId) },
                    ) {
                        ItemTitulo("Ver el pago donde se descontó")
                    }
                }
            }
        }
    }
}
