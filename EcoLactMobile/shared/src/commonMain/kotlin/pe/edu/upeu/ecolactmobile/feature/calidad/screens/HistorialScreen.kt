package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.datetime.LocalDate
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.hoy
import pe.edu.upeu.ecolactmobile.core.util.inicioDeSemana
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import pe.edu.upeu.ecolactmobile.feature.calidad.AnalisisDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.HistorialFila
import pe.edu.upeu.ecolactmobile.feature.calidad.HistorialPanel
import pe.edu.upeu.ecolactmobile.feature.calidad.ResultadoCalidad
import pe.edu.upeu.ecolactmobile.feature.calidad.components.ResultadoChip
import pe.edu.upeu.ecolactmobile.feature.calidad.components.diaMesHora
import pe.edu.upeu.ecolactmobile.feature.calidad.components.litrosZona
import pe.edu.upeu.ecolactmobile.feature.calidad.ordenarPorParametro
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.FiltroDesplegable
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco

enum class PeriodoHistorial(val label: String) { SEMANA("Esta semana"), HOY("Hoy"), TODO("Todo") }

enum class FiltroResultado(val label: String, val resultado: ResultadoCalidad?) {
    TODOS("Todos los resultados", null),
    ACEPTADAS("Aceptadas", ResultadoCalidad.ACEPTADA),
    OBSERVADAS("Observadas", ResultadoCalidad.OBSERVADA),
    RECHAZADAS("Rechazadas", ResultadoCalidad.RECHAZADA),
}

/** Filtro del historial en el teléfono (sirve sin conexión). */
fun filtrarHistorial(filas: List<HistorialFila>, periodo: PeriodoHistorial, resultado: FiltroResultado, hoy: LocalDate): List<HistorialFila> =
    filas.filter { fila ->
        val fecha = (fila.fechaIso ?: fila.fecha).let { parseFechaLocal(it)?.date }
        val enPeriodo = when (periodo) {
            PeriodoHistorial.TODO -> true
            PeriodoHistorial.HOY -> fecha == hoy
            PeriodoHistorial.SEMANA -> fecha != null && fecha >= inicioDeSemana(hoy)
        }
        enPeriodo && fila.estado == "CONFIRMADO" && (resultado.resultado == null || fila.resultado == resultado.resultado.name)
    }

