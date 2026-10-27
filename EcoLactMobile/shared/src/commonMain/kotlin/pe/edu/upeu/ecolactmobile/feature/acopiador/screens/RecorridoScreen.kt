package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.hoy
import pe.edu.upeu.ecolactmobile.core.util.parseFechaLocal
import pe.edu.upeu.ecolactmobile.feature.acopiador.AccionResult
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.EstadoParada
import pe.edu.upeu.ecolactmobile.feature.acopiador.ParadaVista
import pe.edu.upeu.ecolactmobile.feature.acopiador.RecorridoVista
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.FiltrosChips
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.fechaLarga
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
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
import pe.edu.upeu.ecolactmobile.feature.trabajador.LocalAutorizacion

fun estadoRecorrido(estado: String): Pair<String, Tono> = when (estado) {
    "EN_PROGRESO" -> "En curso" to Tono.EXITO
    "FINALIZADO" -> "Finalizado" to Tono.NEUTRAL
    "CANCELADO" -> "Cancelado" to Tono.ERROR
    else -> "Pendiente" to Tono.NEUTRAL
}

/** Recorridos guardados en el teléfono (abiertos o de los últimos días); "de hoy" primero. */
class AcopiadorRecorridosViewModel(private val repository: AcopiadorRepository, isOnline: StateFlow<Boolean>) :
    LocalFirstViewModel<List<RecorridoVista>>(repository.observeRecorridos(), isOnline, { repository.refreshRecorridos() }) {
    private val _mensaje = MutableStateFlow<String?>(null)
    val mensaje: StateFlow<String?> = _mensaje.asStateFlow()

    /** Sin conexión queda "En curso" en el teléfono y se envía al volver internet. */
    fun iniciar(id: Long, alTerminar: () -> Unit) {
        scope.launch {
            when (val r = repository.iniciarRecorrido(id)) {
                is AccionResult.Error -> _mensaje.value = r.mensaje
                else -> alTerminar()
            }
        }
    }
}

@Composable
fun AcopiadorRecorridoScreen(viewModel: AcopiadorRecorridosViewModel, onAbrir: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()
    val mensaje by viewModel.mensaje.collectAsState()

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val dia = hoy()
        val ordenados = success.data.sortedWith(compareByDescending<RecorridoVista> { parseFechaLocal(it.detalle.fecha_operativa)?.date == dia }.thenByDescending { it.abierto })
        val deHoy = ordenados.firstOrNull { parseFechaLocal(it.detalle.fecha_operativa)?.date == dia } ?: ordenados.firstOrNull { it.abierto }
        val otros = ordenados.filter { it !== deHoy }

        ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido()) {
                    Text("Recorrido de hoy", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                    Text(fechaLarga(dia), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                    if (success.fromCache && !success.cachedAtIso.isNullOrBlank()) DatosGuardadosNote(success.cachedAtIso)
                }
            }
            mensaje?.let { item { MensajeError(it) } }
            if (deHoy == null) {
                item { EstadoVacio("No tienes un recorrido asignado para hoy.") }
            } else {
                item { ResumenRecorrido(deHoy) }
                item {
                    when (deHoy.estado) {
                        "PENDIENTE" -> BotonPrincipal("Iniciar recorrido", { viewModel.iniciar(deHoy.detalle.id) { onAbrir(deHoy.detalle.id) } }, operacion = true)
                        "EN_PROGRESO" -> BotonPrincipal("Continuar recorrido", { onAbrir(deHoy.detalle.id) })
                        else -> BotonSecundario("Ver recorrido", { onAbrir(deHoy.detalle.id) })
                    }
                }
            }
            if (otros.isNotEmpty()) {
                item { SectionHeader("Otros recorridos") }
                itemsIndexed(otros, key = { _, r -> r.detalle.id }) { i, r ->
                    val (label, tono) = estadoRecorrido(r.estado)
                    ProviderListItem(
                        primera = i == 0,
                        ultima = i == otros.lastIndex,
                        onClick = { onAbrir(r.detalle.id) },
                        trailing = { StatusChip(label, tono) },
                    ) {
                        ItemTitulo(r.detalle.ruta_nombre)
                        ItemSubtitulo("${r.detalle.fecha_operativa} · ${r.visitados}/${r.paradas.size} visitados")
                    }
                }
            }
        }
    }
}

