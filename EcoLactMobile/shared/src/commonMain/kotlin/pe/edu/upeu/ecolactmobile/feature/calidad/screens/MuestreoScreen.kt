package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.KeyboardOptions
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.QrScannerView
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.RegistrarEntregaMuestreadaResult

/**
 * Uso: cuando el analista muestrea a un proveedor directamente en campo y anticipa que el
 * resultado va a ser RECHAZADA (si va a ser ACEPTADA/OBSERVADA, avisa al acopiador de siempre).
 */
@Composable
fun MuestreoScreen(viewModel: MuestreoViewModel, onVerHistorialDetalle: (Long) -> Unit, onListo: () -> Unit) {
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
        MuestreoStep.IDENTIFICAR -> IdentificarStep(viewModel)
        MuestreoStep.CANTIDAD -> CantidadStep(viewModel)
        MuestreoStep.CONFIRMAR -> ConfirmarStep(viewModel)
        MuestreoStep.RESULTADO -> ResultadoStep(viewModel, onVerHistorialDetalle, onListo)
    }
}

@Composable
private fun IdentificarStep(viewModel: MuestreoViewModel) {
    val state by viewModel.state.collectAsState()
    Column(modifier = Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        Text("Muestreo directo", style = MaterialTheme.typography.titleMedium)
        Text("Use esto solo si anticipa que el resultado va a ser RECHAZADA.", color = EcoLactColors.TextoSecundario)

        Button(
            onClick = viewModel::onAbrirScanner,
            modifier = Modifier.fillMaxWidth().height(64.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) { Text("📷 Escanear QR") }

        Text("o", modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)

        OutlinedTextField(
            value = state.dniInput,
            onValueChange = viewModel::onDniInputChange,
            label = { Text("DNI del proveedor") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        Button(onClick = viewModel::onBuscarDni, enabled = !state.buscandoProveedor, modifier = Modifier.fillMaxWidth()) {
            if (state.buscandoProveedor) CircularProgressIndicator(modifier = Modifier.height(20.dp)) else Text("Buscar")
        }

        state.busquedaError?.let { Text(it, color = EcoLactColors.Rojo) }

        state.proveedorPreview?.let { proveedor ->
            Column(modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                Text("✓ Proveedor encontrado", color = EcoLactColors.VerdePrincipal)
                Text(proveedor.nombre, style = MaterialTheme.typography.titleMedium)
                Text("DNI: ${proveedor.dni}", color = EcoLactColors.TextoSecundario)
                Button(
                    onClick = viewModel::onContinuarDesdeIdentificacion,
                    modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                ) { Text("Continuar →") }
            }
        }
    }
}

@Composable
private fun CantidadStep(viewModel: MuestreoViewModel) {
    val state by viewModel.state.collectAsState()
    Column(modifier = Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        state.proveedorPreview?.let { Text(it.nombre, style = MaterialTheme.typography.titleMedium) }
            ?: Text("Proveedor identificado por QR", style = MaterialTheme.typography.titleMedium)

        OutlinedTextField(
            value = state.litros,
            onValueChange = viewModel::onLitrosChange,
            label = { Text("Litros") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
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
        ) { Text("Continuar →") }
    }
}

@Composable
private fun ConfirmarStep(viewModel: MuestreoViewModel) {
    val state by viewModel.state.collectAsState()
    Column(modifier = Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("CONFIRMAR MUESTREO", style = MaterialTheme.typography.titleMedium)
        state.proveedorPreview?.let {
            Text("Proveedor", color = EcoLactColors.TextoSecundario)
            Text(it.nombre, style = MaterialTheme.typography.bodyLarge)
        }
        Text("Cantidad", color = EcoLactColors.TextoSecundario)
        Text("${state.litros} L", style = MaterialTheme.typography.titleLarge)

        state.errorMensaje?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::onConfirmar,
            enabled = !state.submitting,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.submitting) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco) else Text("CONFIRMAR")
        }
        OutlinedButton(onClick = viewModel::onVolverACantidad, modifier = Modifier.fillMaxWidth()) { Text("Corregir") }
    }
}

@Composable
private fun ResultadoStep(viewModel: MuestreoViewModel, onVerHistorialDetalle: (Long) -> Unit, onListo: () -> Unit) {
    val state by viewModel.state.collectAsState()
    Column(modifier = Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        when (val resultado = state.resultado) {
            is RegistrarEntregaMuestreadaResult.Confirmada -> {
                Text("✓ ENTREGA REGISTRADA", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.VerdePrincipal)
                Text("Ahora registre el análisis de esta entrega (#${resultado.entrega.id}).", color = EcoLactColors.TextoSecundario)
                TextButton(onClick = { onVerHistorialDetalle(resultado.entrega.id) }) { Text("Ir a analizar") }
            }
            is RegistrarEntregaMuestreadaResult.GuardadaOffline -> {
                Text("🟡 GUARDADA EN EL DISPOSITIVO", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Amarillo)
                Text("Se sincronizará automáticamente cuando vuelva la conexión; el análisis se registra después de eso.", color = EcoLactColors.TextoSecundario)
            }
            is RegistrarEntregaMuestreadaResult.Rechazada -> {
                Text("⚠ No se pudo registrar", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Rojo)
                Text(resultado.mensaje, color = EcoLactColors.Rojo)
            }
            null -> Text("Sin resultado")
        }
        Button(
            onClick = { viewModel.onNuevoRegistro(); onListo() },
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) { Text("Listo") }
    }
}
