package pe.edu.upeu.ecolactmobile.feature.trabajador

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido

/**
 * Autorización de asistencia de la sesión, provista por la shell de cada rol. Las pantallas la leen
 * para deshabilitar (no ocultar) sus botones de operación; el servidor valida igual.
 */
val LocalAutorizacion = compositionLocalOf { Autorizacion.HABILITADA }

/**
 * Aviso único en Inicio cuando todavía no se puede operar. Naranja suave (no es un error): la app
 * sigue navegable y la conexión (punto verde/naranja) es otra cosa.
 */
@Composable
fun AsistenciaRequeridaBanner(autorizacion: Autorizacion, onVerQr: () -> Unit, modifier: Modifier = Modifier) {
    if (autorizacion.puedeOperar) return
    val (titulo, texto) = when (autorizacion) {
        Autorizacion.NO_VERIFICADA -> "Asistencia no verificada para hoy" to
            "Conéctate a Internet después de registrar tu asistencia para habilitar las operaciones."
        else -> "Asistencia pendiente" to "Muestra tu QR al administrador para habilitar las operaciones del día."
    }
    Row(
        modifier = modifier.anchoContenido().background(EcoLactColors.NaranjaClaro, RoundedCornerShape(12.dp)).padding(start = 12.dp, top = 12.dp, end = 4.dp, bottom = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(EcoLactIcons.Warning, contentDescription = null, tint = EcoLactColors.Naranja, modifier = Modifier.size(20.dp))
        Column(Modifier.weight(1f)) {
            Text(titulo, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.NaranjaOscuro)
            Text(texto, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.NaranjaOscuro)
            TextButton(onClick = onVerQr, modifier = Modifier.align(Alignment.End)) {
                Icon(EcoLactIcons.QrCode, contentDescription = null, tint = EcoLactColors.NaranjaOscuro, modifier = Modifier.size(18.dp))
                Text("Ver mi QR", color = EcoLactColors.NaranjaOscuro, modifier = Modifier.padding(start = 6.dp))
            }
        }
    }
}

/** Debajo de un botón de operación deshabilitado: enseña qué falta, sin repetir el aviso en cada fila. */
@Composable
fun NotaRequiereAsistencia(modifier: Modifier = Modifier) {
    if (LocalAutorizacion.current.puedeOperar) return
    Text(
        "Necesitas registrar tu asistencia antes de realizar esta operación.",
        style = MaterialTheme.typography.bodySmall,
        color = EcoLactColors.TextoSecundario,
        textAlign = TextAlign.Center,
        modifier = modifier.anchoContenido(),
    )
}
