package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable

/** TODO(iOS): placeholder. Abrir un PDF nativamente requeriría QLPreviewController vía cinterop — no implementado ni probado por falta de macOS/Xcode en esta máquina. */
@Composable
actual fun rememberPdfOpener(): (ByteArray, String) -> Unit {
    return { _, _ -> }
}
