package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable

data class ImagenAdjunta(val bytes: ByteArray, val filename: String, val mimeType: String)

/**
 * Lanza el selector de imágenes de la galería y entrega los bytes leídos. La cámara directa
 * ("Tomar fotografía" del mockup) no está implementada — solo "Seleccionar imagen"; agregar
 * captura directa requeriría configurar un FileProvider, se dejó fuera por tiempo.
 */
@Composable
expect fun rememberImagePickerLauncher(onImagePicked: (ImagenAdjunta) -> Unit): () -> Unit
