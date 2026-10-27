package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatRelativo
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorComunicado
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.FiltrosChips
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.fechaHoraLarga
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

/** "Importantes" = comunicados de tipo AVISO (los tipos son INFORMACION, AVISO, CAPACITACION). */
enum class FiltroComunicado(val label: String) { TODOS("Todos"), NO_LEIDOS("No leídos"), IMPORTANTES("Importantes") }

class AcopiadorComunicadosViewModel(repository: AcopiadorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<List<AcopiadorComunicado>>(repository.observeComunicados(), isOnline, { repository.refreshComunicados() }) {
    private val _filtro = MutableStateFlow(FiltroComunicado.TODOS)
    val filtro: StateFlow<FiltroComunicado> = _filtro.asStateFlow()

    val filtrados: StateFlow<List<AcopiadorComunicado>> = combine(state, _filtro) { s, f ->
        (s as? UiState.Success)?.data.orEmpty().filter {
            when (f) {
                FiltroComunicado.TODOS -> true
                FiltroComunicado.NO_LEIDOS -> !it.leido
                FiltroComunicado.IMPORTANTES -> it.tipo == "AVISO"
            }
        }
    }.stateIn(scope, SharingStarted.Eagerly, emptyList())

    fun onFiltro(f: FiltroComunicado) { _filtro.value = f }
}

@Composable
fun AcopiadorComunicadosScreen(viewModel: AcopiadorComunicadosViewModel, onVerComunicado: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtrados by viewModel.filtrados.collectAsState()
    val filtro by viewModel.filtro.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    FiltrosChips(FiltroComunicado.entries, filtro, { it.label }, viewModel::onFiltro)
                    if (success.fromCache && !success.cachedAtIso.isNullOrBlank()) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            if (filtrados.isEmpty()) {
                item { EstadoVacio("No hay comunicados.") }
            } else {
                itemsIndexed(filtrados, key = { _, c -> c.id }) { i, c ->
                    ProviderListItem(
                        primera = i == 0,
                        ultima = i == filtrados.lastIndex,
                        onClick = { onVerComunicado(c.id) },
                        leading = {
                            Box(Modifier.size(8.dp).background(if (c.leido) EcoLactColors.Blanco else EcoLactColors.VerdePrincipal, CircleShape))
                        },
                    ) {
                        ItemTitulo(c.titulo, fontWeight = if (c.leido) FontWeight.Normal else FontWeight.SemiBold)
                        ItemSubtitulo(formatRelativo(c.fecha_publicacion))
                    }
                }
            }
        }
    }
}

class AcopiadorComunicadoDetalleViewModel(repository: AcopiadorRepository, isOnline: StateFlow<Boolean>, id: Long) :
    LocalFirstViewModel<AcopiadorComunicado?>(repository.observeComunicado(id), isOnline, { repository.refreshComunicados() }) {
    init {
        scope.launch { repository.abrirComunicado(id) }
    }
}

@Composable
fun AcopiadorComunicadoDetalleScreen(viewModel: AcopiadorComunicadoDetalleViewModel) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val c = success.data
        if (c == null) {
            EstadoVacio("Este comunicado ya no está disponible.")
            return@ContenidoLocal
        }
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(c.titulo, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    Text(fechaHoraLarga(c.fecha_publicacion), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                }
            }
            item {
                Text(
                    c.contenido ?: "Sin contenido.",
                    style = MaterialTheme.typography.bodyLarge,
                    color = EcoLactColors.TextoPrincipal,
                    modifier = Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
                )
            }
            c.fecha_evento?.let { item { ItemSubtitulo("Fecha del evento: ${fechaHoraLarga(it)}") } }
        }
    }
}
