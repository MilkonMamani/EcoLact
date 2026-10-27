package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.util.formatDecimal
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.feature.proveedor.ComprasListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.FORMA_PAGO_DESCUENTO
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem

class ProveedorMisComprasViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<ComprasListResponse>(repository.observeCompras(), isOnline, { repository.refreshCompras() })

/** "2 unidades", "1 unidad", "1.5 unidades". */
internal fun formatUnidades(cantidad: Double): String {
    val texto = formatDecimal(cantidad, 2).trimEnd('0').trimEnd('.')
    return "$texto ${if (cantidad == 1.0) "unidad" else "unidades"}"
}

internal fun formaPagoLabel(formaPago: String): String = when (formaPago) {
    FORMA_PAGO_DESCUENTO -> "Descuento semanal"
    "EFECTIVO" -> "Efectivo"
    else -> formaPago.lowercase().replace('_', ' ').replaceFirstChar { it.uppercase() }
}

@Composable
fun ProveedorMisComprasScreen(viewModel: ProveedorMisComprasViewModel, onVerCompra: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val filas = s.data.filas
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            if (filas.isEmpty()) {
                item { EstadoVacio("Aún no tienes compras registradas.") }
            }
            itemsIndexed(filas, key = { _, c -> c.id }) { index, compra ->
                ProviderListItem(
                    primera = index == 0,
                    ultima = index == filas.lastIndex,
                    onClick = { onVerCompra(compra.id) },
                    trailing = {
                        Column(horizontalAlignment = Alignment.End) {
                            ItemTitulo(formatSoles(compra.importe_total))
                            ItemSubtitulo(formatFecha(compra.fecha))
                        }
                    },
                ) {
                    ItemTitulo(compra.producto_nombre ?: "Producto")
                    ItemSubtitulo(
                        formatUnidades(compra.cantidad) +
                            if (compra.forma_pago == FORMA_PAGO_DESCUENTO) " · Descuento semanal" else "",
                    )
                }
            }
        }
    }
}
