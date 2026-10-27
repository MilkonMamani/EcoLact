package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
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
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.state.LocalFirstViewModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatDecimal
import pe.edu.upeu.ecolactmobile.feature.acopiador.AccionResult
import pe.edu.upeu.ecolactmobile.feature.acopiador.AcopiadorRepository
import pe.edu.upeu.ecolactmobile.feature.acopiador.ControlCalidad
import pe.edu.upeu.ecolactmobile.feature.acopiador.EntregaVista
import pe.edu.upeu.ecolactmobile.feature.acopiador.EstadoRegistro
import pe.edu.upeu.ecolactmobile.feature.acopiador.SeguimientoDetalleResponse
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.ControlCalidadChip
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.EstadoRegistroIndicador
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.fechaHoraLarga
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ContenidoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SectionHeader
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

class AcopiadorEntregaDetalleViewModel(
    private val repository: AcopiadorRepository,
    private val isOnline: StateFlow<Boolean>,
    clave: String,
) : LocalFirstViewModel<EntregaVista?>(repository.observeEntrega(clave), isOnline, { repository.refreshEntregas() }) {
    private val serverId: Long? = clave.removePrefix("s-").toLongOrNull()?.takeIf { clave.startsWith("s-") }

    /** Resultado del análisis (fecha, observación) si la entrega fue muestreada; null mientras no se descargue. */
    val seguimiento = serverId?.let(repository::observeSeguimientoDetalle) ?: flowOf(null)

    init {
        if (serverId != null && isOnline.value) scope.launch { runCatching { repository.refreshSeguimientoDetalle(serverId) } }
    }

    private val _mensaje = MutableStateFlow<String?>(null)
    val mensaje: StateFlow<String?> = _mensaje.asStateFlow()

    /** Los 10 minutos cuentan desde la hora real del acopio, no desde la sincronización. */
    fun litrosEditables(entrega: EntregaVista): Boolean = AcopiadorRepository.litrosEditables(entrega.fechaHora)

    fun corregir(localId: String, litros: Double, observacion: String?) {
        _mensaje.value = (repository.corregirEntrega(localId, litros, observacion) as? AccionResult.Error)?.mensaje
    }
    fun descartar(localId: String) = repository.descartarEntregaRechazada(localId)
}

@Composable
fun AcopiadorEntregaDetalleScreen(viewModel: AcopiadorEntregaDetalleViewModel, onDescartada: () -> Unit) {
    val state by viewModel.state.collectAsState()
    val seguimiento by viewModel.seguimiento.collectAsState(null)
    val mensaje by viewModel.mensaje.collectAsState()
    var corrigiendo by remember { mutableStateOf(false) }
    var confirmarDescartar by remember { mutableStateOf(false) }

    ContenidoLocal(state, onRetry = viewModel::refresh) { success ->
        val entrega = success.data
        if (entrega == null) {
            EstadoVacio("Esta entrega ya no está disponible en el teléfono.")
            return@ContenidoLocal
        }
        ProveedorPantalla {
            item {
                Text(entrega.proveedorNombre, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal, modifier = Modifier.anchoContenido())
            }
            item {
                SummaryCard {
                    SummaryRow("DNI", entrega.proveedorDni ?: "—", primera = true)
                    SummaryRow("Zona", entrega.zonaNombre ?: "—")
                    SummaryRow("Fecha y hora", fechaHoraLarga(entrega.fechaHora))
                    SummaryRow("Cantidad de leche", "${formatDecimal(entrega.litros, 1).removeSuffix(".0")} litros", destacado = true)
                    SummaryRow("Observación", entrega.observacion ?: "Sin observación")
                }
            }
            item { SectionHeader("Estado de registro") }
            item { EstadoRegistroCard(entrega, onCorregir = { corrigiendo = true }, onDescartar = { confirmarDescartar = true }) }
            mensaje?.let { item { MensajeError(it) } }
            item { SectionHeader("Control de calidad") }
            item { ControlCalidadCard(entrega, seguimiento) }
        }

        if (corrigiendo && entrega.localId != null) {
            CorregirDialog(entrega, viewModel.litrosEditables(entrega), onDismiss = { corrigiendo = false }) { litros, obs ->
                viewModel.corregir(entrega.localId, litros, obs)
                corrigiendo = false
            }
        }
        if (confirmarDescartar && entrega.localId != null) {
            AlertDialog(
                onDismissRequest = { confirmarDescartar = false },
                title = { Text("Descartar registro") },
                text = { Text("Esta entrega no llegó al servidor. Si la descartas se borrará del teléfono.") },
                confirmButton = {
                    TextButton(onClick = {
                        viewModel.descartar(entrega.localId)
                        confirmarDescartar = false
                        onDescartada()
                    }) { Text("Descartar", color = EcoLactColors.Rojo) }
                },
                dismissButton = { TextButton(onClick = { confirmarDescartar = false }) { Text("Cancelar") } },
                containerColor = EcoLactColors.Blanco,
            )
        }
    }
}

