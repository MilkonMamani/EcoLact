package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.calidad.EntregaElegible
import pe.edu.upeu.ecolactmobile.feature.calidad.TipoControl
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.calidad.components.litrosZona
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.EstadoVacio
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.FiltroDesplegable
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemSubtitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ItemTitulo
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProviderListItem
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.LocalAutorizacion

/**
 * Muestreo: el analista elige qué entregas evalúa Calidad. Al azar (lo sortea el servidor) o
 * manualmente; el muestreo directo en campo queda como acción secundaria.
 */
@Composable
fun MuestreoScreen(
    viewModel: MuestreoViewModel,
    isOnline: Boolean,
    onAnalizar: (Long) -> Unit,
    onVerPendientes: () -> Unit,
    onMuestreoDirecto: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    when (state.modo) {
        ModoMuestreo.INICIO -> InicioMuestreo(state, isOnline, viewModel, onAnalizar, onVerPendientes, onMuestreoDirecto)
        ModoMuestreo.ALEATORIA -> MuestraAleatoria(state, viewModel)
        ModoMuestreo.MANUAL -> SeleccionManual(state, viewModel)
    }
    state.seleccion?.let { ConfirmarMuestraSheet(it, state.confirmando, state.error, viewModel) }
}

@Composable
private fun InicioMuestreo(
    state: MuestreoUiState,
    isOnline: Boolean,
    viewModel: MuestreoViewModel,
    onAnalizar: (Long) -> Unit,
    onVerPendientes: () -> Unit,
    onMuestreoDirecto: () -> Unit,
) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido()) {
                Text("Muestreo", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                Text(
                    "Selecciona las entregas que serán evaluadas por Calidad.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = EcoLactColors.TextoSecundario,
                )
            }
        }
        state.elegida?.let { elegida ->
            item {
                Column(
                    Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(20.dp))
                        Text("Muestra seleccionada", style = MaterialTheme.typography.titleSmall, color = EcoLactColors.VerdeOscuro)
                    }
                    Text(elegida.proveedorNombre, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
                    Text(
                        if (elegida.guardadaLocal) "Guardada en el dispositivo. Se enviará automáticamente al volver la conexión." else "Ya aparece en Muestras pendientes.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = EcoLactColors.TextoSecundario,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        TextButton(onClick = { viewModel.cerrarElegida(); onAnalizar(elegida.entregaId) }) {
                            Text("Analizar ahora", color = EcoLactColors.VerdeOscuro)
                        }
                        TextButton(onClick = { viewModel.cerrarElegida(); onVerPendientes() }) {
                            Text("Ver pendientes", color = EcoLactColors.TextoSecundario)
                        }
                    }
                }
            }
        }
        state.error?.let { item { MensajeError(it) } }
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                BotonPrincipal("Elegir muestra al azar", viewModel::elegirAlAzar, enabled = isOnline, icon = EcoLactIcons.Shuffle, operacion = true)
                if (!isOnline) {
                    Text(
                        "Se necesita conexión para elegir una muestra aleatoria. Puedes seleccionar manualmente.",
                        style = MaterialTheme.typography.bodySmall,
                        color = EcoLactColors.NaranjaOscuro,
                    )
                }
                BotonSecundario("Seleccionar manualmente", viewModel::seleccionarManualmente, icon = EcoLactIcons.Search, operacion = true)
            }
        }
        item {
            Column(Modifier.anchoContenido().padding(top = 8.dp)) {
                Text("¿Estás en campo con el proveedor?", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                TextButton(onClick = onMuestreoDirecto, enabled = LocalAutorizacion.current.puedeOperar, contentPadding = ButtonDefaults.TextButtonContentPadding) {
                    Icon(EcoLactIcons.QrCode, contentDescription = null, tint = EcoLactColors.VerdeOscuro, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.size(8.dp))
                    Text("Muestreo directo", color = EcoLactColors.VerdeOscuro)
                }
            }
        }
    }
}

