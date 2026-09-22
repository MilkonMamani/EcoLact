package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable

/**
 * TODO(iOS): placeholder. Seleccionar imagen requeriría PHPickerViewController vía cinterop —
 * no implementado ni probado por falta de macOS/Xcode en esta máquina. La evidencia fotográfica
 * es opcional en el contrato (calidad.md), así que el flujo de análisis sigue funcionando sin ella.
 */
@Composable
actual fun rememberImagePickerLauncher(onImagePicked: (ImagenAdjunta) -> Unit): () -> Unit {
    return { }
}
