package pe.edu.upeu.ecolactmobile.feature.calidad.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.ResultadoCalidadChip
import pe.edu.upeu.ecolactmobile.core.ui.components.rememberImagePickerLauncher
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.ConfirmarAnalisisResult

@Composable
fun AnalizarScreen(viewModel: AnalizarViewModel, onVerHistorialDetalle: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    if (state.cargando) {
        LoadingBox()
        return
    }
    state.error?.let {
        ErrorBox(it, onRetry = viewModel::cargar)
        return
    }

    when (state.step) {
        AnalizarStep.EVIDENCIA -> EvidenciaStep(viewModel)
        AnalizarStep.PARAMETROS -> ParametrosStep(viewModel)
        AnalizarStep.CONFIRMAR -> ConfirmarStep(viewModel)
        AnalizarStep.RESULTADO -> ResultadoStep(viewModel, onVerHistorialDetalle)
    }
}

@Composable
private fun EvidenciaStep(viewModel: AnalizarViewModel) {
    val state by viewModel.state.collectAsState()
    val launchPicker = rememberImagePickerLauncher(viewModel::onImagenSeleccionada)

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("1 de 3 · Evidencia", style = MaterialTheme.typography.titleMedium)
        Text("La fotografía es opcional: sirve como evidencia adjunta, no se lee automáticamente.", color = EcoLactColors.TextoSecundario)

        Button(onClick = launchPicker, modifier = Modifier.fillMaxWidth().height(56.dp)) {
            Text(if (state.imagen != null) "✓ Imagen seleccionada — cambiar" else "📷 Seleccionar imagen")
        }

        Button(
            onClick = viewModel::onContinuarDesdeEvidencia,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            Text("Continuar →")
        }
    }
}

@Composable
private fun ParametrosStep(viewModel: AnalizarViewModel) {
    val state by viewModel.state.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text("2 de 3 · Parámetros", style = MaterialTheme.typography.titleMedium)

        if (state.parametros.isEmpty()) {
            Text("No hay parámetros activos configurados.", color = EcoLactColors.TextoSecundario, modifier = Modifier.padding(top = 12.dp))
        } else {
            LazyColumn(modifier = Modifier.weight(1f).padding(top = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(state.parametros, key = { it.id }) { parametro ->
                    OutlinedTextField(
                        value = state.valores[parametro.codigo].orEmpty(),
                        onValueChange = { viewModel.onValorChange(parametro.codigo, it) },
                        label = { Text(parametro.nombre + (parametro.unidad?.let { " ($it)" } ?: "")) },
                        keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }

        OutlinedTextField(
            value = state.observacion,
            onValueChange = viewModel::onObservacionChange,
            label = { Text("Observación (opcional)") },
            modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
        )

        Button(
            onClick = viewModel::onContinuarDesdeParametros,
            modifier = Modifier.fillMaxWidth().height(52.dp).padding(top = 12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            Text("Continuar →")
        }
    }
}

@Composable
private fun ConfirmarStep(viewModel: AnalizarViewModel) {
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("3 de 3 · Confirmar", style = MaterialTheme.typography.titleMedium)
        Text(
            "El resultado (Aceptada/Observada/Rechazada) se calcula en el servidor al confirmar.",
            color = EcoLactColors.TextoSecundario,
        )

        state.parametros.forEach { parametro ->
            val valor = state.valores[parametro.codigo]
            if (!valor.isNullOrBlank()) {
                Text("${parametro.nombre}: $valor ${parametro.unidad.orEmpty()}")
            }
        }

        if (state.observacion.isNotBlank()) {
            Text("Observación: ${state.observacion}", color = EcoLactColors.TextoSecundario)
        }

        Text(
            "⚠ Una vez confirmado, el análisis no podrá editarse.",
            color = EcoLactColors.Amarillo,
        )

        Button(
            onClick = viewModel::onConfirmar,
            enabled = !state.enviando,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.enviando) {
                CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            } else {
                Text("CONFIRMAR ANÁLISIS")
            }
        }
        OutlinedButton(onClick = viewModel::onVolverAParametros, modifier = Modifier.fillMaxWidth()) {
            Text("Revisar valores")
        }
    }
}

@Composable
private fun ResultadoStep(viewModel: AnalizarViewModel, onVerHistorialDetalle: (Long) -> Unit) {
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        when (val resultado = state.resultado) {
            is ConfirmarAnalisisResult.Confirmado -> {
                Text("✓ ANÁLISIS CONFIRMADO", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.VerdePrincipal)
                ResultadoCalidadChip(resultado.analisis.resultado)
                TextButton(onClick = { onVerHistorialDetalle(resultado.analisis.id) }) { Text("Ver detalle") }
            }
            is ConfirmarAnalisisResult.GuardadoOffline -> {
                Text("🔒 CONFIRMADO LOCALMENTE", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Amarillo)
                Text(
                    "Se sincronizará automáticamente cuando vuelva la conexión. El resultado se sabrá al confirmarse en el servidor.",
                    color = EcoLactColors.TextoSecundario,
                )
            }
            is ConfirmarAnalisisResult.Rechazado -> {
                Text("⚠ No se pudo confirmar", style = MaterialTheme.typography.titleMedium, color = EcoLactColors.Rojo)
                Text(resultado.mensaje, color = EcoLactColors.Rojo)
            }
            null -> Text("Sin resultado")
        }
    }
}
