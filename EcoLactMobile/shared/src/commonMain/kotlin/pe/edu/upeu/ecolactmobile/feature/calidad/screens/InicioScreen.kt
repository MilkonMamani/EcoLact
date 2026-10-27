package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.StateFlow
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.primerNombre
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.PanelMuestras
import pe.edu.upeu.ecolactmobile.feature.calidad.components.AvisoConflicto
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.KpiPequena
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MuestraFila
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/** Inicio y "Muestras pendientes" observan el mismo panel (SQLite + cola local). */
class CalidadPanelViewModel(private val repository: CalidadRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<PanelMuestras>(repository.observePanel(), isOnline, { repository.refreshMuestras() }) {
    fun descartarConflicto(colaId: String) = repository.descartarConflicto(colaId)
}

private const val MAXIMO_EN_INICIO = 3

/**
 * Hola, María · dos tarjetas pequeñas · las primeras muestras pendientes. Solo aparecen las
 * entregas SELECCIONADAS como muestra, nunca todas las entregas sin análisis.
 */
@Composable
fun CalidadInicioScreen(
    viewModel: CalidadPanelViewModel,
    nombreFallback: String,
    onAnalizar: (Long) -> Unit,
    onVerTodas: () -> Unit,
    onIrAMuestreo: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val panel = success.data
        val nombre = panel.analista?.nombres?.let(::primerNombre) ?: primerNombre(nombreFallback)
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido()) {
                    Text("Hola, $nombre", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    Text("Analista de calidad", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                    if (success.fromCache) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            items(panel.conflictos.size) { i ->
                val conflicto = panel.conflictos[i]
                AvisoConflicto(conflicto) { viewModel.descartarConflicto(conflicto.colaId) }
            }
            item {
                Row(Modifier.anchoContenido(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    KpiPequena("Muestras pendientes", panel.pendientes.size, Modifier.weight(1f), destacado = true)
                    KpiPequena("Analizadas hoy", panel.analizadasHoy, Modifier.weight(1f))
                }
            }
            item {
                Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        "Muestras pendientes",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                        color = EcoLactColors.TextoPrincipal,
                        modifier = Modifier.weight(1f),
                    )
                    if (panel.pendientes.size > MAXIMO_EN_INICIO) {
                        TextButton(onClick = onVerTodas) { Text("Ver todos →", color = EcoLactColors.VerdeOscuro) }
                    }
                }
            }
            if (panel.pendientes.isEmpty()) {
                item { EstadoVacio("No tienes muestras pendientes. Selecciona una entrega en Muestreo.") }
                item { BotonPrincipal("Ir a Muestreo", onIrAMuestreo) }
            } else {
                val visibles = panel.pendientes.take(MAXIMO_EN_INICIO)
                itemsIndexed(visibles, key = { _, m -> m.entregaId }) { i, muestra ->
                    MuestraFila(muestra, primera = i == 0, ultima = i == visibles.lastIndex) { onAnalizar(muestra.entregaId) }
                }
                if (panel.pendientes.size <= MAXIMO_EN_INICIO) {
                    item {
                        TextButton(onClick = onVerTodas, modifier = Modifier.anchoContenido()) {
                            Text("Ver todos →", color = EcoLactColors.VerdeOscuro)
                        }
                    }
                }
            }
        }
    }
}

/** Lista completa de muestras pendientes (las seleccionadas que faltan analizar). */
@Composable
fun CalidadPendientesScreen(viewModel: CalidadPanelViewModel, onAnalizar: (Long) -> Unit, onIrAMuestreo: () -> Unit) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val panel = success.data
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 16.dp)) {
                    Text(
                        "${panel.pendientes.size} muestras por analizar",
                        style = MaterialTheme.typography.bodyMedium,
                        color = EcoLactColors.TextoSecundario,
                    )
                    if (success.fromCache) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            items(panel.conflictos.size) { i ->
                val conflicto = panel.conflictos[i]
                Column(Modifier.anchoContenido().padding(bottom = 16.dp)) {
                    AvisoConflicto(conflicto) { viewModel.descartarConflicto(conflicto.colaId) }
                }
            }
            if (panel.pendientes.isEmpty()) {
                item { EstadoVacio("No hay muestras pendientes.") }
                item { BotonPrincipal("Seleccionar muestra", onIrAMuestreo, operacion = true) }
            } else {
                itemsIndexed(panel.pendientes, key = { _, m -> m.entregaId }) { i, muestra ->
                    MuestraFila(muestra, primera = i == 0, ultima = i == panel.pendientes.lastIndex) { onAnalizar(muestra.entregaId) }
                }
            }
        }
    }
}
