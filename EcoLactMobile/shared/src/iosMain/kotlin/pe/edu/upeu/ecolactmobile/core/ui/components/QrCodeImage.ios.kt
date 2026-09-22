package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

/**
 * TODO(iOS): placeholder sin verificar. Reemplazar por generación real de QR (CIFilter
 * "CIQRCodeGenerator" vía cinterop a CoreImage, o una librería Kotlin/Native pura) — esta app se
 * está desarrollando en Windows sin Xcode/macOS disponible, así que no se pudo implementar ni
 * compilar la versión real todavía.
 */
@Composable
actual fun QrCodeImage(content: String, modifier: Modifier) {
    Box(modifier = modifier.padding(16.dp), contentAlignment = Alignment.Center) {
        Text("QR no implementado aún en iOS\n$content")
    }
}
