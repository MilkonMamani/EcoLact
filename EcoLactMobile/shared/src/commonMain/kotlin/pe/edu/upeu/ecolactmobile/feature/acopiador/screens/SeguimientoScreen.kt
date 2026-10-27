package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.itemsIndexed
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
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorEntrega
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.ControlCalidad
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.ControlCalidadChip
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.FiltrosChips
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.horaOFecha
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

enum class FiltroControl(val label: String, val incluye: Set<ControlCalidad>?) {
    TODAS("Todas", null),
    EN_CONTROL("En control", setOf(ControlCalidad.SELECCIONADA, ControlCalidad.EN_ANALISIS)),
    CON_RESULTADO("Con resultado", setOf(ControlCalidad.ACEPTADA, ControlCalidad.OBSERVADA, ControlCalidad.RECHAZADA)),
    SIN_MUESTREO("Sin muestreo", setOf(ControlCalidad.SIN_MUESTREO)),
}

/** Seguimiento de calidad (solo lectura) de las entregas de la zona, según el muestreo. */
class AcopiadorSeguimientoViewModel(repository: AcopiadorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<List<AcopiadorEntrega>>(repository.observeSeguimiento(), isOnline, { repository.refreshSeguimiento() }) {
    private val _filtro = MutableStateFlow(FiltroControl.TODAS)
    val filtro: StateFlow<FiltroControl> = _filtro.asStateFlow()

    val filtradas: StateFlow<List<AcopiadorEntrega>> = combine(state, _filtro) { s, f ->
        (s as? UiState.Success)?.data.orEmpty().filter { e ->
            f.incluye == null || (ControlCalidad.from(e.control_calidad) ?: ControlCalidad.SIN_MUESTREO) in f.incluye
        }
    }.stateIn(scope, SharingStarted.Eagerly, emptyList())

    fun onFiltro(f: FiltroControl) { _filtro.value = f }
}

@Composable
fun AcopiadorSeguimientoScreen(viewModel: AcopiadorSeguimientoViewModel, onVerEntrega: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val filtradas by viewModel.filtradas.collectAsState()
    val filtro by viewModel.filtro.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Seguimiento de calidad", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    Text("Calidad analiza solo las entregas seleccionadas como muestra.", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                    FiltrosChips(FiltroControl.entries, filtro, { it.label }, viewModel::onFiltro)
                    if (success.fromCache && !success.cachedAtIso.isNullOrBlank()) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            if (filtradas.isEmpty()) {
                item { EstadoVacio("No hay entregas con ese filtro.") }
            } else {
                itemsIndexed(filtradas, key = { _, e -> e.id }) { i, e ->
                    ProviderListItem(
                        primera = i == 0,
                        ultima = i == filtradas.lastIndex,
                        onClick = { onVerEntrega(e.id) },
                        trailing = { ControlCalidadChip(e.control_calidad) },
                    ) {
                        ItemTitulo(e.proveedor_nombre ?: "Proveedor")
                        ItemSubtitulo("${horaOFecha(e.fecha_hora)} · ${formatLitros(e.litros)}")
                    }
                }
            }
        }
    }
}
