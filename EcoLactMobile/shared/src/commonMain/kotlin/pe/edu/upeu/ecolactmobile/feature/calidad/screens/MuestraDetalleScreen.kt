package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
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
import kotlinx.coroutines.flow.combine
import kotlinx.datetime.TimeZone
import kotlinx.datetime.toLocalDateTime
import pe.edu.upeu.ecolactmobile.core.cache.CachedValue
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatDiaMes
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.CapturaLocal
import pe.edu.upeu.ecolactmobile.feature.calidad.EstadoCaptura
import pe.edu.upeu.ecolactmobile.feature.calidad.MetodoSeleccion
import pe.edu.upeu.ecolactmobile.feature.calidad.MuestraPendiente
import pe.edu.upeu.ecolactmobile.feature.calidad.TipoControl
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.litrosConfirmados
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.StatusChip
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRowSlot
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import kotlin.time.Clock
import kotlin.time.ExperimentalTime

data class MuestraDetalle(val muestra: MuestraPendiente?, val captura: CapturaLocal?)

class MuestraDetalleViewModel(repository: CalidadRepository, isOnline: StateFlow<Boolean>, entregaId: Long) :
    LocalFirstViewModel<MuestraDetalle>(
        source = combine(repository.observeMuestra(entregaId), repository.observeCaptura(entregaId)) { muestra, captura ->
            muestra?.let { CachedValue(MuestraDetalle(it.value, captura), it.cachedAtIso) }
        },
        isOnline = isOnline,
        refresher = { repository.refreshMuestras() },
    )

/** Detalle de la muestra: datos de la entrega y de la selección, y cómo registrar el análisis. */
@OptIn(ExperimentalTime::class)
@Composable
fun MuestraDetalleScreen(viewModel: MuestraDetalleViewModel, onEscanear: () -> Unit, onIngresarManual: () -> Unit) {
    val state by viewModel.state.collectAsState()
    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val muestra = success.data.muestra
        val captura = success.data.captura
        if (muestra == null) {
            EstadoVacio("Esta muestra ya no está pendiente de análisis.")
            return@ContenidoLocal
        }
        val ahora = Clock.System.now().toLocalDateTime(TimeZone.currentSystemDefault())
        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido()) {
                    Text(muestra.proveedorNombre, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                    muestra.proveedorDni?.let { Text("DNI $it", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
                    if (success.fromCache) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            item {
                SummaryCard {
                    SummaryRowSlot("Litros entregados", primera = true) {
                        Column(horizontalAlignment = Alignment.End) {
                            Text(formatLitros(muestra.litros), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.VerdeOscuro)
                            // Solo informativo: los 10 minutos limitan corregir litros, nunca el análisis.
                            if (litrosConfirmados(muestra.litrosEditablesHasta, ahora)) {
                                Text("Litros confirmados", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
                            }
                        }
                    }
                    SummaryRow("Zona", muestra.zonaNombre ?: "—")
                    SummaryRow("Fecha y hora", "${formatDiaMes(muestra.fechaHora).lowercase()} · ${formatHora(muestra.fechaHora)}")
                    SummaryRow("Acopiador", muestra.acopiadorNombre ?: if (muestra.metodo == MetodoSeleccion.DIRECTA) "Sin acopiador (muestreo directo)" else "—")
                    SummaryRowSlot("Estado") { StatusChip("Pendiente de análisis", Tono.ATENCION) }
                    SummaryRow("Método de selección", metodoTexto(muestra))
                    if (muestra.fechaSeleccion.isNotBlank()) SummaryRow("Fecha de selección", formatHora(muestra.fechaSeleccion))
                    muestra.observacion?.takeIf { it.isNotBlank() }?.let { SummaryRow("Observación", it) }
                }
            }
            captura?.let { item { EstadoCapturaCard(it) } }
            item {
                Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    if (captura != null) {
                        BotonPrincipal(if (captura.estado == EstadoCaptura.REQUIERE_REVISION) "Revisar datos" else "Ingresar valores", onIngresarManual, operacion = true, icon = EcoLactIcons.Edit)
                        BotonSecundario("Tomar otra foto", onEscanear, icon = EcoLactIcons.PhotoCamera, operacion = true)
                    } else {
                        BotonPrincipal("Escanear ticket", onEscanear, icon = EcoLactIcons.PhotoCamera, operacion = true)
                        BotonSecundario("Ingresar manualmente", onIngresarManual, icon = EcoLactIcons.Edit, operacion = true)
                    }
                }
            }
        }
    }
}

private fun metodoTexto(muestra: MuestraPendiente): String = when (muestra.metodo) {
    MetodoSeleccion.MANUAL -> TipoControl.entries.firstOrNull { it.api == muestra.tipoControl }?.let { "Manual · ${it.label.lowercase()}" } ?: "Manual"
    else -> muestra.metodo.label
}

@Composable
private fun EstadoCapturaCard(captura: CapturaLocal) {
    val (titulo, detalle) = when (captura.estado) {
        EstadoCaptura.PENDIENTE_OCR -> "Foto guardada" to "Se leerá automáticamente cuando vuelva la conexión. Puedes ingresar los valores a mano."
        EstadoCaptura.REQUIERE_REVISION -> "Datos detectados" to (captura.mensajeOcr ?: "Revisa cada valor antes de confirmar.")
    }
    Row(
        Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Icon(
            if (captura.estado == EstadoCaptura.PENDIENTE_OCR) EcoLactIcons.CloudOff else EcoLactIcons.CheckCircle,
            contentDescription = null,
            tint = if (captura.estado == EstadoCaptura.PENDIENTE_OCR) EcoLactColors.Naranja else EcoLactColors.VerdePrincipal,
            modifier = Modifier.size(22.dp),
        )
        Column {
            Text(titulo, style = MaterialTheme.typography.titleSmall)
            Text(detalle, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            Text("Foto: ${formatHora(captura.fechaCapturaLocal)}", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario, modifier = Modifier.padding(top = 4.dp))
        }
    }
}
