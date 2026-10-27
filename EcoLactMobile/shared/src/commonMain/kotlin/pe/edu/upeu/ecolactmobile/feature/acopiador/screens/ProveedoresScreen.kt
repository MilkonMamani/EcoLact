package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
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
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorProveedorFila
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.CampoBusqueda
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.fechaHoraLarga
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/**
 * Proveedores de la zona del acopiador, siempre desde la copia del teléfono: es la misma lista
 * con la que se identifica por QR/DNI sin conexión. La búsqueda se filtra localmente.
 */
class AcopiadorProveedoresViewModel(repository: AcopiadorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<List<AcopiadorProveedorFila>>(repository.observeProveedores(), isOnline, { repository.refreshProveedores() }) {
    private val _texto = MutableStateFlow("")
    val texto: StateFlow<String> = _texto.asStateFlow()

    val filtrados: StateFlow<List<AcopiadorProveedorFila>> = combine(state, _texto) { s, texto ->
        val q = texto.trim().lowercase()
        (s as? UiState.Success)?.data.orEmpty().filter { q.isEmpty() || it.nombre_completo.lowercase().contains(q) || it.dni.contains(q) }
    }.stateIn(scope, SharingStarted.Eagerly, emptyList())

    fun onTexto(texto: String) { _texto.value = texto }
}

@Composable
fun AcopiadorProveedoresScreen(viewModel: AcopiadorProveedoresViewModel, onVerProveedor: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtrados by viewModel.filtrados.collectAsState()
    val texto by viewModel.texto.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Mis proveedores", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    CampoBusqueda(texto, viewModel::onTexto, "Buscar proveedor o DNI")
                    if (success.fromCache && !success.cachedAtIso.isNullOrBlank()) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            if (filtrados.isEmpty()) {
                item { EstadoVacio(if (success.data.isEmpty()) "No hay proveedores asignados a tu zona." else "Sin resultados.") }
            } else {
                itemsIndexed(filtrados, key = { _, p -> p.id }) { i, p ->
                    ProviderListItem(primera = i == 0, ultima = i == filtrados.lastIndex, onClick = { onVerProveedor(p.id) }) {
                        ItemTitulo(p.nombre_completo)
                        ItemSubtitulo("DNI: ${p.dni}")
                        p.zona_nombre?.let { ItemSubtitulo("Zona: $it") }
                    }
                }
            }
        }
    }
}

class AcopiadorProveedorDetalleViewModel(private val repository: AcopiadorRepository, isOnline: StateFlow<Boolean>, private val proveedorId: Long) :
    LocalFirstViewModel<AcopiadorProveedorFila?>(repository.observeProveedor(proveedorId), isOnline, { repository.refreshProveedores() }) {
    val resumen = repository.observeProveedorResumen(proveedorId)

    init {
        if (isOnline.value) scope.launch { runCatching { repository.refreshProveedorDetalle(proveedorId) } }
    }
}

/** Datos reales del proveedor (DNI, zona, estado, teléfono) + resumen si ya se descargó. */
@Composable
fun AcopiadorProveedorDetalleScreen(viewModel: AcopiadorProveedorDetalleViewModel, onRegistrar: () -> Unit, onVerHistorial: () -> Unit) {
    val state by viewModel.state.collectAsState()
    val resumen by viewModel.resumen.collectAsState(null)

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val p = success.data
        if (p == null) {
            EstadoVacio("Este proveedor ya no está en tu zona.")
            return@ContenidoLocal
        }
        ProveedorPantalla {
            item {
                Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    InitialsAvatar(initials(p.nombres, p.apellidos, p.nombre_completo), large = true)
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(p.nombre_completo, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                        StatusChip(if (p.activo) "Activo" else "Inactivo", if (p.activo) Tono.EXITO else Tono.NEUTRAL)
                    }
                }
            }
            item {
                SummaryCard {
                    SummaryRow("DNI", p.dni, primera = true)
                    SummaryRow("Zona", p.zona_nombre ?: "—")
                    p.telefono?.takeIf { it.isNotBlank() }?.let { SummaryRow("Teléfono", it) }
                    resumen?.let { r ->
                        SummaryRow("Entregas", "${r.entregas_totales}")
                        SummaryRow("Litros entregados", formatLitros(r.litros_entregados))
                        r.ultima_entrega?.let { SummaryRow("Última entrega", fechaHoraLarga(it)) }
                    }
                }
            }
            if (p.activo) item { BotonPrincipal("Registrar entrega", onRegistrar, icon = EcoLactIcons.Add, operacion = true) }
            item { BotonSecundario("Ver historial", onVerHistorial, icon = EcoLactIcons.History) }
        }
    }
}
