package pe.edu.upeu.ecolactmobile.feature.calidad.screens

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
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
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
import pe.edu.upeu.ecolactmobile.core.ui.state.ScreenModel
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.nowIso
import pe.edu.upeu.ecolactmobile.feature.calidad.CalidadRepository
import pe.edu.upeu.ecolactmobile.feature.calidad.CapturaResult
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.PantallaGuardadoLocal

data class CamaraUiState(
    val procesando: Boolean = false,
    val resultado: CapturaResult? = null,
    val errorCamara: String? = null,
)

/** Guarda la foto con su hora real; con conexión el servidor la lee (OCR) de inmediato. */
class CamaraTicketViewModel(private val repository: CalidadRepository, private val entregaId: Long, private val muestreoId: Long?) : ScreenModel() {
    private val _state = MutableStateFlow(CamaraUiState())
    val state: StateFlow<CamaraUiState> = _state.asStateFlow()

    fun onFoto(imagen: ImagenAdjunta) {
        // La hora del evento es la del disparo, no la del envío.
        val fechaCaptura = nowIso()
        scope.launch {
            _state.update { it.copy(procesando = true, errorCamara = null) }
            val resultado = repository.capturarTicket(entregaId, muestreoId, imagen, fechaCaptura)
            _state.update { it.copy(procesando = false, resultado = resultado) }
        }
    }

    fun onErrorCamara(mensaje: String) = _state.update { it.copy(errorCamara = mensaje) }

    fun reintentar() = _state.update { CamaraUiState() }
}

@Composable
fun CamaraTicketScreen(
    viewModel: CamaraTicketViewModel,
    onLeida: () -> Unit,
    onIngresarManual: () -> Unit,
    onCerrar: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    val resultado = state.resultado

    LaunchedEffect(resultado) { if (resultado == CapturaResult.Leida) onLeida() }

    when (resultado) {
        CapturaResult.GuardadaOffline -> PantallaGuardadoLocal(
            titulo = "Sin conexión a Internet",
            descripcion = "El análisis se guardará en el dispositivo y se enviará automáticamente.",
            hechos = listOf("Foto guardada"),
            nota = "La foto se leerá automáticamente cuando vuelva la conexión. Puedes ingresar los valores ahora.",
            onContinuar = onCerrar,
            accionSecundaria = "Ingresar valores ahora" to onIngresarManual,
        )
        is CapturaResult.Error -> ErrorCaptura(resultado.mensaje, viewModel::reintentar, onIngresarManual)
        else -> VisorCamara(state, viewModel, onCerrar)
    }
}

@Composable
private fun VisorCamara(state: CamaraUiState, viewModel: CamaraTicketViewModel, onCerrar: () -> Unit) {
    val controller = remember { TicketCameraController() }
    val abrirGaleria = rememberImagePickerLauncher(viewModel::onFoto)

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        TicketCameraView(controller, onCaptured = viewModel::onFoto, onError = viewModel::onErrorCamara)

        // Recuadro del ticket, centrado (ticket vertical, ~3:5).
        Box(
            Modifier
                .align(Alignment.Center)
                .fillMaxWidth(0.68f)
                .widthIn(max = 360.dp)
                .aspectRatio(0.6f)
                .border(2.dp, Color.White.copy(alpha = 0.9f), RoundedCornerShape(16.dp)),
        )

        Column(Modifier.align(Alignment.TopCenter).fillMaxWidth().padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text("Tomar foto del ticket", style = MaterialTheme.typography.titleMedium, color = Color.White)
            Text(
                state.errorCamara ?: "Centra el ticket dentro del recuadro, con buena luz.",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.White.copy(alpha = 0.85f),
                textAlign = TextAlign.Center,
            )
        }

        Row(
            Modifier.align(Alignment.BottomCenter).fillMaxWidth().navigationBarsPadding().padding(horizontal = 16.dp, vertical = 24.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(onClick = onCerrar, modifier = Modifier.weight(1f)) { Text("Cancelar", color = Color.White) }
            if (state.procesando) {
                Box(Modifier.size(72.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Color.White) }
            } else {
                IconButton(
                    onClick = controller::capturar,
                    enabled = controller.lista,
                    modifier = Modifier
                        .size(72.dp)
                        .border(4.dp, Color.White, CircleShape)
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

@Composable
private fun ErrorCaptura(mensaje: String, onReintentar: () -> Unit, onIngresarManual: () -> Unit) {
    Column(
        Modifier.fillMaxSize().padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterVertically),
    ) {
        Text("No se pudo leer el ticket", style = MaterialTheme.typography.titleMedium)
        Text(mensaje, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario, textAlign = TextAlign.Center)
        Spacer(Modifier.size(8.dp))
        BotonPrincipal("Tomar otra foto", onReintentar)
        BotonSecundario("Ingresar manualmente", onIngresarManual)
    }
}
