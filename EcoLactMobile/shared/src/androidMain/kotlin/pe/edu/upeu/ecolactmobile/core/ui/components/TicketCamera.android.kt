package pe.edu.upeu.ecolactmobile.core.ui.components

import android.Manifest
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageCapture
import androidx.camera.core.ImageCaptureException
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.LocalLifecycleOwner
import java.io.ByteArrayOutputStream

/** Lado mayor de la foto enviada: suficiente para el OCR y muy por debajo del límite de 5 MB. */
private const val LADO_MAXIMO = 2000

@Composable
actual fun TicketCameraView(
    controller: TicketCameraController,
    onCaptured: (ImagenAdjunta) -> Unit,
    onError: (String) -> Unit,
    modifier: Modifier,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val capturada by rememberUpdatedState(onCaptured)
    val fallo by rememberUpdatedState(onError)
    var hasPermission by remember {
        mutableStateOf(ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED)
    }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        hasPermission = granted
        if (!granted) fallo("Se necesita permiso de cámara para fotografiar el ticket.")
    }
    LaunchedEffect(Unit) { if (!hasPermission) permissionLauncher.launch(Manifest.permission.CAMERA) }
    if (!hasPermission) return

    val imageCapture = remember {
        ImageCapture.Builder().setCaptureMode(ImageCapture.CAPTURE_MODE_MAXIMIZE_QUALITY).build()
    }
    DisposableEffect(controller) {
        controller.disparar = {
            imageCapture.takePicture(
                ContextCompat.getMainExecutor(context),
                object : ImageCapture.OnImageCapturedCallback() {
                    override fun onCaptureSuccess(image: ImageProxy) {
                        val jpeg = runCatching { jpegDerecho(image) }.getOrNull()
                        image.close()
                        if (jpeg != null) capturada(ImagenAdjunta(jpeg, "ticket.jpg", "image/jpeg")) else fallo("No se pudo procesar la foto. Intenta de nuevo.")
                    }

                    override fun onError(exception: ImageCaptureException) {
                        fallo("No se pudo tomar la foto. Intenta de nuevo.")
                    }
                },
            )
        }
        onDispose { controller.disparar = null }
    }

    AndroidView(
        modifier = modifier.fillMaxSize(),
        factory = { ctx ->
            val previewView = PreviewView(ctx)
            val providerFuture = ProcessCameraProvider.getInstance(ctx)
            providerFuture.addListener({
                val provider = providerFuture.get()
                val preview = Preview.Builder().build().also { it.surfaceProvider = previewView.surfaceProvider }
                try {
                    provider.unbindAll()
                    provider.bindToLifecycle(lifecycleOwner, CameraSelector.DEFAULT_BACK_CAMERA, preview, imageCapture)
                } catch (_: Exception) {
                    fallo("No hay cámara disponible en este dispositivo.")
                }
            }, ContextCompat.getMainExecutor(ctx))
            previewView
        },
    )
}

/** JPEG de la captura en memoria, rotado según el sensor y reducido a [LADO_MAXIMO]. */
private fun jpegDerecho(image: ImageProxy): ByteArray {
    val buffer = image.planes[0].buffer
    val bytes = ByteArray(buffer.remaining()).also { buffer.get(it) }
    val original = BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
    val escala = minOf(1f, LADO_MAXIMO.toFloat() / maxOf(original.width, original.height))
    val matriz = Matrix().apply {
        postScale(escala, escala)
        postRotate(image.imageInfo.rotationDegrees.toFloat())
    }
    val derecho = Bitmap.createBitmap(original, 0, 0, original.width, original.height, matriz, true)
    return ByteArrayOutputStream().use { salida ->
        derecho.compress(Bitmap.CompressFormat.JPEG, 85, salida)
        salida.toByteArray()
    }
}
