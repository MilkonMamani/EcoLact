package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.QrScannerView
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.feature.calidad.RegistrarEntregaMuestreadaResult
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.calidad.components.PantallaGuardadoLocal
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.ProveedorPantalla
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryCard
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.SummaryRow
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

/**
 * Muestreo directo: inspección sorpresa en campo. QR o DNI → identificar proveedor → cantidad
 * de muestra → continuar a análisis.
 */
@Composable
fun MuestreoDirectoScreen(viewModel: MuestreoDirectoViewModel, onAnalizar: (Long) -> Unit, onListo: () -> Unit) {
    val state by viewModel.state.collectAsState()

    if (state.mostrandoScanner) {
        Box(Modifier.fillMaxSize().background(Color.Black)) {
            QrScannerView(modifier = Modifier.fillMaxSize(), onScanned = viewModel::onQrEscaneado)
            TextButton(onClick = viewModel::onCerrarScanner, modifier = Modifier.align(Alignment.BottomCenter).navigationBarsPadding().padding(24.dp)) {
                Text("Cancelar", color = EcoLactColors.Blanco)
            }
        }
        return
    }

    when (state.step) {
        MuestreoDirectoStep.IDENTIFICAR -> Identificar(state, viewModel)
        MuestreoDirectoStep.CANTIDAD -> Cantidad(state, viewModel)
        MuestreoDirectoStep.CONFIRMAR -> Confirmar(state, viewModel)
        MuestreoDirectoStep.RESULTADO -> Resultado(state, viewModel, onAnalizar, onListo)
    }
}

@Composable
private fun Identificar(state: MuestreoDirectoUiState, viewModel: MuestreoDirectoViewModel) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido()) {
                Text("Muestreo directo", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                Text(
                    "Inspección en campo: identifica al proveedor y toma la muestra antes del acopio.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = EcoLactColors.TextoSecundario,
                )
            }
        }
        item { BotonPrincipal("Escanear QR", viewModel::onAbrirScanner, icon = EcoLactIcons.QrCode) }
        item { Text("o", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center, modifier = Modifier.anchoContenido()) }
        item {
            OutlinedTextField(
                value = state.dniInput,
                onValueChange = viewModel::onDniInputChange,
                label = { Text("DNI del proveedor") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.anchoContenido(),
            )
        }
        item { BotonSecundario(if (state.buscandoProveedor) "Buscando…" else "Buscar", viewModel::onBuscarDni, enabled = !state.buscandoProveedor, icon = EcoLactIcons.Search) }
        state.busquedaError?.let { item { MensajeError(it) } }
        state.proveedorPreview?.let { proveedor ->
            item {
                Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp)) {
                    Text("Proveedor encontrado", style = MaterialTheme.typography.labelLarge, color = EcoLactColors.VerdeOscuro)
                    Text(proveedor.nombre, style = MaterialTheme.typography.titleMedium)
                    Text("DNI ${proveedor.dni}", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                }
            }
            item { BotonPrincipal("Continuar", viewModel::onContinuarDesdeIdentificacion) }
        }
    }
}

@Composable
private fun Cantidad(state: MuestreoDirectoUiState, viewModel: MuestreoDirectoViewModel) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido()) {
                Text(state.proveedorPreview?.nombre ?: "Proveedor identificado por QR", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Text("Cantidad de muestra", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            }
        }
        item {
            OutlinedTextField(
                value = state.litros,
                onValueChange = viewModel::onLitrosChange,
                label = { Text("Litros") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                singleLine = true,
                textStyle = MaterialTheme.typography.titleLarge,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.anchoContenido(),
            )
        }
        item {
            OutlinedTextField(
                value = state.observacion,
                onValueChange = viewModel::onObservacionChange,
                label = { Text("Observación (opcional)") },
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.anchoContenido(),
            )
        }
        state.errorMensaje?.let { item { MensajeError(it) } }
        item { BotonPrincipal("Continuar", viewModel::onContinuarDesdeCantidad) }
    }
}

@Composable
private fun Confirmar(state: MuestreoDirectoUiState, viewModel: MuestreoDirectoViewModel) {
    ProveedorPantalla {
        item { Text("Confirmar muestreo directo", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.anchoContenido()) }
        item {
            SummaryCard {
                SummaryRow("Proveedor", state.proveedorPreview?.nombre ?: "Identificado por QR", primera = true)
                SummaryRow("Cantidad", state.litros.toDoubleOrNull()?.let(::formatLitros) ?: state.litros, destacado = true)
                SummaryRow("Método", "Muestreo directo")
            }
        }
        state.errorMensaje?.let { item { MensajeError(it) } }
        item { BotonPrincipal("Confirmar", viewModel::onConfirmar, cargando = state.submitting, operacion = true) }
        item { NotaRequiereAsistencia() }
        item { BotonSecundario("Corregir", viewModel::onVolverACantidad) }
    }
}

@Composable
private fun Resultado(state: MuestreoDirectoUiState, viewModel: MuestreoDirectoViewModel, onAnalizar: (Long) -> Unit, onListo: () -> Unit) {
    when (val resultado = state.resultado) {
        is RegistrarEntregaMuestreadaResult.GuardadaOffline -> PantallaGuardadoLocal(
            titulo = "Sin conexión a Internet",
            descripcion = "El muestreo se guardará en el dispositivo y se enviará automáticamente.",
            hechos = listOf("Muestreo guardado en el dispositivo"),
            nota = "Al sincronizarse aparecerá en Muestras pendientes para analizarla.",
            onContinuar = { viewModel.onNuevoRegistro(); onListo() },
        )
        is RegistrarEntregaMuestreadaResult.Confirmada -> ProveedorPantalla {
            item {
                Column(Modifier.anchoContenido().padding(top = 24.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal)
                    Text("Muestra registrada", style = MaterialTheme.typography.titleLarge)
                    Text("Ya está en Muestras pendientes.", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                }
            }
            item { BotonPrincipal("Continuar a análisis", { viewModel.onNuevoRegistro(); onAnalizar(resultado.entrega.id) }) }
            item { BotonSecundario("Listo", { viewModel.onNuevoRegistro(); onListo() }) }
        }
        is RegistrarEntregaMuestreadaResult.Rechazada -> ProveedorPantalla {
            item { MensajeError(resultado.mensaje) }
            item { BotonSecundario("Volver", viewModel::onVolverACantidad) }
        }
        null -> Box(Modifier.fillMaxWidth())
    }
}