@Composable
private fun EstadoRegistroCard(entrega: EntregaVista, onCorregir: () -> Unit, onDescartar: () -> Unit) {
    SummaryCard {
        Column(Modifier.fillMaxWidth().padding(vertical = 14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            EstadoRegistroIndicador(entrega.registro)
            Text(
                when (entrega.registro) {
                    EstadoRegistro.SINCRONIZADA -> "Registrada en el servidor."
                    EstadoRegistro.PENDIENTE -> "Guardada en el teléfono. Se enviará sola cuando haya conexión."
                    EstadoRegistro.ERROR -> "Error de sincronización: ${entrega.errorSync ?: "el servidor no aceptó el registro."}"
                },
                style = MaterialTheme.typography.bodyMedium,
                color = if (entrega.registro == EstadoRegistro.ERROR) EcoLactColors.Rojo else EcoLactColors.TextoSecundario,
            )
            if (entrega.registro == EstadoRegistro.ERROR) {
                Text("La entrega sigue guardada en el teléfono para revisión.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
                BotonPrincipal("Corregir y reintentar", onCorregir, modifier = Modifier.padding(top = 8.dp))
                BotonSecundario("Descartar registro", onDescartar)
            }
        }
    }
}

/**
 * Con el muestreo, no toda entrega se analiza: "Sin muestreo", "Seleccionada para análisis",
 * "En análisis" o el resultado. Una entrega que aún no llega al servidor todavía no tiene estado.
 */
@Composable
private fun ControlCalidadCard(entrega: EntregaVista, seguimiento: SeguimientoDetalleResponse?) {
    val control = ControlCalidad.from(seguimiento?.control_calidad ?: entrega.controlCalidad)
    SummaryCard {
        Column(Modifier.fillMaxWidth().padding(vertical = 14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            if (control == null) {
                Text(
                    if (entrega.registro == EstadoRegistro.SINCRONIZADA) "Se actualizará en unos segundos." else "Se conocerá cuando la entrega llegue al servidor.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = EcoLactColors.TextoSecundario,
                )
            } else {
                ControlCalidadChip(control.api)
                Text(control.detalle, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                seguimiento?.analisis?.fecha_analisis?.let {
                    Text("Analizada: ${fechaHoraLarga(it)}", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
                }
                seguimiento?.analisis?.observacion?.takeIf { it.isNotBlank() }?.let {
                    Text(it, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoPrincipal)
                }
            }
        }
    }
}

@Composable
private fun CorregirDialog(entrega: EntregaVista, litrosEditables: Boolean, onDismiss: () -> Unit, onGuardar: (Double, String?) -> Unit) {
    var litros by remember { mutableStateOf(formatDecimal(entrega.litros, 1).removeSuffix(".0")) }
    var observacion by remember { mutableStateOf(entrega.observacion.orEmpty()) }
    val valor = litros.replace(',', '.').toDoubleOrNull()
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Corregir entrega") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(
                    value = litros,
                    onValueChange = { litros = it },
                    label = { Text("Litros") },
                    singleLine = true,
                    enabled = litrosEditables,
                    supportingText = if (litrosEditables) null else ({ Text("Pasaron más de 10 minutos desde la hora de acopio.") }),
                    isError = valor == null || valor <= 0.0,
                    shape = RoundedCornerShape(12.dp),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                )
                OutlinedTextField(
                    value = observacion,
                    onValueChange = { observacion = it },
                    label = { Text("Observación") },
                    shape = RoundedCornerShape(12.dp),
                )
            }
        },
        confirmButton = {
            TextButton(enabled = valor != null && valor > 0.0, onClick = { onGuardar(valor ?: 0.0, observacion) }) {
                Text("Guardar y reintentar", color = EcoLactColors.VerdeOscuro)
            }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancelar") } },
        containerColor = EcoLactColors.Blanco,
    )
}
