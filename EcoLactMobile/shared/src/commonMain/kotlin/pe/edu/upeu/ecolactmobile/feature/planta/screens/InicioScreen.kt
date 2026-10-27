package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.height
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.planta.InsumosListResponse
import pe.edu.upeu.ecolactmobile.feature.planta.LotePendiente
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProduccionFila
import pe.edu.upeu.ecolactmobile.feature.planta.ProduccionListResponse
import pe.edu.upeu.ecolactmobile.feature.planta.components.AccionRapida
import pe.edu.upeu.ecolactmobile.feature.planta.components.EncabezadoSeccion
import pe.edu.upeu.ecolactmobile.feature.planta.components.KpiPlanta
import pe.edu.upeu.ecolactmobile.feature.planta.components.LoteItem
import pe.edu.upeu.ecolactmobile.feature.planta.estadoLote
import pe.edu.upeu.ecolactmobile.feature.planta.formatUnidades
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.trabajador.LocalAutorizacion

data class PlantaInicioData(
    val insumos: InsumosListResponse?,
    val enProceso: ProduccionListResponse?,
    val pendientes: List<LotePendiente>,
) {
    val lecheDisponible: Double get() = insumos?.filas?.firstOrNull { it.esLeche }?.stock_actual ?: 0.0
    val lotesEnProceso: Int get() = (enProceso?.filas?.size ?: 0) + pendientes.count { it.conflicto == null }
    val stockBajo: Int get() = insumos?.filas?.count { !it.esLeche && (it.esta_bajo_minimo || it.stock_actual <= 0.0) } ?: 0
}

/** Inicio: leche, lotes en proceso y stock bajo salen de lo guardado en SQLite (insumos + producción). */
class PlantaInicioViewModel(private val repository: PlantaRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<PlantaInicioData>(
        combine(
            repository.observeInsumos(),
            repository.observeProduccion(PlantaRepository.EN_PROCESO),
            repository.observeLotesPendientes(),
        ) { insumos, produccion, pendientes ->
            val base = insumos ?: produccion
            base?.let { CachedValue(PlantaInicioData(insumos?.value, produccion?.value, pendientes), it.cachedAtIso) }
        },
        isOnline,
        {
            repository.refreshInsumos()
            repository.refreshProduccion(PlantaRepository.EN_PROCESO)
        },
    )

@Composable
fun PlantaInicioScreen(
    viewModel: PlantaInicioViewModel,
    primerNombre: String,
    onNuevaProduccion: () -> Unit,
    onEscanearBoleta: () -> Unit,
    onMiQr: () -> Unit,
    onVerTodas: () -> Unit,
    onVerLote: (Long) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val datos = s.data
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido()) {
                    Text("Hola, $primerNombre", style = MaterialTheme.typography.headlineSmall, color = EcoLactColors.TextoPrincipal)
                    Text("Encargado de planta", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                }
            }
            item {
                Row(Modifier.anchoContenido().height(IntrinsicSize.Min), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    KpiPlanta("Leche disponible", formatLitros(datos.lecheDisponible), Modifier.weight(1f))
                    KpiPlanta("Lotes en proceso", "${datos.lotesEnProceso}", Modifier.weight(1f))
                    KpiPlanta("Stock bajo", "${datos.stockBajo}", Modifier.weight(1f), alerta = datos.stockBajo > 0)
                }
            }
            item {
                Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Acciones rápidas", style = MaterialTheme.typography.titleSmall, color = EcoLactColors.TextoPrincipal)
                    Row(Modifier.height(IntrinsicSize.Min), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        val puedeOperar = LocalAutorizacion.current.puedeOperar
                        AccionRapida("Nueva producción", EcoLactIcons.Add, onNuevaProduccion, Modifier.weight(1f), habilitada = puedeOperar)
                        AccionRapida("Escanear boleta", EcoLactIcons.Receipt, onEscanearBoleta, Modifier.weight(1f), habilitada = puedeOperar)
                        AccionRapida("Mi QR", EcoLactIcons.QrCode, onMiQr, Modifier.weight(1f))
                    }
                }
            }
            item { EncabezadoSeccion("Producción activa", "Ver todas", onVerTodas) }

            val pendientes = datos.pendientes.filter { it.conflicto == null }
            val visibles: List<Any> = (pendientes + datos.enProceso?.filas.orEmpty()).take(MAX_ACTIVA)
            if (visibles.isEmpty()) {
                item { EstadoVacio("No hay producción en proceso.") }
            }
            visibles.forEachIndexed { index, lote ->
                item {
                    when (lote) {
                        is LotePendiente -> LoteItem(
                            titulo = lote.productoNombre,
                            subtitulo = formatUnidades(lote.cantidad),
                            detalle = "Sin enviar",
                            estado = "Pendiente" to Tono.ATENCION,
                            primera = index == 0,
                            ultima = index == visibles.lastIndex,
                            onClick = null,
                        )
                        is ProduccionFila -> LoteItem(
                            titulo = tituloLote(lote),
                            subtitulo = formatUnidades(lote.planificado, lote.unidadObtenido),
                            detalle = lote.codigo,
                            estado = estadoLote(lote.estado),
                            primera = index == 0,
                            ultima = index == visibles.lastIndex,
                            onClick = { onVerLote(lote.id) },
                        )
                    }
                }
            }
        }
    }
}

private const val MAX_ACTIVA = 3

/** "Queso Fresco · 1 kg" (o varios productos separados por coma). */
fun tituloLote(fila: ProduccionFila): String = fila.productosNombres.joinToString(", ").ifBlank { "Lote ${fila.codigo}" }
