package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

/**
 * TODO(iOS): placeholder sin verificar. El escaneo real necesitaría AVFoundation/Vision vía
 * cinterop — no implementado ni probado por falta de macOS/Xcode en esta máquina. Mientras tanto,
 * se ofrece un campo de texto para pegar el `qr_token` manualmente y no bloquear el flujo.
 */
@Composable
actual fun QrScannerView(modifier: Modifier, onScanned: (String) -> Unit) {
    var value by remember { mutableStateOf("") }
    Column(modifier = modifier.fillMaxSize().padding(24.dp)) {
        Text("Escaneo de cámara no implementado aún en iOS. Ingrese el código manualmente:")
        OutlinedTextField(value = value, onValueChange = { value = it }, modifier = Modifier.padding(top = 12.dp))
        Button(onClick = { if (value.isNotBlank()) onScanned(value) }, modifier = Modifier.padding(top = 12.dp)) {
            Text("Usar código")
        }
    }
}
