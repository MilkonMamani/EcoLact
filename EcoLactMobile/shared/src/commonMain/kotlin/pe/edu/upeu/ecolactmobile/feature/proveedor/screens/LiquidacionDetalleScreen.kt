package pe.edu.upeu.ecolactmobile.feature.proveedor.screens

import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatDecimal
import pe.edu.upeu.ecolactmobile.core.util.formatFecha
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.feature.proveedor.LiquidacionDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.proveedor.ProveedorRepository
import pe.edu.upeu.ecolactmobile.feature.proveedor.comprasDescontadas
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.estadoPagoLabel
import pe.edu.upeu.ecolactmobile.feature.proveedor.otrosAjustes
import pe.edu.upeu.ecolactmobile.feature.proveedor.resultadoSemanal
import pe.edu.upeu.ecolactmobile.feature.proveedor.semanaLabel

class ProveedorLiquidacionDetalleViewModel(repository: ProveedorRepository, isOnline: StateFlow<Boolean>, liquidacionId: Long) :
    LocalFirstViewModel<LiquidacionDetalleResponse>(
        source = repository.observeLiquidacionDetalle(liquidacionId),
        isOnline = isOnline,
        refresher = { repository.refreshLiquidacionDetalle(liquidacionId) },
        refreshOnStart = true,
    )

/** Precio por litro: 2 decimales salvo que el backend aplique más (ej. 1.2375 por un ajuste porcentual). */
private fun formatPrecio(valor: Double): String {
    val cuatro = formatDecimal(valor, 4).trimEnd('0')
    val decimales = cuatro.substringAfter('.', "").length.coerceAtLeast(2)
    return "S/ ${formatDecimal(valor, decimales)} por litro"
}

@Composable
fun ProveedorLiquidacionDetalleScreen(viewModel: ProveedorLiquidacionDetalleViewModel) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val detalle = s.data
        val liq = detalle.liquidacion
        val (estado, tonoEstado) = estadoPagoLabel(liq.estado)
        val calidad = resultadoSemanal(liq)
        val compras = comprasDescontadas(detalle)
        val ajustesCalidad = otrosAjustes(detalle)
        val fechaPago = detalle.pagos.filter { it.estado != "ANULADO" }.mapNotNull { it.fecha_pago }.maxOrNull()

        ProveedorPantalla {
            item {
                SummaryCard {
                    SummaryRow("Semana", semanaLabel(liq).removePrefix("Semana "), primera = true)
                    if (liq.fecha_inicio != null && liq.fecha_fin != null) {
                        SummaryRow("Del", "${formatFecha(liq.fecha_inicio)} al ${formatFecha(liq.fecha_fin)}")
                    }
                    SummaryRow("Litros entregados", formatLitros(liq.total_litros_entregados))
                    if (liq.total_litros_pagables != liq.total_litros_entregados) {
                        SummaryRow("Litros pagables", formatLitros(liq.total_litros_pagables))
                    }
                    SummaryRow("Precio aplicado", formatPrecio(liq.precio_litro_aplicado))
                    SummaryRowSlot("Calidad de la semana") { StatusChip(calidad.label, calidad.tono) }
                }
            }
            item {
                SummaryCard {
                    SummaryRow("Monto por leche", formatSoles(liq.subtotal), primera = true)
                    if (ajustesCalidad != 0.0) {
                        SummaryRow(
                            "Ajuste de calidad",
                            formatSoles(ajustesCalidad),
                            color = if (ajustesCalidad < 0) EcoLactColors.Rojo else null,
                        )
                    }
                    if (compras > 0) SummaryRow("Compras descontadas", "− ${formatSoles(compras)}")
                    SummaryRow("Total a recibir", formatSoles(liq.total_pagar), destacado = true)
                }
            }
            item {
                SummaryCard {
                    SummaryRowSlot("Estado", primera = true) { StatusChip(estado, tonoEstado) }
                    fechaPago?.let { SummaryRow("Fecha de pago", formatFecha(it)) }
                    if (liq.estado != "PAGADA" && detalle.saldo_pendiente > 0) {
                        SummaryRow("Saldo pendiente", formatSoles(detalle.saldo_pendiente))
                    }
                }
            }
        }
    }
}
