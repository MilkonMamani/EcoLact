package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable

/** TODO(iOS): placeholder, como rememberPdfOpener. Guardar/compartir requeriría UIActivityViewController — no implementado ni probado por falta de macOS/Xcode. */
@Composable
actual fun rememberPdfAcciones(): PdfAcciones = object : PdfAcciones {
    override fun descargar(bytes: ByteArray, nombre: String) {}
    override fun compartir(bytes: ByteArray, nombre: String, texto: String) {}
}
