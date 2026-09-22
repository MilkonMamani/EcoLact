package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier

/** Dibuja `content` (el `qr_token` de texto que entrega el backend) como imagen QR. */
@Composable
expect fun QrCodeImage(content: String, modifier: Modifier = Modifier)
