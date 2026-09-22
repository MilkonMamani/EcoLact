package pe.edu.upeu.ecolactmobile.core.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

private val EcoLactColorScheme = lightColorScheme(
    primary = EcoLactColors.VerdePrincipal,
    onPrimary = EcoLactColors.Blanco,
    primaryContainer = EcoLactColors.VerdeClaro,
    onPrimaryContainer = EcoLactColors.VerdeOscuro,
    secondary = EcoLactColors.AzulInstitucional,
    onSecondary = EcoLactColors.Blanco,
    secondaryContainer = EcoLactColors.AzulClaro,
    onSecondaryContainer = EcoLactColors.AzulOscuro,
    tertiary = EcoLactColors.Amarillo,
    onTertiary = EcoLactColors.TextoPrincipal,
    tertiaryContainer = EcoLactColors.AmarilloClaro,
    onTertiaryContainer = EcoLactColors.TextoPrincipal,
    error = EcoLactColors.Rojo,
    onError = EcoLactColors.Blanco,
    errorContainer = EcoLactColors.RojoClaro,
    onErrorContainer = EcoLactColors.Rojo,
    background = EcoLactColors.Fondo,
    onBackground = EcoLactColors.TextoPrincipal,
    surface = EcoLactColors.Blanco,
    onSurface = EcoLactColors.TextoPrincipal,
    surfaceVariant = EcoLactColors.Fondo,
    onSurfaceVariant = EcoLactColors.TextoSecundario,
    outline = EcoLactColors.Bordes,
    outlineVariant = EcoLactColors.Bordes,
)

/**
 * Estados visuales semánticos usados de forma consistente en toda la app (ver principios de
 * UI/UX del prompt inicial: "estados visuales claros"). Nunca mezclar estos significados: verde
 * es éxito/aceptado, azul es informativo/neutral, amarillo es pendiente/advertencia, rojo es
 * error/crítico/rechazado.
 */
object StatusColors {
    val Exito = EcoLactColors.VerdePrincipal
    val ExitoFondo = EcoLactColors.VerdeClaro
    val Info = EcoLactColors.AzulInstitucional
    val InfoFondo = EcoLactColors.AzulClaro
    val Advertencia = EcoLactColors.Amarillo
    val AdvertenciaFondo = EcoLactColors.AmarilloClaro
    val Error = EcoLactColors.Rojo
    val ErrorFondo = EcoLactColors.RojoClaro
}

private val EcoLactTypography = Typography(
    titleLarge = TextStyle(fontWeight = FontWeight.Bold, fontSize = 24.sp),
    titleMedium = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 18.sp),
    bodyLarge = TextStyle(fontWeight = FontWeight.Normal, fontSize = 16.sp),
    bodyMedium = TextStyle(fontWeight = FontWeight.Normal, fontSize = 14.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 14.sp),
)

@Composable
fun EcoLactTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = EcoLactColorScheme,
        typography = EcoLactTypography,
        content = content,
    )
}
