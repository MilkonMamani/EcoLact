package pe.edu.upeu.ecolactmobile.core.ui.navigation

import androidx.compose.runtime.Composable

/** iOS no tiene botón "atrás" de sistema: la navegación hacia atrás es la flecha de la barra superior. */
@Composable
actual fun PlatformBackHandler(enabled: Boolean, onBack: () -> Unit) = Unit