@Composable
private fun ResumenRecorrido(r: RecorridoVista) {
    val (label, tono) = estadoRecorrido(r.estado)
    SummaryCard {
        SummaryRow("Ruta", r.detalle.ruta_nombre, primera = true)
        SummaryRow("Zona", r.detalle.zona_nombre)
        SummaryRowSlot("Estado") { StatusChip(label, tono) }
        SummaryRow("Proveedores", "${r.paradas.size}")
        SummaryRow("Visitados", "${r.visitados}")
        SummaryRow("Litros", formatLitros(r.litros), destacado = true)
    }
}

// ---- Detalle: paradas ----

class AcopiadorRecorridoDetalleViewModel(private val repository: AcopiadorRepository, isOnline: StateFlow<Boolean>, private val recorridoId: Long) :
    LocalFirstViewModel<RecorridoVista?>(repository.observeRecorrido(recorridoId), isOnline, { repository.refreshRecorridos() }) {
    private val _mensaje = MutableStateFlow<String?>(null)
    val mensaje: StateFlow<String?> = _mensaje.asStateFlow()

    private val _ocupado = MutableStateFlow(false)
    val ocupado: StateFlow<Boolean> = _ocupado.asStateFlow()

    fun marcarNoEntrego(paradaId: Long, motivo: String) = ejecutar { repository.marcarNoEntrego(recorridoId, paradaId, motivo) }
    fun iniciar() = ejecutar { repository.iniciarRecorrido(recorridoId) }
    fun finalizar() = ejecutar { repository.finalizarRecorrido(recorridoId) }
    fun limpiarMensaje() { _mensaje.value = null }

    private fun ejecutar(accion: suspend () -> AccionResult) {
        scope.launch {
            _ocupado.value = true
            _mensaje.value = (accion() as? AccionResult.Error)?.mensaje
            _ocupado.value = false
        }
    }
}

@Composable
fun AcopiadorRecorridoDetalleScreen(viewModel: AcopiadorRecorridoDetalleViewModel, onRegistrar: (ParadaVista) -> Unit) {
    val state by viewModel.state.collectAsState()
    val mensaje by viewModel.mensaje.collectAsState()
    val ocupado by viewModel.ocupado.collectAsState()
    var noEntregoDe by remember { mutableStateOf<ParadaVista?>(null) }
    var confirmarFinalizar by remember { mutableStateOf(false) }

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val r = success.data
        if (r == null) {
            EstadoVacio("Este recorrido ya no está disponible.")
            return@ContenidoLocal
        }
        val (label, tono) = estadoRecorrido(r.estado)
        ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
            item {
                Column(Modifier.anchoContenido().padding(bottom = 16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(r.detalle.ruta_nombre, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal, modifier = Modifier.weight(1f))
                        StatusChip(label, tono)
                    }
                    Text(
                        "${r.detalle.zona_nombre} · ${r.visitados} de ${r.paradas.size} visitados · ${formatLitros(r.litros)}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = EcoLactColors.TextoSecundario,
                    )
                    if (r.pendientesEnvio > 0) {
                        Text("${r.pendientesEnvio} registros guardados en el teléfono, se enviarán solos.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.NaranjaOscuro)
                    }
                    if (success.fromCache && !success.cachedAtIso.isNullOrBlank()) DatosGuardadosNote(success.cachedAtIso)
                    mensaje?.let { MensajeError(it, Modifier.padding(top = 8.dp)) }
                    if (r.estado == "PENDIENTE") BotonPrincipal("Iniciar recorrido", viewModel::iniciar, modifier = Modifier.padding(top = 8.dp), cargando = ocupado, operacion = true)
                }
            }
            if (r.paradas.isEmpty()) item { EstadoVacio("Este recorrido no tiene paradas.") }
            itemsIndexed(r.paradas, key = { _, p -> p.parada.id }) { i, p ->
                ParadaFila(
                    parada = p,
                    primera = i == 0,
                    ultima = i == r.paradas.lastIndex,
                    operable = r.estado == "EN_PROGRESO",
                    onRegistrar = { onRegistrar(p) },
                    onNoEntrego = { noEntregoDe = p },
                )
            }
            if (r.abierto && r.estado == "EN_PROGRESO") {
                item {
                    BotonSecundario("Finalizar recorrido", { confirmarFinalizar = true }, modifier = Modifier.padding(top = 24.dp), enabled = !ocupado, operacion = true)
                }
            }
        }
    }

    noEntregoDe?.let { p ->
        NoEntregoDialog(p.parada.proveedor_nombre, onDismiss = { noEntregoDe = null }) { motivo ->
            viewModel.marcarNoEntrego(p.parada.id, motivo)
            noEntregoDe = null
        }
    }
    if (confirmarFinalizar) {
        AlertDialog(
            onDismissRequest = { confirmarFinalizar = false },
            title = { Text("Finalizar recorrido") },
            text = { Text("Ya no podrás registrar acopios en este recorrido.") },
            confirmButton = { TextButton(onClick = { confirmarFinalizar = false; viewModel.finalizar() }) { Text("Finalizar", color = EcoLactColors.VerdeOscuro) } },
            dismissButton = { TextButton(onClick = { confirmarFinalizar = false }) { Text("Cancelar") } },
            containerColor = EcoLactColors.Blanco,
        )
    }
}

