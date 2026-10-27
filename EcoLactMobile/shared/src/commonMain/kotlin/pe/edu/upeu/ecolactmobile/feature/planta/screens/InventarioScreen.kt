package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.InsumoFila
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProductoFila
import pe.edu.upeu.ecolactmobile.feature.planta.components.PestanasSegmento
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.formatUnidadesLargo
import pe.edu.upeu.ecolactmobile.feature.planta.nivelInsumo
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip

data class InventarioData(val insumos: List<InsumoFila>, val productos: List<ProductoFila>)

class PlantaInventarioViewModel(private val repository: PlantaRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<InventarioData>(
        combine(repository.observeInsumos(), repository.observeProductos()) { insumos, productos ->
            val base = insumos ?: productos
            base?.let { CachedValue(InventarioData(insumos?.value?.filas.orEmpty(), productos?.value?.filas.orEmpty()), it.cachedAtIso) }
        },
        isOnline,
        {
            repository.refreshInsumos()
            repository.refreshProductos()
        },
    )

/** Inventario de consulta: insumos (con su estado) y productos terminados en SOLO LECTURA. */
@Composable
fun PlantaInventarioScreen(
    viewModel: PlantaInventarioViewModel,
    tab: Int,
    onTab: (Int) -> Unit,
    onVerInsumo: (Long) -> Unit,
    onVerProducto: (Long) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val datos = s.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
            item {
                PestanasSegmento(listOf("Insumos (${datos.insumos.size})", "Productos (${datos.productos.size})"), tab, onTab)
            }
            if (tab == 0) {
                if (datos.insumos.isEmpty()) item { EstadoVacio("No hay insumos registrados.") }
                // La leche primero: es el insumo principal y viene del acopio.
                val insumos = datos.insumos.sortedByDescending { it.esLeche }
                insumos.forEachIndexed { index, insumo ->
                    item(key = "i-${insumo.id}") {
                        val nivel = nivelInsumo(insumo)
                        ProviderListItem(
                            primera = index == 0,
                            ultima = index == insumos.lastIndex,
                            onClick = { onVerInsumo(insumo.id) },
                            leading = { IconoFila(if (insumo.esLeche) EcoLactIcons.LocalShipping else EcoLactIcons.Inventory) },
                            trailing = { StatusChip(nivel.label, nivel.tono) },
                        ) {
                            ItemTitulo(insumo.nombre)
                            ItemSubtitulo(formatCantidad(insumo.stock_actual, insumo.unidad))
                        }
                    }
                }
            } else {
                if (datos.productos.isEmpty()) item { EstadoVacio("Todavía no hay productos terminados.") }
                datos.productos.forEachIndexed { index, producto ->
                    item(key = "p-${producto.id}") {
                        ProviderListItem(
                            primera = index == 0,
                            ultima = index == datos.productos.lastIndex,
                            onClick = { onVerProducto(producto.id) },
                            leading = { IconoFila(EcoLactIcons.ShoppingBag) },
                            trailing = if (producto.sin_stock) ({ StatusChip("Sin stock", Tono.ERROR) }) else null,
                        ) {
                            ItemTitulo(producto.nombre)
                            ItemSubtitulo(formatUnidadesLargo(producto.cantidad_actual, producto.unidad_medida))
                        }
                    }
                }
            }
        }
    }
}

@Composable
internal fun IconoFila(icon: ImageVector) {
    Box(Modifier.size(40.dp).background(EcoLactColors.VerdeClaro, RoundedCornerShape(10.dp)), contentAlignment = Alignment.Center) {
        Icon(icon, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(22.dp))
    }
}
