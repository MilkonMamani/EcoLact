package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.state.UiState
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.hoy
import pe.edu.upeu.ecolactmobile.feature.proveedor.EntregaResumen
import pe.edu.upeu.ecolactmobile.feature.proveedor.EntregasListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.EstadoEntregaVisual
import pe.edu.upeu.ecolactmobile.feature.proveedor.FiltroSemana
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.ResumenSemana
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.FechaBadge
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.FiltroDesplegable
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoEntrega
import pe.edu.upeu.ecolactmobile.feature.proveedor.filtrarEntregas
import pe.edu.upeu.ecolactmobile.feature.proveedor.resumenSemana

/** Lo que pinta la pantalla: resumen fijo de esta semana + la lista ya filtrada. */
data class EntregasVista(val resumen: ResumenSemana, val filas: List<EntregaResumen>, val hayEntregas: Boolean)

/** Estados que se pueden filtrar (null = todos). */
private val ESTADOS_FILTRO: List<EstadoEntregaVisual?> = listOf(
    null,
    EstadoEntregaVisual.ACEPTADA,
    EstadoEntregaVisual.OBSERVADA,
    EstadoEntregaVisual.PENDIENTE,
    EstadoEntregaVisual.RECHAZADA,
)

class ProveedorEntregasViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<EntregasListResponse>(repository.observeEntregas(), isOnline, { repository.refreshEntregas() }) {

    val semana = MutableStateFlow(FiltroSemana.TODAS)
    val estado = MutableStateFlow<EstadoEntregaVisual?>(null)

    /** Los filtros se aplican sobre la copia local: responden al instante y funcionan offline. */
    val vista: StateFlow<UiState<EntregasVista>> = combine(state, semana, estado) { s, semana, estado ->
        when (s) {
            is UiState.Success -> {
                val hoy = hoy()
                UiState.Success(
                    EntregasVista(
                        resumen = resumenSemana(s.data.filas, hoy),
                        filas = filtrarEntregas(s.data.filas, semana, estado, hoy),
                        hayEntregas = s.data.filas.isNotEmpty(),
                    ),
                    fromCache = s.fromCache,
                    cachedAtIso = s.cachedAtIso,
                )
            }
            is UiState.Error -> s
            UiState.Loading -> UiState.Loading
        }
    }.stateIn(scope, SharingStarted.Eagerly, UiState.Loading)
}

@Composable
fun ProveedorEntregasScreen(viewModel: ProveedorEntregasViewModel, onVerEntrega: (Long) -> Unit) {
    val state by viewModel.vista.collectAsState()
    val semana by viewModel.semana.collectAsState()
    val estado by viewModel.estado.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val vista = s.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        buildAnnotatedString {
                            withStyle(SpanStyle(fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)) {
                                append(formatLitros(vista.resumen.litros))
                            }
                            append(" esta semana  ·  ")
                            withStyle(SpanStyle(fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)) {
                                append("${vista.resumen.entregas}")
                            }
                            append(if (vista.resumen.entregas == 1) " entrega" else " entregas")
                        },
                        style = MaterialTheme.typography.bodyLarge,
                        color = EcoLactColors.TextoSecundario,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        FiltroDesplegable(
                            label = if (semana == FiltroSemana.TODAS) "Semana" else semana.label,
                            activo = semana != FiltroSemana.TODAS,
                            opciones = FiltroSemana.entries.map { it.label },
                            onSeleccionar = { viewModel.semana.value = FiltroSemana.entries[it] },
                        )
                        FiltroDesplegable(
                            label = estado?.label ?: "Estado",
                            activo = estado != null,
                            opciones = ESTADOS_FILTRO.map { it?.label ?: "Todos los estados" },
                            onSeleccionar = { viewModel.estado.value = ESTADOS_FILTRO[it] },
                        )
                    }
                    if (s.fromCache) DatosGuardadosNote(s.cachedAtIso)
                }
            }
            item { Spacer(Modifier.height(12.dp)) }

            if (vista.filas.isEmpty()) {
                item {
                    EstadoVacio(if (vista.hayEntregas) "No hay entregas con estos filtros." else "Aún no tienes entregas registradas.")
                }
            } else {
                itemsIndexed(vista.filas, key = { _, e -> e.id }) { index, entrega ->
                    val estadoVisual = estadoEntrega(entrega.estado, entrega.resultado)
                    ProviderListItem(
                        primera = index == 0,
                        ultima = index == vista.filas.lastIndex,
                        onClick = { onVerEntrega(entrega.id) },
                        leading = { FechaBadge(entrega.fecha_hora) },
                        trailing = { ItemSubtitulo(formatHora(entrega.fecha_hora)) },
                    ) {
                        ItemTitulo(formatLitros(entrega.litros))
                        StatusChip(estadoVisual.label, estadoVisual.tono)
                    }
                }
            }
        }
    }
}

