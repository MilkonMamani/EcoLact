package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.feature.proveedor.ComunicadosListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip

class ProveedorComunicadosViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<ComunicadosListResponse>(repository.observeComunicados(), isOnline, { repository.refreshComunicados() })

@Composable
fun ProveedorComunicadosScreen(viewModel: ProveedorComunicadosViewModel, onVerComunicado: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val filas = s.data.filas
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            if (filas.isEmpty()) {
                item { EstadoVacio("No hay comunicados.") }
            }
            itemsIndexed(filas, key = { _, c -> c.id }) { index, comunicado ->
                val nuevo = comunicado.leido == false
                ProviderListItem(
                    primera = index == 0,
                    ultima = index == filas.lastIndex,
                    onClick = { onVerComunicado(comunicado.id) },
                    trailing = { if (nuevo) StatusChip("Nuevo", Tono.EXITO) },
                ) {
                    ItemTitulo(comunicado.titulo, fontWeight = if (nuevo) FontWeight.SemiBold else FontWeight.Normal)
                    ItemSubtitulo(formatFecha(comunicado.fecha_publicacion))
                }
            }
        }
    }
}
