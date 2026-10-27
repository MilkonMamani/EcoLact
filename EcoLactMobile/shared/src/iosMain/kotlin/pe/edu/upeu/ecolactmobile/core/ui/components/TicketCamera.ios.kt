package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier

/** Sin cámara propia en iOS por ahora: la pantalla ofrece elegir la foto de la galería. */
@Composable
actual fun TicketCameraView(
    controller: TicketCameraController,
    onCaptured: (ImagenAdjunta) -> Unit,
    onError: (String) -> Unit,
    modifier: Modifier,
) {
    LaunchedEffect(Unit) { onError("La cámara no está disponible. Elige la foto del ticket de la galería.") }
}
