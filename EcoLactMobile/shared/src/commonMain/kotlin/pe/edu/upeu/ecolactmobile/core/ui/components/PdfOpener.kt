package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.runtime.Composable

/** Guarda el PDF recibido y lo abre con el visor del sistema. */
@Composable
expect fun rememberPdfOpener(): (ByteArray, String) -> Unit
