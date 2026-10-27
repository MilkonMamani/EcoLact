package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatSoles
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.planta.BorradorResumen
import pe.edu.upeu.ecolactmobile.feature.planta.CatalogoInsumo
import pe.edu.upeu.ecolactmobile.feature.planta.CompraReciente
import pe.edu.upeu.ecolactmobile.feature.planta.EstadoBorrador
import pe.edu.upeu.ecolactmobile.feature.planta.OrigenCompra
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.components.EncabezadoSeccion
import pe.edu.upeu.ecolactmobile.feature.planta.formatCantidad
import pe.edu.upeu.ecolactmobile.feature.planta.formatFechaLarga
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

data class ComprasData(val borradores: List<BorradorResumen>, val compras: List<CompraReciente>, val catalogo: List<CatalogoInsumo>)

class PlantaComprasViewModel(private val repository: PlantaRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<ComprasData>(
        combine(repository.observeBorradores(), repository.observeCompras(), repository.observeCatalogo()) { borradores, compras, catalogo ->
            CachedValue(ComprasData(borradores, compras?.value.orEmpty(), catalogo?.value.orEmpty()), compras?.cachedAtIso.orEmpty())
        },
        isOnline,
        { repository.refreshCompras() },
        refreshOnStart = true,
    )

fun tonoBorrador(estado: EstadoBorrador): Pair<String, Tono> = when (estado) {
    EstadoBorrador.PENDIENTE_OCR -> "Pendiente OCR" to Tono.ATENCION
    EstadoBorrador.REQUIERE_REVISION -> "Por revisar" to Tono.EXITO
    EstadoBorrador.PENDIENTE_SYNC -> "Pendiente de envío" to Tono.ATENCION
}

/** Compras de insumos: lo que está en el teléfono (por revisar / sin enviar) y lo ya registrado. */
@Composable
fun PlantaComprasScreen(
    viewModel: PlantaComprasViewModel,
    onEscanearBoleta: () -> Unit,
    onCompraManual: () -> Unit,
    onAbrirBorrador: (BorradorResumen) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val datos = s.data
        val nombres = datos.catalogo.associate { it.id to it.nombre }
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
            item { BotonPrincipal("Escanear boleta", onEscanearBoleta, icon = EcoLactIcons.PhotoCamera, operacion = true) }
            item { BotonSecundario("Registrar compra manual", onCompraManual, icon = EcoLactIcons.Edit, operacion = true) }
            item { NotaRequiereAsistencia() }

            if (datos.borradores.isNotEmpty()) {
                item { EncabezadoSeccion("En el teléfono") }
                datos.borradores.forEachIndexed { index, b ->
                    item(key = b.id) {
                        val (texto, tono) = tonoBorrador(b.estado)
                        val insumos = b.datos.lineas.mapNotNull { l -> l.insumoId?.let { nombres[it] } }.distinct()
                        ProviderListItem(
                            primera = index == 0,
                            ultima = index == datos.borradores.lastIndex,
                            onClick = { onAbrirBorrador(b) },
                            leading = { IconoFila(if (b.origen == OrigenCompra.BOLETA) EcoLactIcons.Receipt else EcoLactIcons.Edit) },
                            trailing = { StatusChip(texto, tono) },
                        ) {
                            ItemTitulo(if (b.origen == OrigenCompra.BOLETA) "Boleta escaneada" else "Compra manual")
                            ItemSubtitulo(insumos.joinToString(", ").ifBlank { formatFechaLarga(b.creadoEn) })
                        }
                    }
                }
            }

            item { EncabezadoSeccion("Compras registradas") }
            if (datos.compras.isEmpty()) item { EstadoVacio("Todavía no hay compras registradas.") }
            datos.compras.forEachIndexed { index, compra ->
                item(key = "c-${compra.id}") {
                    ProviderListItem(
                        primera = index == 0,
                        ultima = index == datos.compras.lastIndex,
                        onClick = null,
                        leading = { IconoFila(EcoLactIcons.ShoppingCart) },
                        trailing = compra.total?.let { total -> { Text(formatSoles(total), style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold) } },
                    ) {
                        ItemTitulo(listOfNotNull("Compra #${compra.id}", compra.fecha?.let(::formatFechaLarga)).joinToString(" · "))
                        ItemSubtitulo(
                            compra.lineas.joinToString(" · ") { l ->
                                "${l.insumo.orEmpty()} ${formatCantidad(l.cantidad)} × ${l.presentacion.orEmpty()}".trim()
                            },
                        )
                        compra.proveedor_comercial?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario) }
                    }
                }
            }
        }
    }
}
