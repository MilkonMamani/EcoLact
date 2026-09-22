package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier

/**
 * Vista de cámara que escanea un QR y entrega su texto crudo (el `qr_token`) tal cual, sin
 * interpretarlo — el acopiador lo manda como `identificador` al registrar la entrega.
 */
@Composable
expect fun QrScannerView(modifier: Modifier = Modifier, onScanned: (String) -> Unit)
