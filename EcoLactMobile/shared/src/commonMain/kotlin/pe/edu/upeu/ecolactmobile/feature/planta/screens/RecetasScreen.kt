package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.RecetaFila
import pe.edu.upeu.ecolactmobile.feature.planta.RecetasListResponse
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoInfo
import pe.edu.upeu.ecolactmobile.feature.planta.components.TituloBloque
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/** Una sola fuente para la lista y el detalle: la lista de recetas guardada en el teléfono. */
class PlantaRecetasViewModel(repository: PlantaRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<RecetasListResponse>(repository.observeRecetas(), isOnline, { repository.refreshRecetas() })

/**
 * Por cada unidad: lo que el servidor calculó en la unidad base de cada insumo; si la receta no
 * está activa, sus ingredientes tal como se configuraron.
 */
private fun ingredientesPorUnidad(receta: RecetaFila): List<Pair<String, String>> =
    if (receta.por_unidad.isNotEmpty()) {
        receta.por_unidad.map { it.nombre to formatCantidad(it.cantidad, it.unidad) }
    } else {
        receta.ingredientes.map { it.insumo to formatCantidad(it.cantidad, it.unidad) }
    }

private fun estadoReceta(receta: RecetaFila): Pair<String, Tono> = if (receta.activa) "Activa" to Tono.EXITO else "Inactiva" to Tono.NEUTRAL

/** Recetas en SOLO LECTURA: las crea y edita el ADMINISTRADOR desde la web. */
@Composable
fun PlantaRecetasScreen(viewModel: PlantaRecetasViewModel, onVerReceta: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val recetas = s.data.filas
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
            if (recetas.isEmpty()) item { EstadoVacio("Todavía no hay recetas configuradas.") }
            recetas.forEachIndexed { index, receta ->
                item(key = receta.id) {
                    val (texto, tono) = estadoReceta(receta)
                    ProviderListItem(
                        primera = index == 0,
                        ultima = index == recetas.lastIndex,
                        onClick = { onVerReceta(receta.productoId) },
                        leading = { IconoFila(EcoLactIcons.Article) },
                        trailing = { StatusChip(texto, tono) },
                    ) {
                        ItemTitulo(listOfNotNull(receta.nombreProducto, receta.presentacion).joinToString(" · "))
                        Text(
                            ingredientesPorUnidad(receta).joinToString(" · ") { (nombre, cantidad) -> "$nombre $cantidad" },
                            style = MaterialTheme.typography.bodySmall,
                            color = EcoLactColors.TextoSecundario,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun PlantaRecetaDetalleScreen(viewModel: PlantaRecetasViewModel, productoId: Long) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val receta = s.data.filas.firstOrNull { it.productoId == productoId }
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(14.dp)) {
            if (receta == null) {
                item { EstadoVacio("Esta receta ya no está disponible.") }
                return@ProveedorPantalla
            }
            item {
                Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(receta.nombreProducto, style = MaterialTheme.typography.titleLarge, color = EcoLactColors.TextoPrincipal)
                        receta.presentacion?.let { Text(it, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
                    }
                    val (texto, tono) = estadoReceta(receta)
                    StatusChip(texto, tono)
                }
            }
            item { TituloBloque("Por cada unidad") }
            item {
                SummaryCard {
                    ingredientesPorUnidad(receta).forEachIndexed { index, (nombre, cantidad) ->
                        SummaryRow(nombre, cantidad, primera = index == 0)
                    }
                }
            }
            item {
                SummaryCard {
                    SummaryRow("Estado", estadoReceta(receta).first, primera = true)
                    SummaryRow("Versión", "${receta.version}")
                    receta.actualizada?.let { SummaryRow("Actualizada", it) }
                }
            }
            item { AvisoInfo("Las recetas las configura el administrador. Nueva producción usa siempre la receta activa.") }
        }
    }
}
