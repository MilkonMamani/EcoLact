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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.planta.InsumoDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.components.AvisoInfo
import pe.edu.upeu.ecolactmobile.feature.planta.components.TituloBloque
import pe.edu.upeu.ecolactmobile.feature.planta.descripcionMovimiento
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidadConSigno
import pe.edu.upeu.ecolactmobile.feature.planta.formatFechaHoraLarga
import pe.edu.upeu.ecolactmobile.feature.planta.nivelInsumo
import pe.edu.upeu.ecolactmobile.feature.planta.nombreUnidad
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

class InsumoDetalleViewModel(repository: PlantaRepository, isOnline: StateFlow<Boolean>, id: Long) :
    LocalFirstViewModel<InsumoDetalleResponse>(repository.observeInsumoDetalle(id), isOnline, { repository.refreshInsumoDetalle(id) }, refreshOnStart = true)

/**
 * Detalle de un insumo. La LECHE es especial: no se compra ni se ajusta a mano (sube con Acopio,
 * baja con Producción), así que no muestra los botones de compra.
 */
@Composable
fun InsumoDetalleScreen(viewModel: InsumoDetalleViewModel, onEscanearBoleta: () -> Unit, onCompraManual: () -> Unit) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val insumo = s.data.insumo
        val unidad = insumo.unidad
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(14.dp)) {
            item {
                Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(insumo.nombre, style = MaterialTheme.typography.titleLarge, color = EcoLactColors.TextoPrincipal)
                        val subtitulo = if (insumo.esLeche) "Del sistema (acopio)" else insumo.descripcion
                        subtitulo?.takeIf { it.isNotBlank() }?.let {
                            Text(it, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                        }
                    }
                    val nivel = nivelInsumo(insumo)
                    if (!insumo.esLeche) StatusChip(nivel.label, nivel.tono)
                }
            }
            item {
                SummaryCard {
                    SummaryRow("Stock actual", formatCantidad(insumo.stock_actual, unidad), primera = true, destacado = true)
                    if (!insumo.esLeche) insumo.stock_minimo?.let { SummaryRow("Stock mínimo", formatCantidad(it, unidad)) }
                    SummaryRow("Unidad base", nombreUnidad(unidad))
                }
            }
            if (insumo.esLeche) {
                item { AvisoInfo("La leche aumenta automáticamente con cada acopio y se descuenta al iniciar una producción. No se compra ni se ajusta a mano.") }
            } else {
                item { TituloBloque("Presentaciones de compra") }
                item {
                    if (s.data.presentaciones.isEmpty()) {
                        EstadoVacio("Sin presentaciones configuradas. Las configura el administrador.")
                    } else {
                        SummaryCard {
                            s.data.presentaciones.forEachIndexed { index, p ->
                                SummaryRow(p.etiqueta, "= ${formatCantidad(p.cantidad_base, unidad)}", primera = index == 0)
                            }
                        }
                    }
                }
            }
            item { TituloBloque("Movimientos recientes") }
            item {
                if (s.data.movimientos.isEmpty()) {
                    EstadoVacio("Sin movimientos todavía.")
                } else {
                    SummaryCard {
                        s.data.movimientos.forEachIndexed { index, m ->
                            val entrada = m.tipo == "ENTRADA"
                            SummaryRowSlot(m.fecha?.let(::formatFechaHoraLarga) ?: "—", primera = index == 0) {
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(descripcionMovimiento(m), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoPrincipal)
                                    Text(
                                        formatCantidadConSigno(m.cantidad, unidad, entrada),
                                        style = MaterialTheme.typography.bodyLarge,
                                        fontWeight = FontWeight.SemiBold,
                                        color = if (entrada) EcoLactColors.VerdeOscuro else EcoLactColors.TextoPrincipal,
                                    )
                                }
                            }
                        }
                    }
                }
            }
            if (!insumo.esLeche) {
                item { BotonPrincipal("Escanear boleta", onEscanearBoleta, icon = EcoLactIcons.PhotoCamera, operacion = true) }
                item { BotonSecundario("Registrar compra manual", onCompraManual, icon = EcoLactIcons.Edit, operacion = true) }
                item { NotaRequiereAsistencia() }
            }
        }
    }
}
