package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ChipTone
import pe.edu.upeu.ecolactmobile.core.ui.components.QrScannerView
import pe.edu.upeu.ecolactmobile.core.ui.components.StatusChip
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.acopiador.RegistrarEntregaResult

@Composable
fun AcopiadorRegistrarScreen(
    viewModel: RegistrarViewModel,
    onVerEntrega: (Long) -> Unit,
    onVerEntregaPendiente: (String) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    if (state.mostrandoScanner) {
        Box(modifier = Modifier.fillMaxSize()) {
            QrScannerView(modifier = Modifier.fillMaxSize(), onScanned = viewModel::onQrEscaneado)
            TextButton(onClick = viewModel::onCerrarScanner, modifier = Modifier.padding(16.dp)) {
                Text("Cancelar", color = EcoLactColors.Blanco)
            }
        }
        return
    }

    when (state.step) {
        RegistrarStep.IDENTIFICAR -> IdentificarStep(viewModel)
        RegistrarStep.CANTIDAD -> CantidadStep(viewModel)
        RegistrarStep.CONFIRMAR -> ConfirmarStep(viewModel)
        RegistrarStep.RESULTADO -> ResultadoStep(viewModel, onVerEntrega, onVerEntregaPendiente)
    }
}

@Composable
private fun IdentificarStep(viewModel: RegistrarViewModel) {
    val state by viewModel.state.collectAsState()
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Identifique al proveedor", style = MaterialTheme.typography.titleMedium)

        Button(
            onClick = viewModel::onAbrirScanner,
            modifier = Modifier.fillMaxWidth().height(64.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            Text("📷 Escanear QR")
        }

        Text("o", modifier = Modifier.fillMaxWidth(), textAlign = androidx.compose.ui.text.style.TextAlign.Center)

        OutlinedTextField(
            value = state.dniInput,
            onValueChange = viewModel::onDniInputChange,
            label = { Text("DNI del proveedor") },
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Number),
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        Button(onClick = viewModel::onBuscarDni, enabled = !state.buscandoProveedor, modifier = Modifier.fillMaxWidth()) {
            if (state.buscandoProveedor) {
                CircularProgressIndicator(modifier = Modifier.height(20.dp))
            } else {
                Text("Buscar")
            }
        }

        state.busquedaError?.let {
            Text(it, color = EcoLactColors.Rojo)
        }

        state.proveedorPreview?.let { proveedor ->
            Column(modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                Text("✓ Proveedor encontrado", color = EcoLactColors.VerdePrincipal)
                Text(proveedor.nombre_completo, style = MaterialTheme.typography.titleMedium)
                Text("DNI: ${proveedor.dni}", color = EcoLactColors.TextoSecundario)
                StatusChip(proveedor.estado, if (proveedor.estado == "ACTIVO") ChipTone.EXITO else ChipTone.NEUTRAL, Modifier.padding(top = 4.dp))
                Button(
                    onClick = viewModel::onContinuarDesdeIdentificacion,
                    modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                ) {
                    Text("Continuar →")
                }
            }
        }
    }
}

@Composable
private fun CantidadStep(viewModel: RegistrarViewModel) {
    val state by viewModel.state.collectAsState()
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        state.proveedorPreview?.let { Text(it.nombre_completo, style = MaterialTheme.typography.titleMedium) }
            ?: Text("Proveedor identificado por QR", style = MaterialTheme.typography.titleMedium)

        Text("CANTIDAD DE LECHE", color = EcoLactColors.TextoSecundario)
        OutlinedTextField(
            value = state.litros,
            onValueChange = viewModel::onLitrosChange,
            label = { Text("Litros") },
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Decimal),
            singleLine = true,
            textStyle = MaterialTheme.typography.titleLarge,
            modifier = Modifier.fillMaxWidth(),
        )

        OutlinedTextField(
            value = state.observacion,
            onValueChange = viewModel::onObservacionChange,
            label = { Text("Observación (opcional)") },
            modifier = Modifier.fillMaxWidth(),
        )

        state.errorMensaje?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::onContinuarDesdeCantidad,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            Text("Continuar →")
        }
    }
}

@Composable
private fun ConfirmarStep(viewModel: RegistrarViewModel) {
    val state by viewModel.state.collectAsState()
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("CONFIRMAR ENTREGA", style = MaterialTheme.typography.titleMedium)

        state.proveedorPreview?.let {
            Text("Proveedor", color = EcoLactColors.TextoSecundario)
            Text(it.nombre_completo, style = MaterialTheme.typography.bodyLarge)
        }

        Text("Cantidad", color = EcoLactColors.TextoSecundario)
        Text("${state.litros} L", style = MaterialTheme.typography.titleLarge)

        if (state.observacion.isNotBlank()) {
            Text("Observación", color = EcoLactColors.TextoSecundario)
            Text(state.observacion)
        }

        state.errorMensaje?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::onConfirmar,
            enabled = !state.submitting,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.submitting) {
                CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            } else {
                Text("CONFIRMAR ENTREGA")
            }
        }
        OutlinedButton(onClick = viewModel::onVolverACantidad, modifier = Modifier.fillMaxWidth()) {
            Text("Corregir")
        }
    }
}

@Composable
private fun ResultadoStep(
    viewModel: RegistrarViewModel,
    onVerEntrega: (Long) -> Unit,
    onVerEntregaPendiente: (String) -> Unit,
) {
    val state by viewModel.state.collectAsState()
    val resultado = state.resultado

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        when (resultado) {
            is RegistrarEntregaResult.Confirmada -> {
                Text("✓ ENTREGA REGISTRADA", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.VerdePrincipal)
                Text("${resultado.entrega.litros} L", style = MaterialTheme.typography.titleLarge)
                Text("Guardada correctamente en el servidor.")
                TextButton(onClick = { onVerEntrega(resultado.entrega.id) }) { Text("Ver detalle") }
            }
            is RegistrarEntregaResult.GuardadaOffline -> {
                Text("🟡 GUARDADA EN EL DISPOSITIVO", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Amarillo)
                Text("${state.litros} L")
                Text("Se sincronizará automáticamente cuando vuelva la conexión.", color = EcoLactColors.TextoSecundario)
                TextButton(onClick = { onVerEntregaPendiente(resultado.localId) }) { Text("Ver detalle") }
            }
            is RegistrarEntregaResult.Rechazada -> {
                Text("⚠ No se pudo registrar", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Rojo)
                Text(resultado.mensaje, color = EcoLactColors.Rojo)
            }
            null -> Text("Sin resultado")
        }

        Button(
            onClick = viewModel::onNuevoRegistro,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            Text("+ Registrar otra entrega")
        }
    }
}
