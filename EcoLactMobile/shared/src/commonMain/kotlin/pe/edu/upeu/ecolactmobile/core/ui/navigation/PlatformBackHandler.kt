package pe.edu.upeu.ecolactmobile.core.ui.navigation

import androidx.compose.runtime.Composable

/**
 * Intercepta el botón/gesto "atrás" del sistema mientras [enabled] sea true. Necesario porque la
 * navegación es manual ([Navigator]): sin esto, "atrás" cerraría la app desde cualquier pantalla.
 */
@Composable
expect fun PlatformBackHandler(enabled: Boolean, onBack: () -> Unit)
