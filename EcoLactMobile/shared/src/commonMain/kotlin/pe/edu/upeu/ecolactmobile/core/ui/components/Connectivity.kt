package pe.edu.upeu.ecolactmobile.core.ui.components

import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatActualizado

/**
 * Único indicador de conexión de la app: un punto, sin texto. Verde = hay internet real
 * (ver ConnectivityObserver); naranja = sin conexión, se trabaja con lo guardado. Nunca rojo:
 * estar offline no es un error. El texto queda para lectores de pantalla.
 */
@Composable
fun ConnectionDot(isOnline: Boolean, modifier: Modifier = Modifier, size: Dp = 10.dp) {
    val color by animateColorAsState(if (isOnline) EcoLactColors.VerdePrincipal else EcoLactColors.Naranja)
    Box(
        modifier = modifier
            .semantics { contentDescription = if (isOnline) "Con conexión" else "Sin conexión" }
            .size(size)
            .background(color, CircleShape),
    )
}

/** "Datos guardados · 26/10 · 18:20": nota de una línea para pantallas de datos cuando no hay conexión. */
@Composable
fun DatosGuardadosNote(cachedAtIso: String?, modifier: Modifier = Modifier) {
    Text(
        text = "Datos guardados" + (cachedAtIso?.takeIf { it.isNotBlank() }?.let { " · ${formatActualizado(it)}" } ?: ""),
        style = MaterialTheme.typography.bodySmall,
        color = EcoLactColors.TextoSecundario,
        modifier = modifier,
    )
}
