package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.feature.proveedor.ComunicadoDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

/**
 * Abrir el comunicado lo marca como leído: con conexión, el GET del detalle ya lo marca en el
 * backend; sin conexión, se marca en la copia local y la lectura queda en la cola.
 */
class ProveedorComunicadoDetalleViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>, comunicadoId: Long) :
    LocalFirstViewModel<ComunicadoDetalleResponse>(
        source = repository.observeComunicadoDetalle(comunicadoId),
        isOnline = isOnline,
        refresher = { repository.abrirComunicado(comunicadoId) },
        refreshOnStart = true,
    ) {
    init {
        if (!isOnline.value) scope.launch { repository.abrirComunicado(comunicadoId) }
    }
}

@Composable
fun ProveedorComunicadoDetalleScreen(viewModel: ProveedorComunicadoDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val data = s.data
        ProveedorPantalla {
            item {
                Column(
                    modifier = Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Text(data.titulo, style = MaterialTheme.typography.titleMedium, color = EcoLactColors.TextoPrincipal)
                    Text(
                        listOfNotNull(
                            "Publicado ${formatFecha(data.fecha_publicacion)}",
                            data.fecha_evento?.let { "Fecha del evento ${formatFecha(it)}" },
                        ).joinToString("  ·  "),
                        style = MaterialTheme.typography.bodySmall,
                        color = EcoLactColors.TextoSecundario,
                    )
                    Text(data.contenido, style = MaterialTheme.typography.bodyLarge, color = EcoLactColors.TextoPrincipal)
                }
            }
        }
    }
}