/** "1. María Quispe" · Pendiente [Registrar] / 20 L Registrado / No entregó. */
@Composable
private fun ParadaFila(parada: ParadaVista, primera: Boolean, ultima: Boolean, operable: Boolean, onRegistrar: () -> Unit, onNoEntrego: () -> Unit) {
    ProviderListItem(primera = primera, ultima = ultima, onClick = null) {
        ItemTitulo("${parada.parada.orden_visita}. ${parada.parada.proveedor_nombre}")
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            val (texto, color) = when (parada.estado) {
                EstadoParada.RECOLECTADO -> "${formatLitros(parada.litros)} · Registrado" to EcoLactColors.VerdeOscuro
                EstadoParada.NO_ENTREGO -> "No entregó" + (parada.motivo?.let { " · $it" } ?: "") to EcoLactColors.TextoSecundario
                EstadoParada.PENDIENTE -> "Pendiente" to EcoLactColors.TextoSecundario
            }
            Text(texto, style = MaterialTheme.typography.bodyMedium, color = color)
            if (parada.pendienteEnvio) {
                Box(Modifier.size(7.dp).background(EcoLactColors.Naranja, CircleShape))
                Text("por enviar", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.NaranjaOscuro)
            }
        }
        parada.parada.referencia_ubicacion?.takeIf { it.isNotBlank() }?.let { ItemSubtitulo(it) }
        parada.errorSync?.let { Text("Error de sincronización: $it", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.Rojo) }
        if (operable && parada.estado == EstadoParada.PENDIENTE) {
            Row(Modifier.fillMaxWidth().padding(top = 6.dp), horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.End)) {
                val puedeOperar = LocalAutorizacion.current.puedeOperar
                TextButton(onClick = onNoEntrego, enabled = puedeOperar) { Text("No entregó", color = EcoLactColors.TextoSecundario) }
                Button(
                    onClick = onRegistrar,
                    enabled = puedeOperar,
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                    modifier = Modifier.height(40.dp),
                ) { Text("Registrar") }
            }
        }
    }
}

private val MOTIVOS = listOf("No tenía leche", "No estaba", "Otro")

@Composable
private fun NoEntregoDialog(proveedor: String, onDismiss: () -> Unit, onConfirmar: (String) -> Unit) {
    var opcion by remember { mutableStateOf(MOTIVOS.first()) }
    var otro by remember { mutableStateOf("") }
    val motivo = if (opcion == "Otro") otro.trim() else opcion
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("No entregó") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(proveedor, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
                FiltrosChips(MOTIVOS, opcion, { it }, { opcion = it })
                if (opcion == "Otro") {
                    OutlinedTextField(value = otro, onValueChange = { otro = it.take(255) }, label = { Text("Motivo") }, shape = RoundedCornerShape(12.dp))
                }
            }
        },
        confirmButton = { TextButton(enabled = motivo.isNotBlank(), onClick = { onConfirmar(motivo) }) { Text("Guardar", color = EcoLactColors.VerdeOscuro) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancelar") } },
        containerColor = EcoLactColors.Blanco,
    )
}
