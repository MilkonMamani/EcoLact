package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalContext

@Composable
actual fun rememberImagePickerLauncher(onImagePicked: (ImagenAdjunta) -> Unit): () -> Unit {
    val context = LocalContext.current
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            if (bytes != null) {
                val mimeType = context.contentResolver.getType(uri) ?: "image/jpeg"
                val extension = if (mimeType.contains("png")) "png" else "jpg"
                onImagePicked(ImagenAdjunta(bytes, "evidencia.$extension", mimeType))
            }
        }
    }
    return { launcher.launch("image/*") }
}
