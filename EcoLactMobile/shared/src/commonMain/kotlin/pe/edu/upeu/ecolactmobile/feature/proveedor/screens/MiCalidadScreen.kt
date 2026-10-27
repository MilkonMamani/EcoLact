package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.proveedor.AnalisisResumen
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.FechaBadge
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoResultado

/** Un análisis con los litros de su entrega (cruzados localmente con la lista de entregas guardada). */
data class AnalisisFila(val analisis: AnalisisResumen, val litros: Double?)

class ProveedorMiCalidadViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<List<AnalisisFila>>(
        source = combine(repository.observeAnalisis(), repository.observeEntregas()) { analisis, entregas ->
            analisis ?: return@combine null
            val litrosPorEntrega = entregas?.value?.filas?.associate { it.id to it.litros }.orEmpty()
            CachedValue(analisis.value.filas.map { AnalisisFila(it, litrosPorEntrega[it.entrega_id]) }, analisis.cachedAtIso)
        },
        isOnline = isOnline,
        refresher = { repository.refreshAnalisis() },
    )

@Composable
fun ProveedorMiCalidadScreen(viewModel: ProveedorMiCalidadViewModel, onVerAnalisis: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val filas = s.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            if (filas.isEmpty()) {
                item { EstadoVacio("Aún no tienes análisis de calidad.") }
            }
            itemsIndexed(filas, key = { _, f -> f.analisis.id }) { index, fila ->
                val resultado = estadoResultado(fila.analisis.resultado)
                ProviderListItem(
                    primera = index == 0,
                    ultima = index == filas.lastIndex,
                    onClick = { onVerAnalisis(fila.analisis.id) },
                    leading = { FechaBadge(fila.analisis.fecha_analisis) },
                    trailing = { ItemSubtitulo(formatHora(fila.analisis.fecha_analisis)) },
                ) {
                    ItemTitulo(fila.litros?.let(::formatLitros) ?: "Entrega")
                    StatusChip(resultado.label, resultado.tono)
                }
            }
        }
    }
}