class CalidadHistorialViewModel(private val repository: CalidadRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<HistorialPanel>(repository.observeHistorial(), isOnline, { repository.refreshHistorial() }) {
    private val _periodo = MutableStateFlow(PeriodoHistorial.SEMANA)
    val periodo: StateFlow<PeriodoHistorial> = _periodo.asStateFlow()
    private val _resultado = MutableStateFlow(FiltroResultado.TODOS)
    val resultado: StateFlow<FiltroResultado> = _resultado.asStateFlow()

    fun onPeriodo(p: PeriodoHistorial) { _periodo.value = p }
    fun onResultado(r: FiltroResultado) { _resultado.value = r }
}

@Composable
fun CalidadHistorialScreen(viewModel: CalidadHistorialViewModel, onVerDetalle: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val periodo by viewModel.periodo.collectAsState()
    val resultado by viewModel.resultado.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val filas = filtrarHistorial(success.data.filas, periodo, resultado, hoy())
        val porEnviar = success.data.porEnviar
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Row(Modifier.anchoContenido().padding(bottom = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    FiltroDesplegable(periodo.label, periodo != PeriodoHistorial.SEMANA, PeriodoHistorial.entries.map { it.label }) {
                        viewModel.onPeriodo(PeriodoHistorial.entries[it])
                    }
                    FiltroDesplegable(resultado.label, resultado != FiltroResultado.TODOS, FiltroResultado.entries.map { it.label }) {
                        viewModel.onResultado(FiltroResultado.entries[it])
                    }
                }
            }
            if (success.fromCache) item { DatosGuardadosNote(success.cachedAtIso, Modifier.anchoContenido().padding(bottom = 8.dp)) }
            if (porEnviar.isNotEmpty()) {
                item { SectionHeader("Por enviar") }
                item {
                    Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        porEnviar.forEach { local ->
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text("Entrega #${local.entregaId}", style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
                                    Text(
                                        local.requiereRevision ?: "Analizado ${local.fechaAnalisis?.let(::formatHora).orEmpty()} · se enviará automáticamente",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = if (local.requiereRevision != null) EcoLactColors.Rojo else EcoLactColors.TextoSecundario,
                                    )
                                }
                                StatusChip(if (local.requiereRevision != null) "Revisar" else "Sin enviar", if (local.requiereRevision != null) Tono.ERROR else Tono.ATENCION)
                            }
                        }
                    }
                }
                item { SectionHeader("Analizadas", Modifier.padding(top = 16.dp)) }
            }
            if (filas.isEmpty()) {
                item { EstadoVacio("No hay análisis para estos filtros.") }
            } else {
                itemsIndexed(filas, key = { _, f -> f.id }) { i, fila ->
                    ProviderListItem(primera = i == 0, ultima = i == filas.lastIndex, onClick = { onVerDetalle(fila.id) }) {
                        ItemTitulo(fila.proveedorNombre ?: "Proveedor")
                        ItemSubtitulo(fila.litros?.let { litrosZona(it, fila.zonaNombre) } ?: fila.zonaNombre.orEmpty())
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
                            ResultadoChip(fila.resultado)
                            Text(diaMesHora(fila.fechaIso ?: fila.fecha), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
                        }
                    }
                }
            }
        }
    }
}

class CalidadHistorialDetalleViewModel(repository: CalidadRepository, isOnline: StateFlow<Boolean>, analisisId: Long) :
    LocalFirstViewModel<AnalisisDetalleResponse>(
        repository.observeHistorialDetalle(analisisId),
        isOnline,
        { repository.refreshHistorialDetalle(analisisId) },
        refreshOnStart = true,
    )

@Composable
fun CalidadHistorialDetalleScreen(viewModel: CalidadHistorialDetalleViewModel) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val d = success.data
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido()) {
                    Text(d.entrega?.proveedor_nombre ?: "Entrega #${d.entrega_id}", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                    d.entrega?.let { Text(litrosZona(it.litros, it.zona_nombre), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
                    if (success.fromCache) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            item {
                SummaryCard {
                    SummaryRowSlot("Resultado", primera = true) { ResultadoChip(d.resultado) }
                    d.fecha_analisis?.let { SummaryRow("Fecha", fechaLarga(it)) }
                }
            }
            item { SectionHeader("PARÁMETROS DE CALIDAD") }
            item {
                if (d.detalles.isEmpty()) {
                    EstadoVacio("Sin parámetros registrados.")
                } else {
                    SummaryCard {
                        ordenarPorParametro(d.detalles) { it.parametro_codigo }.forEachIndexed { i, p ->
                            SummaryRow(
                                p.parametro_nombre,
                                listOfNotNull(textoValor(p.valor), p.unidad?.takeIf { it.isNotBlank() && it != "pH" }).joinToString(" "),
                                primera = i == 0,
                                color = if (p.cumple == false) EcoLactColors.Rojo else null,
                            )
                        }
                    }
                }
            }
            item { SectionHeader("Observación") }
            item {
                Text(
                    d.observacion?.takeIf { it.isNotBlank() } ?: "Sin observaciones.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = EcoLactColors.TextoPrincipal,
                    modifier = Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
                )
            }
        }
    }
}

/** "27 oct 2026 · 10:15" */
private fun fechaLarga(iso: String): String {
    val f = parseFechaLocal(iso) ?: return iso
    return "${diaMesHora(iso).substringBefore(" ·")} ${f.year} · ${formatHora(iso)}"
}
