package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.planta.LotePendiente
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository
import pe.edu.upeu.ecolactmobile.feature.planta.ProduccionFila
import pe.edu.upeu.ecolactmobile.feature.planta.components.LoteItem
import pe.edu.upeu.ecolactmobile.feature.planta.components.PestanasSegmento
import pe.edu.upeu.ecolactmobile.feature.planta.estadoLote
import pe.edu.upeu.ecolactmobile.feature.planta.formatUnidades
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

data class PlantaProduccionData(
    val enProceso: List<ProduccionFila>,
    val finalizados: List<ProduccionFila>,
    val pendientes: List<LotePendiente>,
)

class PlantaProduccionViewModel(private val repository: PlantaRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<PlantaProduccionData>(
        combine(
            repository.observeProduccion(PlantaRepository.EN_PROCESO),
            repository.observeProduccion(PlantaRepository.FINALIZADO),
            repository.observeLotesPendientes(),
        ) { enProceso, finalizados, pendientes ->
            val base = enProceso ?: finalizados
            base?.let {
                CachedValue(PlantaProduccionData(enProceso?.value?.filas.orEmpty(), finalizados?.value?.filas.orEmpty(), pendientes), it.cachedAtIso)
            }
        },
        isOnline,
        {
            repository.refreshProduccion(PlantaRepository.EN_PROCESO)
            repository.refreshProduccion(PlantaRepository.FINALIZADO)
        },
    ) {
    /** Conflicto ya leído: se quita de la cola (el servidor nunca creó ese lote). */
    fun descartar(localId: String) = repository.descartarRechazada(localId)
}

@Composable
fun PlantaProduccionScreen(viewModel: PlantaProduccionViewModel, onNuevaProduccion: () -> Unit, onVerLote: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    var tab by rememberSaveable { mutableIntStateOf(0) }

    ContenidoLocal(state, onRetry = viewModel::refresh) { s ->
        val datos = s.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
            item { BotonPrincipal("Nueva producción", onNuevaProduccion, icon = EcoLactIcons.Add, operacion = true) }
            item { PestanasSegmento(listOf("En proceso", "Finalizados"), tab, { tab = it }) }

            if (tab == 0) {
                datos.pendientes.filter { it.conflicto != null }.forEach { conflicto ->
                    item(key = "c-${conflicto.localId}") { AvisoConflicto(conflicto) { viewModel.descartar(conflicto.localId) } }
                }
                val pendientes = datos.pendientes.filter { it.conflicto == null }
                val filas: List<Any> = pendientes + datos.enProceso
                if (filas.isEmpty()) item { EstadoVacio("No hay lotes en proceso.") }
                filas.forEachIndexed { index, fila ->
                    item {
                        when (fila) {
                            is LotePendiente -> LoteItem(
                                titulo = fila.productoNombre,
                                subtitulo = "Sin enviar",
                                detalle = "${formatUnidades(fila.cantidad)} planificadas",
                                estado = "Pendiente" to Tono.ATENCION,
                                primera = index == 0,
                                ultima = index == filas.lastIndex,
                                onClick = null,
                            )
                            is ProduccionFila -> LoteItem(
                                titulo = tituloLote(fila),
                                subtitulo = "Lote ${fila.codigo}",
                                detalle = "${formatUnidades(fila.planificado, fila.unidadObtenido)} planificadas · ${formatLitros(fila.litros)} de leche",
                                estado = estadoLote(fila.estado),
                                primera = index == 0,
                                ultima = index == filas.lastIndex,
                                onClick = { onVerLote(fila.id) },
                            )
                        }
                    }
                }
            } else {
                if (datos.finalizados.isEmpty()) item { EstadoVacio("Todavía no hay lotes finalizados.") }
                datos.finalizados.forEachIndexed { index, fila ->
                    item(key = fila.id) {
                        LoteItem(
                            titulo = tituloLote(fila),
                            subtitulo = "Lote ${fila.codigo}",
                            detalle = "${formatUnidades(fila.obtenido ?: 0.0, fila.unidadObtenido)} obtenidas · ${formatLitros(fila.litros)} de leche",
                            estado = estadoLote(fila.estado),
                            primera = index == 0,
                            ultima = index == datos.finalizados.lastIndex,
                            onClick = { onVerLote(fila.id) },
                        )
                    }
                }
            }
        }
    }
}

/** "No se pudo iniciar la producción porque el inventario cambió mientras estabas sin conexión." */
@Composable
private fun AvisoConflicto(lote: LotePendiente, onDescartar: () -> Unit) {
    Row(
        modifier = Modifier.anchoContenido().background(EcoLactColors.RojoClaro, RoundedCornerShape(12.dp)).padding(start = 12.dp, top = 12.dp, bottom = 4.dp, end = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(EcoLactIcons.Warning, contentDescription = null, tint = EcoLactColors.Rojo, modifier = Modifier.size(20.dp))
        Column(Modifier.weight(1f)) {
            Text("${lote.productoNombre} · ${formatUnidades(lote.cantidad)}", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.Rojo)
            Text(lote.conflicto.orEmpty(), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.Rojo)
            TextButton(onClick = onDescartar, modifier = Modifier.align(Alignment.End)) { Text("Entendido", color = EcoLactColors.Rojo) }
        }
    }
}