@Composable
private fun MuestraAleatoria(state: MuestreoUiState, viewModel: MuestreoViewModel) {
    ProveedorPantalla {
        item {
            Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                Text("Muestra seleccionada", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                TextButton(onClick = viewModel::volver) { Text("Cancelar", color = EcoLactColors.TextoSecundario) }
            }
        }
        val propuesta = state.propuesta
        when {
            state.sorteando && propuesta == null -> item {
                Column(Modifier.anchoContenido().padding(vertical = 32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = EcoLactColors.VerdePrincipal)
                    Text("Eligiendo al azar…", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, modifier = Modifier.padding(top = 12.dp))
                }
            }
            propuesta == null -> {
                state.error?.let { item { MensajeError(it) } }
                item { BotonSecundario("Volver", viewModel::volver) }
            }
            else -> {
                val e = propuesta.entrega
                item {
                    Column(Modifier.anchoContenido()) {
                        Text(e.proveedorNombre, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                        e.proveedorDni?.let { Text("DNI $it", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
                    }
                }
                item {
                    SummaryCard {
                        SummaryRow("Litros", formatLitros(e.litros), primera = true, destacado = true)
                        SummaryRow("Zona", e.zonaNombre ?: "—")
                        SummaryRow("Acopiador", e.acopiadorNombre ?: "—")
                        SummaryRow("Hora de entrega", formatHora(e.fechaHora))
                        SummaryRow("Método", "Aleatoria")
                    }
                }
                state.error?.let { item { MensajeError(it) } }
                item { BotonPrincipal("Confirmar muestra", viewModel::confirmarAleatoria, cargando = state.confirmando, operacion = true) }
                item { BotonSecundario("Elegir otra", viewModel::elegirOtra, enabled = !state.sorteando && !state.confirmando) }
            }
        }
    }
}

@Composable
private fun SeleccionManual(state: MuestreoUiState, viewModel: MuestreoViewModel) {
    val filas = state.elegiblesFiltradas
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
        item {
            Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                Text("Seleccionar manualmente", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                TextButton(onClick = viewModel::volver) { Text("Cancelar", color = EcoLactColors.TextoSecundario) }
            }
        }
        item {
            OutlinedTextField(
                value = state.busqueda,
                onValueChange = viewModel::onBusqueda,
                placeholder = { Text("Buscar proveedor o DNI...") },
                leadingIcon = { Icon(EcoLactIcons.Search, contentDescription = null) },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = EcoLactColors.Blanco,
                    unfocusedContainerColor = EcoLactColors.Blanco,
                    focusedBorderColor = EcoLactColors.VerdePrincipal,
                    unfocusedBorderColor = EcoLactColors.Bordes,
                ),
                modifier = Modifier.anchoContenido().padding(top = 8.dp),
            )
        }
        item {
            Row(Modifier.anchoContenido().padding(vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                FiltroDesplegable(
                    label = state.fecha.label,
                    activo = state.fecha != FechaMuestreo.HOY,
                    opciones = FechaMuestreo.entries.map { it.label },
                ) { viewModel.onFecha(FechaMuestreo.entries[it]) }
                Spacer(Modifier.weight(1f))
                state.listaGuardadaEn?.let { DatosGuardadosNote(it) }
            }
        }
        state.error?.let { item { MensajeError(it, Modifier.padding(bottom = 8.dp)) } }
        when {
            state.cargandoLista && filas.isEmpty() -> item {
                Column(Modifier.anchoContenido().padding(vertical = 32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = EcoLactColors.VerdePrincipal)
                }
            }
            filas.isEmpty() -> item { EstadoVacio("No hay entregas disponibles para muestrear.") }
            else -> itemsIndexed(filas, key = { _, e -> e.entregaId }) { i, entrega ->
                FilaElegible(entrega, primera = i == 0, ultima = i == filas.lastIndex) { viewModel.abrirSeleccion(entrega) }
            }
        }
    }
}

@Composable
private fun FilaElegible(entrega: EntregaElegible, primera: Boolean, ultima: Boolean, onSeleccionar: () -> Unit) {
    ProviderListItem(
        primera = primera,
        ultima = ultima,
        onClick = null,
        trailing = {
            Button(
                onClick = onSeleccionar,
                enabled = LocalAutorizacion.current.puedeOperar,
                shape = RoundedCornerShape(10.dp),
                colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdeClaro, contentColor = EcoLactColors.VerdeOscuro),
                contentPadding = ButtonDefaults.TextButtonContentPadding,
                modifier = Modifier.heightIn(min = 40.dp),
            ) { Text("Seleccionar", style = MaterialTheme.typography.labelLarge) }
        },
    ) {
        ItemTitulo(entrega.proveedorNombre)
        ItemSubtitulo(litrosZona(entrega.litros, entrega.zonaNombre))
        Text(formatHora(entrega.fechaHora), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ConfirmarMuestraSheet(seleccion: SeleccionEnCurso, confirmando: Boolean, error: String?, viewModel: MuestreoViewModel) {
    ModalBottomSheet(
        onDismissRequest = viewModel::cerrarSeleccion,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = EcoLactColors.Blanco,
    ) {
        Column(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp).navigationBarsPadding().padding(bottom = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Seleccionar para control de calidad", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            Column {
                Text(seleccion.entrega.proveedorNombre, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
                Text(litrosZona(seleccion.entrega.litros, seleccion.entrega.zonaNombre), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            }
            Text("Método", style = MaterialTheme.typography.labelLarge, color = EcoLactColors.TextoSecundario)
            TipoControl.entries.forEach { tipo ->
                Row(
                    Modifier
                        .fillMaxWidth()
                        .heightIn(min = 44.dp)
                        .selectable(selected = seleccion.tipoControl == tipo, role = Role.RadioButton) { viewModel.onTipoControl(tipo) },
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    RadioButton(
                        selected = seleccion.tipoControl == tipo,
                        onClick = null,
                        colors = RadioButtonDefaults.colors(selectedColor = EcoLactColors.VerdePrincipal),
                    )
                    Text(tipo.label, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.padding(start = 8.dp))
                }
            }
            OutlinedTextField(
                value = seleccion.observacion,
                onValueChange = viewModel::onObservacion,
                label = { Text("Observación (opcional)") },
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth(),
            )
            error?.let { MensajeError(it) }
            Spacer(Modifier.height(4.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                BotonSecundario("Cancelar", viewModel::cerrarSeleccion, modifier = Modifier.weight(1f))
                BotonPrincipal("Confirmar muestra", viewModel::confirmarManual, modifier = Modifier.weight(1f), cargando = confirmando, operacion = true)
            }
        }
    }
}
