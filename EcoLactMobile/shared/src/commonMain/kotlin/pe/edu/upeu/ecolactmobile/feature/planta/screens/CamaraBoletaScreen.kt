package pe.edu.upeu.ecolactmobile.feature.planta.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.edu.upeu.ecolactmobile.core.ui.components.ImagenAdjunta
import pe.edu.upeu.ecolactmobile.core.ui.components.TicketCameraController
import pe.edu.upeu.ecolactmobile.core.ui.components.TicketCameraView
import pe.edu.upeu.ecolactmobile.core.ui.components.rememberImagePickerLauncher
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.PantallaGuardadoLocal
import pe.edu.upeu.ecolactmobile.feature.planta.CapturaBoletaResult
import pe.edu.upeu.ecolactmobile.feature.planta.PlantaRepository

data class CamaraBoletaState(val procesando: Boolean = false, val resultado: CapturaBoletaResult? = null, val errorCamara: String? = null)

/** La foto se guarda en el teléfono; con conexión el servidor la lee (OCR) de inmediato. Nunca registra stock. */
class CamaraBoletaViewModel(private val repository: PlantaRepository) : ScreenModel() {
    private val _state = MutableStateFlow(CamaraBoletaState())
    val state: StateFlow<CamaraBoletaState> = _state.asStateFlow()

    fun onFoto(imagen: ImagenAdjunta) {
        if (_state.value.procesando) return
        scope.launch {
            _state.update { it.copy(procesando = true, errorCamara = null) }
            val resultado = repository.capturarBoleta(imagen)
            _state.update { it.copy(procesando = false, resultado = resultado) }
        }
    }

    fun onErrorCamara(mensaje: String) = _state.update { it.copy(errorCamara = mensaje) }

    fun reintentar() = _state.update { CamaraBoletaState() }
}

@Composable
fun CamaraBoletaScreen(
    viewModel: CamaraBoletaViewModel,
    onLeida: (String) -> Unit,
    onRevisarAhora: (String) -> Unit,
    onCerrar: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    val resultado = state.resultado

    LaunchedEffect(resultado) { if (resultado is CapturaBoletaResult.Leida) onLeida(resultado.borradorId) }

    when (resultado) {
        is CapturaBoletaResult.GuardadaOffline -> PantallaGuardadoLocal(
            titulo = "Sin conexión a Internet",
            descripcion = "La foto de la boleta quedó guardada en el dispositivo.",
            hechos = listOf("Foto guardada"),
            nota = "Se leerá automáticamente cuando vuelva la conexión y quedará lista para revisar. La compra no se registra hasta que la confirmes.",
            onContinuar = onCerrar,
            accionSecundaria = "Asociar insumos ahora" to { onRevisarAhora(resultado.borradorId) },
        )
        is CapturaBoletaResult.Error -> Column(
            Modifier.fillMaxSize().padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterVertically),
        ) {
            Text("No se pudo procesar la boleta", style = MaterialTheme.typography.titleMedium)
            Text(resultado.mensaje, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center)
            Spacer(Modifier.size(8.dp))
            BotonPrincipal("Tomar otra foto", viewModel::reintentar)
            BotonSecundario("Cancelar", onCerrar)
        }
        else -> VisorBoleta(state, viewModel, onCerrar)
    }
}

/** Pantalla negra, marco verde centrado, botón de captura abajo. */
@Composable
private fun VisorBoleta(state: CamaraBoletaState, viewModel: CamaraBoletaViewModel, onCerrar: () -> Unit) {
    val controller = remember { TicketCameraController() }
    val abrirGaleria = rememberImagePickerLauncher(viewModel::onFoto)

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        TicketCameraView(controller, onCaptured = viewModel::onFoto, onError = viewModel::onErrorCamara)

        // Marco de la boleta (vertical, ~3:4).
        Box(
            Modifier
                .align(Alignment.Center)
                .fillMaxWidth(0.78f)
                .widthIn(max = 380.dp)
                .aspectRatio(0.72f)
                .border(3.dp, EcoLactColors.VerdePrincipal, RoundedCornerShape(16.dp)),
        )

        Row(
            Modifier.align(Alignment.TopCenter).fillMaxWidth().statusBarsPadding().padding(horizontal = 4.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onCerrar) { Icon(EcoLactIcons.Close, contentDescription = "Cerrar", tint = Color.White) }
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Tomar foto de la boleta", style = MaterialTheme.typography.titleMedium, color = Color.White)
                Text(
                    state.errorCamara ?: "Centra la boleta dentro del marco, con buena luz.",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.85f),
                    textAlign = TextAlign.Center,
                )
            }
            Spacer(Modifier.size(48.dp))
        }

        Row(
            Modifier.align(Alignment.BottomCenter).fillMaxWidth().navigationBarsPadding().padding(horizontal = 16.dp, vertical = 24.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(onClick = onCerrar, modifier = Modifier.weight(1f)) { Text("Cancelar", color = Color.White) }
            if (state.procesando) {
                Box(Modifier.size(72.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = EcoLactColors.VerdePrincipal) }
            } else {
                IconButton(
                    onClick = controller::capturar,
                    enabled = controller.lista,
                    modifier = Modifier
                        .size(72.dp)
                        .border(4.dp, EcoLactColors.VerdePrincipal, CircleShape)
                        .padding(8.dp)
                        .background(if (controller.lista) Color.White else Color.White.copy(alpha = 0.3f), CircleShape)
                        .semantics { contentDescription = "Tomar foto" },
                ) {}
            }
            TextButton(onClick = abrirGaleria, modifier = Modifier.weight(1f), enabled = !state.procesando) {
                Text("Galería", color = Color.White)
            }
        }
    }
}
