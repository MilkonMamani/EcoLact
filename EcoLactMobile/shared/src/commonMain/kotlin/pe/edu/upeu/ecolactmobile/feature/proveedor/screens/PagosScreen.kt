package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.feature.proveedor.LiquidacionesListResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoPagoLabel
import pe.edu.upeu.ecolactmobile.feature.proveedor.semanaLabel

/** "Mis pagos": el backend las llama liquidaciones (una por semana). */
class ProveedorPagosViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<LiquidacionesListResponse>(repository.observeLiquidaciones(), isOnline, { repository.refreshLiquidaciones() })

@Composable
fun ProveedorPagosScreen(viewModel: ProveedorPagosViewModel, onVerPago: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val filas = s.data.filas
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    s.data.resumen?.let { resumen ->
                        Text(
                            buildAnnotatedString {
                                withStyle(SpanStyle(fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)) {
                                    append(formatSoles(resumen.por_cobrar))
                                }
                                append(" por cobrar")
                            },
                            style = MaterialTheme.typography.bodyLarge,
                            color = EcoLactColors.TextoSecundario,
                        )
                    }
                    if (s.fromCache) DatosGuardadosNote(s.cachedAtIso)
                }
            }
            item { Spacer(Modifier.height(12.dp)) }

            if (filas.isEmpty()) {
                item { EstadoVacio("Aún no tienes pagos registrados.") }
            } else {
                itemsIndexed(filas, key = { _, l -> l.id }) { index, liquidacion ->
                    val (estado, tono) = estadoPagoLabel(liquidacion.estado)
                    val compras = liquidacion.compras_descontadas ?: 0.0
                    ProviderListItem(
                        primera = index == 0,
                        ultima = index == filas.lastIndex,
                        onClick = { onVerPago(liquidacion.id) },
                        trailing = {
                            Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                ItemTitulo(formatSoles(liquidacion.total_pagar))
                                StatusChip(estado, tono)
                            }
                        },
                    ) {
                        ItemTitulo(semanaLabel(liquidacion))
                        ItemSubtitulo(formatLitros(liquidacion.total_litros_entregados))
                        if (compras > 0) ItemSubtitulo("Compras descontadas − ${formatSoles(compras)}")
                    }
                }
            }
        }
    }
}
