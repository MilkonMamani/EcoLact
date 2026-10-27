package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier

/**
 * Disparador de la cámara del ticket: la pantalla dibuja su propio botón de captura y llama a
 * [capturar]; la vista de cada plataforma registra aquí cómo tomar la foto.
 */
class TicketCameraController {
    /** Estado observable: el botón de captura se habilita cuando la cámara queda lista. */
    internal var disparar: (() -> Unit)? by mutableStateOf(null)

    /** false mientras la cámara no está lista (sin permiso o sin cámara). */
    val lista: Boolean get() = disparar != null

    fun capturar() {
        disparar?.invoke()
    }
}

/**
 * Vista previa de la cámara trasera para fotografiar el ticket del analizador. Entrega la foto
 * ya derecha y en JPEG liviano (el servidor acepta hasta 5 MB). [onError]: sin permiso o sin
 * cámara — la pantalla ofrece elegir la foto de la galería.
 */
@Composable
expect fun TicketCameraView(
    controller: TicketCameraController,
    onCaptured: (ImagenAdjunta) -> Unit,
    onError: (String) -> Unit,
    modifier: Modifier = Modifier,
)
