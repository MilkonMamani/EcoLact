package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.PrecioVigente
import pe.edu.upeu.ecolactmobile.feature.planta.ProductoDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.planta.components.TituloBloque
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.formatUnidadesLargo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

class PlantaProductoDetalleViewModel(repository: PlantaRepository, isOnline: StateFlow<Boolean>, id: Long) :
    LocalFirstViewModel<ProductoDetalleResponse>(repository.observeProductoDetalle(id), isOnline, { repository.refreshProductoDetalle(id) }, refreshOnStart = true)

/**
 * Producto terminado en SOLO LECTURA. Los precios los configura el ADMINISTRADOR desde la web:
 * aquí solo se muestran (se actualizan al sincronizar) y no existe ninguna acción para cambiarlos.
 */
@Composable
fun PlantaProductoDetalleScreen(viewModel: PlantaProductoDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val p = s.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(14.dp)) {
            item {
                Column(Modifier.anchoContenido()) {
                    Text(p.nombre_base ?: p.nombre, style = MaterialTheme.typography.titleLarge, color = EcoLactColors.TextoPrincipal)
                    p.presentacion?.let { Text(it, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
                }
            }
            item {
                SummaryCard {
                    p.presentacion?.let { SummaryRow("Presentación", it, primera = true) }
                    SummaryRow("Stock actual", formatUnidadesLargo(p.cantidad_actual, p.unidad_medida), primera = p.presentacion == null, destacado = true)
                    SummaryRow("Estado", if (p.estado == "INACTIVO") "Inactivo" else "Activo")
                    SummaryRow("Última producción", p.ultima_produccion?.let { "Lote ${it.codigo}" } ?: "Sin producción")
                    SummaryRow(
                        "Receta",
                        if (p.receta?.estado == "ACTIVA") "Disponible" else "Sin receta activa",
                        color = if (p.receta?.estado == "ACTIVA") EcoLactColors.VerdeOscuro else EcoLactColors.TextoSecundario,
                    )
                }
            }
            item { TituloBloque("Precios vigentes") }
            item {
                if (p.precios.sinConfigurar) {
                    SummaryCard { SummaryRow("Precios", "Sin configurar", primera = true, color = EcoLactColors.TextoSecundario) }
                } else {
                    SummaryCard {
                        FilaPrecio("Público", p.precios.publico, mostrarMinimo = false, primera = true)
                        FilaPrecio("Mayorista", p.precios.mayorista, mostrarMinimo = true)
                        FilaPrecio("Proveedor", p.precios.proveedor, mostrarMinimo = false)
                    }
                }
            }
        }
    }
}

@Composable
private fun FilaPrecio(tipo: String, precio: PrecioVigente?, mostrarMinimo: Boolean, primera: Boolean = false) {
    SummaryRowSlot(tipo, primera) {
        Column(horizontalAlignment = Alignment.End) {
            Text(
                precio?.let { formatSoles(it.precio) } ?: "Sin configurar",
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = if (precio != null) FontWeight.SemiBold else FontWeight.Normal,
                color = if (precio != null) EcoLactColors.TextoPrincipal else EcoLactColors.TextoSecundario,
            )
            if (mostrarMinimo) precio?.cantidad_minima?.let {
                Text("Desde ${formatCantidad(it)} unidades", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
            }
        }
    }
}
