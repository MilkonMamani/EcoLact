package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionItem
import pe.edu.upeu.ecolactmobile.feature.notificaciones.NotificacionesRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem

class ProveedorNotificacionesViewModel(private val repository: NotificacionesRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<List<NotificacionItem>>(repository.observe(), isOnline, { repository.refresh() }, refreshOnStart = true) {

    /** Se marca al instante en el teléfono; el servidor se entera ahora o al volver internet. */
    fun marcarLeida(id: Long) {
        scope.launch { repository.marcarLeida(id) }
    }
}

@Composable
fun ProveedorNotificacionesScreen(viewModel: ProveedorNotificacionesViewModel) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val filas = s.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            if (filas.isEmpty()) {
                item { EstadoVacio("No tienes notificaciones.") }
            }
            itemsIndexed(filas, key = { _, n -> n.id }) { index, notificacion ->
                val nueva = !notificacion.leido
                ProviderListItem(
                    primera = index == 0,
                    ultima = index == filas.lastIndex,
                    onClick = if (nueva) ({ viewModel.marcarLeida(notificacion.id) }) else null,
                    leading = {
                        Box(
                            Modifier
                                .semantics { if (nueva) contentDescription = "Sin leer" }
                                .size(8.dp)
                                .background(if (nueva) EcoLactColors.VerdePrincipal else Color.Transparent, CircleShape),
                        )
                    },
                ) {
                    ItemTitulo(
                        notificacion.titulo,
                        color = if (nueva) EcoLactColors.TextoPrincipal else EcoLactColors.TextoSecundario,
                        fontWeight = if (nueva) FontWeight.SemiBold else FontWeight.Normal,
                    )
                    ItemSubtitulo(notificacion.mensaje)
                }
            }
        }
    }
}
