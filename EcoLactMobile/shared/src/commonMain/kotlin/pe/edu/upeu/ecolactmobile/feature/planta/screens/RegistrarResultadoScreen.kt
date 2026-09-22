package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.ErrorBox
import pe.edu.upeu.ecolactmobile.core.ui.components.LoadingBox
import pe.edu.upeu.ecolactmobile.core.ui.components.SectionTitle
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.planta.RegistrarResultadoOpResult

@Composable
fun RegistrarResultadoScreen(viewModel: RegistrarResultadoViewModel, onTerminado: () -> Unit) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(state.resultado) {
        val resultado = state.resultado
        if (resultado is RegistrarResultadoOpResult.Confirmado || resultado is RegistrarResultadoOpResult.GuardadoOffline) {
            onTerminado()
        }
    }

    if (state.cargando) {
        LoadingBox()
        return
    }
    if (state.productos.isEmpty() && state.error != null) {
        ErrorBox(state.error ?: "", onRetry = viewModel::cargar)
        return
    }

    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        SectionTitle("Producto obtenido")
        androidx.compose.foundation.layout.Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            state.productos.forEach { producto ->
                FilterChip(
                    selected = state.productoSeleccionadoId == producto.id,
                    onClick = { viewModel.onProductoSeleccionado(producto.id) },
                    label = { Text(producto.nombre) },
                )
            }
        }

        OutlinedTextField(
            value = state.cantidadProducida,
            onValueChange = viewModel::onCantidadProducidaChange,
            label = { Text("Cantidad obtenida") },
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Decimal),
            modifier = Modifier.fillMaxWidth(),
        )

        Text(
            "Los insumos necesarios se calculan solos según la receta del producto — se muestran al confirmar.",
            color = EcoLactColors.TextoSecundario,
        )

        val mensajeError = (state.resultado as? RegistrarResultadoOpResult.Rechazado)?.mensaje ?: state.error
        mensajeError?.let { Text(it, color = EcoLactColors.Rojo) }

        Button(
            onClick = viewModel::onConfirmar,
            enabled = !state.enviando,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
        ) {
            if (state.enviando) CircularProgressIndicator(modifier = Modifier.height(20.dp), color = EcoLactColors.Blanco)
            else Text("CONFIRMAR PRODUCCIÓN")
        }
    }
}
