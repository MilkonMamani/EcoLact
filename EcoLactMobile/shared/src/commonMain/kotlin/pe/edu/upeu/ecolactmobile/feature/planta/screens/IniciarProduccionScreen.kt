package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
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
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.IniciarProduccionResult

@Composable
fun IniciarProduccionScreen(
    viewModel: IniciarProduccionViewModel,
    onVerLote: (Long) -> Unit,
    onVerLotePendiente: (String) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    if (state.cargando) {
        LoadingBox()
        return
    }

    when (state.step) {
        IniciarStep.FORMULARIO -> FormularioStep(viewModel)
        IniciarStep.RESULTADO -> ResultadoStep(viewModel, onVerLote, onVerLotePendiente)
    }
}

@Composable
private fun FormularioStep(viewModel: IniciarProduccionViewModel) {
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Iniciar producción", style = MaterialTheme.typography.titleMedium)
        Text(
            "El acopio no se separa por entrega: ingrese el total de litros a usar.",
            color = EcoLactColors.TextoSecundario,
        )
        Text("Leche disponible: ${state.litrosDisponibles} L", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.VerdePrincipal)

        OutlinedTextField(
            value = state.litrosUtilizados,
            onValueChange = viewModel::onLitrosUtilizadosChange,
            label = { Text("Litros a utilizar") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(
            value = state.observacion,
            onValueChange = viewModel::onObservacionChange,
            label = { Text("Observación (opcional)") },
            modifier = Modifier.fillMaxWidth(),
        )

        state.error?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::onConfirmar,
            enabled = !state.enviando,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.enviando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            else Text("INICIAR PRODUCCIÓN")
        }
    }
}

@Composable
private fun ResultadoStep(
    viewModel: IniciarProduccionViewModel,
    onVerLote: (Long) -> Unit,
    onVerLotePendiente: (String) -> Unit,
) {
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        when (val resultado = state.resultado) {
            is IniciarProduccionResult.Confirmado -> {
                Text("✓ PRODUCCIÓN INICIADA", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.VerdePrincipal)
                Text(resultado.lote.codigo)
                TextButton(onClick = { onVerLote(resultado.lote.id) }) { Text("Ver lote") }
            }
            is IniciarProduccionResult.GuardadoOffline -> {
                Text("🟡 GUARDADO EN EL DISPOSITIVO", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Amarillo)
                Text("Se sincronizará automáticamente cuando vuelva la conexión.", color = EcoLactColors.TextoSecundario)
                TextButton(onClick = { onVerLotePendiente(resultado.localId) }) { Text("Ver detalle") }
            }
            is IniciarProduccionResult.Rechazado -> {
                Text("⚠ No se pudo iniciar", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Rojo)
                Text(resultado.mensaje, color = EcoLactColors.Rojo)
            }
            null -> Text("Sin resultado")
        }
    }
}
