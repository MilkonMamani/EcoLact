package pe.edu.upeu.ecolactmobile.core.ui.components

import android.content.ClipData
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.os.Build
import android.provider.MediaStore
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.FileProvider
import java.io.File

private const val MIME_PDF = "application/pdf"

/**
 * Descargar: Android 10+ escribe en Descargas con MediaStore (sin permisos de almacenamiento);
 * en versiones anteriores la persona elige dónde guardarlo (Storage Access Framework).
 * Compartir: el PDF va en caché, expuesto con FileProvider, y se abre el Sharesheet estándar.
 */
@Composable
actual fun rememberPdfAcciones(): PdfAcciones {
    val context = LocalContext.current
    // Bytes que esperan el lugar elegido por la persona (solo Android < 10).
    val pendiente = remember { mutableStateOf<ByteArray?>(null) }
    val crearDocumento = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument(MIME_PDF)) { uri ->
        val bytes = pendiente.value
        pendiente.value = null
        if (uri != null && bytes != null) {
            val ok = runCatching { context.contentResolver.openOutputStream(uri)?.use { it.write(bytes) } != null }.getOrDefault(false)
            avisar(context, if (ok) "Boleta guardada." else "No se pudo guardar la boleta.")
        }
    }
    return remember(context, crearDocumento) {
        object : PdfAcciones {
            override fun descargar(bytes: ByteArray, nombre: String) {
                if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
                    pendiente.value = bytes
                    crearDocumento.launch(nombre)
                    return
                }
                val ok = runCatching { guardarEnDescargas(context, bytes, nombre) }.getOrDefault(false)
                avisar(context, if (ok) "Boleta guardada en Descargas: $nombre" else "No se pudo guardar la boleta.")
            }

            override fun compartir(bytes: ByteArray, nombre: String, texto: String) {
                val archivo = File(File(context.cacheDir, "boletas").apply { mkdirs() }, nombre).apply { writeBytes(bytes) }
                val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", archivo)
                val envio = Intent(Intent.ACTION_SEND).apply {
                    type = MIME_PDF
                    putExtra(Intent.EXTRA_STREAM, uri)
                    putExtra(Intent.EXTRA_SUBJECT, texto)
                    putExtra(Intent.EXTRA_TEXT, texto)
                    clipData = ClipData.newRawUri(nombre, uri)
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                }
                context.startActivity(Intent.createChooser(envio, "Compartir boleta").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
            }
        }
    }
}

private fun guardarEnDescargas(context: Context, bytes: ByteArray, nombre: String): Boolean {
    val resolver = context.contentResolver
    val valores = ContentValues().apply {
        put(MediaStore.Downloads.DISPLAY_NAME, nombre)
        put(MediaStore.Downloads.MIME_TYPE, MIME_PDF)
        put(MediaStore.Downloads.IS_PENDING, 1)
    }
    val uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, valores) ?: return false
    return try {
        resolver.openOutputStream(uri)?.use { it.write(bytes) } ?: error("Sin salida")
        resolver.update(uri, ContentValues().apply { put(MediaStore.Downloads.IS_PENDING, 0) }, null, null)
        true
    } catch (e: Exception) {
        resolver.delete(uri, null, null)
        false
    }
}

private fun avisar(context: Context, mensaje: String) = Toast.makeText(context, mensaje, Toast.LENGTH_LONG).show()
