package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
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
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatDecimal
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.proveedor.AnalisisDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.AnalisisParametroDetalle
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoResultado

class ProveedorAnalisisDetalleViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>, analisisId: Long) :
    LocalFirstViewModel<AnalisisDetalleResponse>(
        source = repository.observeAnalisisDetalle(analisisId),
        isOnline = isOnline,
        refresher = { repository.refreshAnalisisDetalle(analisisId) },
        refreshOnStart = true,
    )

/** Orden habitual de lectura de un análisis de leche; lo que no se reconoce va al final, en el orden del backend. */
private val ORDEN_PARAMETROS = listOf("grasa", "prote", "lactosa", "densidad", "no graso", "sng", "sales", "total", "agua", "congela", "ph")

private fun ordenDe(parametro: AnalisisParametroDetalle): Int {
    val texto = "${parametro.parametro_nombre} ${parametro.parametro_codigo}".lowercase()
    return ORDEN_PARAMETROS.indexOfFirst { it in texto }.let { if (it < 0) ORDEN_PARAMETROS.size else it }
}

private fun formatValor(valor: Double): String = formatDecimal(valor, 3).trimEnd('0').trimEnd('.')

@Composable
fun ProveedorAnalisisDetalleScreen(viewModel: ProveedorAnalisisDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val data = s.data
        val resultado = estadoResultado(data.resultado)

        ProveedorPantalla {
            item {
                SummaryCard {
                    SummaryRowSlot("Resultado", primera = true) { StatusChip(resultado.label, resultado.tono) }
                    SummaryRow("Fecha", formatFecha(data.fecha_analisis))
                    data.entrega?.let { entrega ->
                        SummaryRow("Litros", formatLitros(entrega.litros))
                        entrega.zona_nombre?.let { SummaryRow("Zona", it) }
                    }
                }
            }

            if (data.detalles.isNotEmpty()) {
                item {
                    Column(Modifier.anchoContenido()) {
                        SectionHeader("Parámetros")
                        SummaryCard {
                            data.detalles.sortedBy(::ordenDe).forEachIndexed { index, parametro ->
                                SummaryRowSlot(parametro.parametro_nombre, primera = index == 0) {
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(
                                            formatValor(parametro.valor) + (parametro.unidad?.takeIf { it.isNotBlank() }?.let { " $it" } ?: ""),
                                            style = MaterialTheme.typography.bodyLarge,
                                            fontWeight = FontWeight.SemiBold,
                                            color = if (parametro.cumple == false) EcoLactColors.Rojo else EcoLactColors.TextoPrincipal,
                                        )
                                        if (parametro.cumple == false) {
                                            Text("Fuera de rango", style = MaterialTheme.typography.labelSmall, color = EcoLactColors.Rojo)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            data.observacion?.takeIf { it.isNotBlank() }?.let { observacion ->
                item {
                    Column(Modifier.anchoContenido()) {
                        SectionHeader("Observación del analista")
                        Text(
                            observacion,
                            style = MaterialTheme.typography.bodyMedium,
                            color = EcoLactColors.TextoPrincipal,
                            modifier = Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
                        )
                    }
                }
            }

            data.sancion?.let { sancion ->
                item {
                    Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(0.dp)) {
                        SectionHeader("Sanción")
                        SummaryCard {
                            SummaryRow("Tipo", sancion.tipo.lowercase().replaceFirstChar { it.uppercase() }, primera = true, color = EcoLactColors.Rojo)
                            sancion.descripcion?.takeIf { it.isNotBlank() }?.let { SummaryRow("Detalle", it) }
                        }
                    }
                }
            }
        }
    }
}
